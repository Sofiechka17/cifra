-- Тестовые данные. У всех пользователей пароль: test123
SET search_path TO cit_schema;

-- municipalities -------------------------------------------------------------
INSERT INTO municipalities (municipality_name) VALUES
    ('Оренбург'),
    ('Орск'),
    ('Новотроицк'),
    ('Бузулук'),
    ('Бугуруслан')
ON CONFLICT (municipality_name) DO NOTHING;

-- users ----------------------------------------------------------------------
-- password = test123 (bcrypt)
INSERT INTO users (user_full_name, user_login, user_password, user_email, user_phone, municipality_id, is_admin, role) VALUES
    ('Админов Админ Админович',     'admin',   '$2y$10$Rb0INX8pUWosN9mXsn1zKOMKarR86AVE.UDkgcaL3w9ZXxncrLqB2', 'admin@example.ru', '+7 (900) 000-00-01', 1, TRUE,  'admin'),
    ('Минэков Минэк Минэкович',     'minec',   '$2y$10$Rb0INX8pUWosN9mXsn1zKOMKarR86AVE.UDkgcaL3w9ZXxncrLqB2', 'minec@example.ru', '+7 (900) 000-00-02', 1, FALSE, 'minec'),
    ('Иванов Иван Иванович',        'orenburg','$2y$10$Rb0INX8pUWosN9mXsn1zKOMKarR86AVE.UDkgcaL3w9ZXxncrLqB2', 'ivanov@example.ru','+7 (900) 000-00-03', 1, FALSE, 'user'),
    ('Петров Пётр Петрович',        'orsk',    '$2y$10$Rb0INX8pUWosN9mXsn1zKOMKarR86AVE.UDkgcaL3w9ZXxncrLqB2', 'petrov@example.ru','+7 (900) 000-00-04', 2, FALSE, 'user'),
    ('Сидорова Анна Сергеевна',     'buzuluk', '$2y$10$Rb0INX8pUWosN9mXsn1zKOMKarR86AVE.UDkgcaL3w9ZXxncrLqB2', 'sidorova@example.ru','+7 (900) 000-00-05', 4, FALSE, 'user')
ON CONFLICT (user_login) DO NOTHING;

-- table_templates ------------------------------------------------------------
INSERT INTO table_templates (template_name, template_headers, template_structure, is_active) VALUES
(
    'Отчёт по населению (2026 Q1)',
    '[
        {"name": "Показатель",          "type": "text",   "readonly": true},
        {"name": "Численность, чел.",   "type": "number", "readonly": false},
        {"name": "Прирост за год, %",   "type": "number", "readonly": false}
    ]'::jsonb,
    '{
        "rows": [
            {"rowType": "normal",  "cells": {"Показатель": "Городское население"}},
            {"rowType": "normal",  "cells": {"Показатель": "Сельское население"}},
            {"rowType": "normal",  "cells": {"Показатель": "Трудоспособное"}},
            {"rowType": "comment", "cells": {"Комментарий": ""}}
        ],
        "merges": []
    }'::jsonb,
    TRUE
),
(
    'Бюджет МО (архивный)',
    '[
        {"name": "Статья",        "type": "text",   "readonly": true},
        {"name": "План, тыс.₽",   "type": "number", "readonly": false},
        {"name": "Факт, тыс.₽",   "type": "number", "readonly": false}
    ]'::jsonb,
    '{"rows": [
        {"rowType": "normal", "cells": {"Статья": "Доходы"}},
        {"rowType": "normal", "cells": {"Статья": "Расходы"}}
    ], "merges": []}'::jsonb,
    FALSE
);

-- filled_data ----------------------------------------------------------------
-- template_id = 1 (активный), заполнения от разных МО
INSERT INTO filled_data (user_id, template_id, municipality_id, filled_data, filled_date) VALUES
    (3, 1, 1,
     '{"0": {"Показатель": "Городское население", "Численность, чел.": "567000", "Прирост за год, %": "0.4"},
       "1": {"Показатель": "Сельское население", "Численность, чел.": "12000",  "Прирост за год, %": "-1.2"},
       "2": {"Показатель": "Трудоспособное",     "Численность, чел.": "340000", "Прирост за год, %": "0.1"},
       "3": {"Комментарий": "Данные по переписи 2026."}}'::jsonb,
     NOW() - INTERVAL '10 days'),
    (4, 1, 2,
     '{"0": {"Показатель": "Городское население", "Численность, чел.": "218000", "Прирост за год, %": "-0.3"},
       "1": {"Показатель": "Сельское население", "Численность, чел.": "4500",   "Прирост за год, %": "-0.8"},
       "2": {"Показатель": "Трудоспособное",     "Численность, чел.": "128000", "Прирост за год, %": "-0.2"},
       "3": {"Комментарий": ""}}'::jsonb,
     NOW() - INTERVAL '5 days'),
    (5, 1, 4,
     '{"0": {"Показатель": "Городское население", "Численность, чел.": "83000"},
       "1": {"Показатель": "Сельское население", "Численность, чел.": "9100"},
       "2": {"Показатель": "Трудоспособное",     "Численность, чел.": "52000"},
       "3": {"Комментарий": "Черновик, уточняется."}}'::jsonb,
     NOW() - INTERVAL '1 day');

-- feedback_requests ----------------------------------------------------------
INSERT INTO feedback_requests (user_id, full_name_feedback, phone_feedback, problem_description_feedback) VALUES
    (3,    'Иванов И.И.',   '+7 (900) 000-00-03', 'Не могу открыть активный шаблон — виснет вкладка.'),
    (NULL, 'Гость',          '+7 (900) 111-22-33', 'Нужна инструкция по регистрации МО.'),
    (4,    'Петров П.П.',    '+7 (900) 000-00-04', 'В числовом поле не принимает запятую.');

-- Последовательности: после ручных вставок с авто-id данные уже корректны (SERIAL).
