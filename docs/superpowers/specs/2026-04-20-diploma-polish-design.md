# Diploma Polish — Design Spec

**Дата:** 2026-04-20
**Автор:** Claude + пользователь
**Контекст:** Рефакторинг ИССД (PHP/PostgreSQL, ГКУ «ЦИТ Оренбургской области») для защиты дипломной работы.
**Срок:** ~1 неделя
**Подход:** «Архитектурный showcase» (Подход Б) — без CI/CD.

## Цель

Привести код к виду, который выдерживает:
- Защиту перед комиссией, которая смотрит слайды, запускает живую демку и читает код.
- Вопросы про ООП-паттерны и соответствие UML-диаграммам из пояснительной записки.
- Базовые вопросы про защиту данных (диплом работает с данными МО).

## Цель НЕ в этом

- Полный OWASP-compliance.
- 100% test coverage.
- Переписывание UI/фронта.
- CI/CD pipeline.
- Миграции БД.

---

## Scope

### Deliverables

1. Новая ООП-структура в `core/` (см. «Архитектура»).
2. Тонкие PHP-скрипты в корне (controllers), вся логика в `core/`.
3. Composer-autoload (classmap на `core/`).
4. `bootstrap.php` — единая точка инициализации для всех скриптов.
5. Security-минимум: CSRF, session regen, generic errors, secure cookie flags.
6. Архитектурные инварианты зафиксированы в коде (createTemplate atomicity, canBeUsedForFill check).
7. README.md с Mermaid-диаграммой и навигацией по паттернам.
8. Работающий `docker compose up -d` без регрессий (демка должна открываться, логин/регистрация/заполнение/экспорт должны работать).

### Out of scope

- Рефакторинг фронтенда (`styles.css`, `constructor.js`, `charts.js`, inline JS в `get_table.php`/`admin_view.php`).
- Изменения схемы БД.
- Унит-тесты (дефолтно не делаем; если останется время на день 7 — 1-2 показательных).
- CI/CD.
- Публичный релиз (остаётся как учебный проект).

---

## Архитектура

### Новая структура `core/`

```
core/
  Auth/
    Csrf.php                      — генерация и проверка CSRF-токена (session-based)
    SessionGuard.php              — OOP-обёртка над require_auth/admin/minec
  Http/
    JsonResponse.php              — единый формат ответа {success, message, data?}
    ErrorHandler.php              — глобальный перехват Throwable, лог в error_log, generic клиенту
  Repository/
    AdminViewRepository.php       — вынесенный дубликат из admin_view.php и minec_view.php
    UserRepository.php            — запросы по users (findByLogin, create, findForSession)
    FeedbackRepository.php        — insert в feedback_requests
  Template/
    Template.php                  — сущность (без изменений по API, но Template::createEmpty → Template::notFound)
    TemplateService.php           — facade; createTemplate(makeActive) теперь атомарен; saveFilledData проверяет canBeUsedForFill
    TemplateState.php             — без изменений
  Export/
    ExcelFormulaGuard.php         — защита от formula injection для setCellValue
    FilledDataExcelExporter.php   — вынесенный export_excel.php
    FeedbackExcelExporter.php     — вынесенный export_feedback_excel.php
```

### Тонкие скрипты в корне

Каждый PHP-файл — «точка входа», 10-30 строк: bootstrap → инстанцирование Controller → `handle()`.

Пример (save_template.php):

```php
<?php
require_once __DIR__ . '/bootstrap.php';

(new SaveTemplateController(
    new TemplateService($conn),
    new TemplatePayloadValidator(),
    new Csrf()
))->handle();
```

### bootstrap.php (новый файл)

```php
<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/core/Http/ErrorHandler.php';
ErrorHandler::register();

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => false, // TODO: на проде true
]);

require __DIR__ . '/auth.php';
ensure_session_started();

require __DIR__ . '/db.php';
```

### Autoload

`composer.json` получает секцию:

```json
"autoload": {
    "classmap": ["core/"]
}
```

После `composer dump-autoload` все `require_once __DIR__ . '/core/...'` из PHP-скриптов удаляются.

### Что сохраняется (не ломаем)

- Стек: PHP 8.2 + PostgreSQL 16 + `pg_query_params` (не PDO).
- Схема БД и все имена таблиц/полей.
- Docker-окружение (docker-compose.yml, Dockerfile, initdb/).
- Фронтенд (`styles.css`, JS-файлы, inline JS в views).
- `auth.php` — функциональный API остаётся (`require_auth` и т.д.), его внутрь подключим к SessionGuard, но старые функции не ломаем.

---

## Security минимум

