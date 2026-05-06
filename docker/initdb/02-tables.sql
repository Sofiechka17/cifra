-- Схема восстановлена из PHP-кода. Типы и длины — предположительные.
-- Проверить/уточнить перед продом:
--   * точные VARCHAR-длины
--   * политики ON DELETE (сейчас RESTRICT по умолчанию)
--   * существование колонки role vs is_admin (в коде встречаются обе)
--   * индексы кроме PK/FK

SET search_path TO cit_schema;

-- municipalities -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS municipalities (
    municipality_id   SERIAL PRIMARY KEY,
    municipality_name VARCHAR(255) NOT NULL UNIQUE
);

-- users ----------------------------------------------------------------------
-- Источники:
--   register.php:41 — INSERT (user_full_name, user_login, user_password,
--                             user_email, user_phone, municipality_id, is_admin)
--   login.php:24    — SELECT user_id, user_full_name, user_password, role,
--                            municipality_id
--   save_table.php:130 — SELECT municipality_id WHERE user_id = $1
-- В коде присутствуют ОБЕ колонки: is_admin (bool) и role (text).
-- Скорее всего role — более новая (используется для 'admin'/'minec'/'user').
CREATE TABLE IF NOT EXISTS users (
    user_id         SERIAL PRIMARY KEY,
    user_full_name  VARCHAR(255) NOT NULL,
    user_login      VARCHAR(100) NOT NULL UNIQUE,
    user_password   VARCHAR(255) NOT NULL,           -- bcrypt-хэш
    user_email      VARCHAR(255) NOT NULL UNIQUE,
    user_phone      VARCHAR(50),
    municipality_id INTEGER NOT NULL
        REFERENCES municipalities(municipality_id),
    is_admin        BOOLEAN NOT NULL DEFAULT FALSE,
    role            VARCHAR(32) NOT NULL DEFAULT 'user'
        CHECK (role IN ('user', 'admin', 'minec'))
);

CREATE INDEX IF NOT EXISTS users_municipality_id_idx
    ON users (municipality_id);

-- table_templates ------------------------------------------------------------
-- core/TemplateService.php:109 — INSERT (template_name, template_headers,
--                                        template_structure, is_active)
-- headers хранятся как JSONB-массив [{name, type, readonly}, ...]
-- structure — {rows: [{rowType, cells}], merges: [{startRow, startCol, rowSpan, colSpan}]}
CREATE TABLE IF NOT EXISTS table_templates (
    template_id        SERIAL PRIMARY KEY,
    template_name      VARCHAR(255) NOT NULL,
    template_headers   JSONB NOT NULL DEFAULT '[]'::jsonb,
    template_structure JSONB NOT NULL DEFAULT '{"rows":[]}'::jsonb,
    is_active          BOOLEAN NOT NULL DEFAULT FALSE
);

-- Одновременно активным может быть только один шаблон
-- (setActiveTemplate() в транзакции гасит все остальные).
CREATE UNIQUE INDEX IF NOT EXISTS table_templates_one_active_idx
    ON table_templates ((TRUE)) WHERE is_active;

-- filled_data ----------------------------------------------------------------
-- core/TemplateService.php:173 — INSERT (user_id, template_id,
--                                        municipality_id, filled_data)
-- admin_view.php:52 / export_excel.php:41 — SELECT filled_data_id, template_id,
--                                                  filled_data, filled_date
CREATE TABLE IF NOT EXISTS filled_data (
    filled_data_id  SERIAL PRIMARY KEY,
    user_id         INTEGER NOT NULL
        REFERENCES users(user_id),
    template_id     INTEGER NOT NULL
        REFERENCES table_templates(template_id),
    municipality_id INTEGER NOT NULL
        REFERENCES municipalities(municipality_id),
    filled_data     JSONB NOT NULL,
    filled_date     TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS filled_data_template_id_idx
    ON filled_data (template_id);
CREATE INDEX IF NOT EXISTS filled_data_user_id_idx
    ON filled_data (user_id);
CREATE INDEX IF NOT EXISTS filled_data_municipality_id_idx
    ON filled_data (municipality_id);

-- feedback_requests ----------------------------------------------------------
-- submit_form.php:53 — INSERT (user_id, full_name_feedback,
--                              phone_feedback, problem_description_feedback)
-- export_feedback_excel.php:18 — SELECT feedback_id, full_name_feedback,
--                                       phone_feedback, problem_description_feedback
-- user_id допускаем NULL: форма есть и в неавторизованном виде на лендинге.
CREATE TABLE IF NOT EXISTS feedback_requests (
    feedback_id                  SERIAL PRIMARY KEY,
    user_id                      INTEGER
        REFERENCES users(user_id) ON DELETE SET NULL,
    full_name_feedback           VARCHAR(255) NOT NULL,
    phone_feedback               VARCHAR(50)  NOT NULL,
    problem_description_feedback TEXT         NOT NULL,
    created_at                   TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS feedback_requests_user_id_idx
    ON feedback_requests (user_id);