| Защита | Как реализовано |
|---|---|
| **CSRF** | `Csrf::generate()` при первом обращении хранит токен в `$_SESSION['_csrf']`. Все POST-формы получают `<input type="hidden" name="_csrf">`, JSON-запросы — заголовок `X-CSRF-Token`. Controllers вызывают `Csrf::verify($postOrHeader)` до любой мутации данных. Mismatch → HTTP 403 + generic. |
| **Session fixation** | `LoginController` после успешного `password_verify` вызывает `session_regenerate_id(true)` до записи user_id в сессию. |
| **Cookie flags** | `session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax'])` в `bootstrap.php` ДО `ensure_session_started()`. `secure=false` для dev, комментарий про прод. |
| **Generic auth errors** | `LoginController` возвращает единое сообщение «Неверный логин или пароль» для обеих веток (пользователь не найден / неверный пароль). |
| **Generic server errors** | `ErrorHandler` ловит `Throwable`, пишет `error_log($e)`, клиенту — `{success:false, message:"Внутренняя ошибка сервера"}`. В dev-режиме (ENV `APP_DEBUG=1`) можно отдавать полный текст. |
| **Close public endpoint** | `get_municipalities.php` получает `require_auth()`, переезжает на `include bootstrap.php` (удалит дубликат кредов), начинает отдавать JSON. |
| **Excel formula injection** | `ExcelFormulaGuard::sanitize($value)` — если `$value` начинается с `= + - @ \t \r`, префиксуем `'`. Все `setCellValue` в экспортёрах идут через `sanitize()`. |

### Не делаем (в зоне роста)

Эти пункты упоминаются в разделе «Безопасность» README как планируемые улучшения — защитная тема для доклада:
- Rate-limit на login (счётчик в сессии/таблице).
- 2FA.
- CSP / X-Frame-Options / X-Content-Type-Options заголовки.
- HSTS.
- Password policy (min length/complexity).
- Email verification на регистрации.
- Вынос `db.php` creds в env с обязательной валидацией (сейчас есть fallback — оставляем для простоты dev).

---

## Архитектурные инварианты

Три логических бага из ревью, которые исправляем:

### 1. Атомарная активация в createTemplate

Сейчас `TemplateService::createTemplate($makeActive=true)` делает INSERT с `is_active=true`, но не снимает флаг с других → возможно несколько активных одновременно.

**Фикс:** переиспользовать логику `setActiveTemplate` внутри транзакции:

```php
public function createTemplate(string $name, array $headers, array $structure, bool $makeActive = false): int
{
    pg_query($this->conn, 'BEGIN');
    try {
        if ($makeActive) {
            pg_query($this->conn, "UPDATE cit_schema.table_templates SET is_active=false WHERE is_active=true");
        }
        // ... INSERT с is_active=$makeActive ...
        pg_query($this->conn, 'COMMIT');
        return $newId;
    } catch (Throwable $e) {
        pg_query($this->conn, 'ROLLBACK');
        throw $e;
    }
}
```

### 2. Проверка canBeUsedForFill в saveFilledData

Сейчас пользователь МО может отправить данные в неактивный шаблон, если знает его id.

**Фикс:**

```php
public function saveFilledData(int $userId, int $templateId, int $municipalityId, array $rows): void
{
    $template = $this->getTemplateById($templateId);
    if (!$template->canBeUsedForFill()) {
        throw new DomainException('Шаблон недоступен для заполнения.');
    }
    // ... INSERT ...
}
```

SaveTableController ловит `DomainException` → HTTP 400 + generic message.

### 3. Переименование createEmpty → notFound

`Template::createEmpty()` → `Template::notFound()`. Старое имя не передаёт семантику. Это Null-object паттерн, и новое имя это подчёркивает.

---

## Документация (README.md)

Структура:

1. **Заголовок + краткое описание.** Одно предложение + статус «учебный проект, дипломная работа».
2. **Стек технологий.** Таблица: PHP 8.2, PostgreSQL 16, PhpSpreadsheet, vanilla JS, Yandex Maps.
3. **Быстрый старт.**
   ```bash
   docker compose up -d --build
   # http://localhost:8000
   ```
   Демо-логины: `admin/admin`, `minec/admin`, `orenburg/admin`.
4. **Архитектура.** Mermaid-диаграмма компонентов:
   ```mermaid
   graph TB
     Browser -->|HTTP + CSRF| Controllers[Thin PHP Controllers]
     Controllers --> Core[core/]
     Core --> Auth[Auth: Csrf, SessionGuard]
     Core --> Http[Http: JsonResponse, ErrorHandler]
     Core --> Repos[Repository: User, Feedback, AdminView]
     Core --> Tmpl[Template: Service, State, Entity]
     Core --> Export[Export: ExcelExporters + FormulaGuard]
     Repos --> DB[(PostgreSQL)]
     Tmpl --> DB
   ```
5. **Навигация по паттернам.** Список с ссылками на конкретные файлы:
   - Facade → `core/Template/TemplateService.php`
   - State → `core/Template/TemplateState.php`
   - Repository → `core/Repository/`
   - Null Object → `Template::notFound()`
   - Thin Controller → любой файл в корне
6. **Безопасность.** Две подсекции: «Что закрыто» и «Зона роста» (из таблиц выше).
7. **Структура репозитория.** Дерево каталогов.
8. **База данных.** Ссылка на `docker/initdb/` + короткое описание ключевых таблиц.

---

## Порядок работы

### Изоляция

Работа идёт в git-worktree (согласно глобальному CLAUDE.md):

```bash
git worktree add .worktrees/refactor-diploma-polish -b refactor/diploma-polish
```

Предварительно — проверить, что `.worktrees/` в `.gitignore` (добавить если нет).

### Roadmap на 7 дней

| День | Задачи | Выход |
|---|---|---|
| 1 | Worktree, composer autoload, `bootstrap.php`, `Csrf`, `ErrorHandler`, `JsonResponse` | Инфраструктура готова |
| 2 | `UserRepository`, `FeedbackRepository`, `AdminViewRepository`; `LoginController`, `RegisterController`, `SubmitFormController`, `LogoutController` | Аутентификация и feedback на новом стеке |
| 3 | `SaveTableController`, `SaveTemplateController`; инварианты в `TemplateService`; `Template::notFound()` | Основной бизнес-флоу на новом стеке |
| 4 | `ExcelFormulaGuard`, `FilledDataExcelExporter`, `FeedbackExcelExporter`; `GetMunicipalitiesController` (с auth + JSON) | Экспорт и закрытый endpoint |
| 5 | Consistency pass: все `session_start` → `ensure_session_started`, все `include` → `require_once`, убрать дубликаты | Код единообразен |
| 6 | README + Mermaid; ручная проверка всех флоу через браузер (E2E-сценарий по глобальному правилу) | Документация и зелёная приёмка |
| 7 | Буфер: правки после приёмки, 1-2 показательных юнит-теста (если время позволит) | Финальный вид |

### Валидация

Каждый день после изменений — полный ручной проход сценариев через `docker compose` + браузер:
1. Гость → регистрация → логин → личный кабинет.
2. Админ → открыть конструктор → сохранить шаблон → активировать → выйти.
3. МО → залогиниться → заполнить таблицу → сохранить.
4. Минэк → просмотреть заполненные → выгрузить Excel.
5. Главная → отправить форму обратной связи.

E2E-сценарий сохраняется в `swarm-report/diploma-polish-e2e-scenario.md` (по глобальному правилу устойчивости к компактизации).

---

## Риски

| Риск | Митигация |
|---|---|
| Рефакторинг ломает флоу, замечаем на защите | Ежедневная ручная валидация всех 5 сценариев + работа в worktree (main всегда рабочий) |
| Не успеваем за неделю | Приоритет сверху вниз: security + автолоад + инварианты — критично; полный вынос в Repository — можно частично; 1-2 скрипта оставить old-style если прижмёт |
| Комиссия копает именно OWASP-моменты, которые в «зоне роста» | В README и докладе явно помечены как «roadmap» — показывает, что студент знает об этом |
| Поломка docker-окружения | Не трогаем Dockerfile/compose, только добавляем `composer dump-autoload` шаг в контейнер при первом запуске |

---

## Критерий приёмки

К концу дня 6 должно выполняться:

- [ ] `docker compose up -d --build` поднимает всё без ошибок.
- [ ] Все 5 пользовательских сценариев работают в браузере.
- [ ] Папка `core/` содержит минимум 10 классов в организованной структуре.
- [ ] В PHP-скриптах в корне нет бизнес-логики (только Controller wiring).
- [ ] README.md открывается на GitHub, Mermaid-диаграмма рендерится.
- [ ] `grep -rn "session_start()" *.php` возвращает только `auth.php` (внутри `ensure_session_started`).
- [ ] Все формы имеют CSRF-токен, все POST-контроллеры его проверяют.
- [ ] На login с неверными кредами — одно generic сообщение.
- [ ] `get_municipalities.php` требует auth и возвращает JSON.
- [ ] В `setCellValue` экспортёров нет путей в обход `ExcelFormulaGuard`.
