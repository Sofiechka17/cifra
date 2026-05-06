# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Информационная система сбора данных ГКУ «ЦИТ Оренбургской области». Веб-приложение на PHP + PostgreSQL: муниципальные образования (МО) заполняют табличные формы по шаблонам, которые конструирует администратор. Роль `minec` (Минэк) просматривает агрегированные данные.

Язык интерфейса и комментариев — русский. Код и идентификаторы — английские/смешанные.

## Stack & Commands

- PHP (классический LAMP-style, без фреймворка), Composer
- PostgreSQL, расширение `pgsql` (`pg_connect`, `pg_query_params`) — не PDO
- Зависимости: `phpoffice/phpspreadsheet` (экспорт в Excel)
- Frontend — ванильный JS, Яндекс.Карты API

```bash
composer install                         # установка зависимостей
php -S localhost:8000                    # локальный сервер разработки
```

Тестов в проекте нет. Линтера нет. Сборки нет — PHP-файлы редактируются «в бою».

## Database

- Подключение централизовано в `db.php` (хардкод `localhost:5432`, `postgres/postgres`, схема `cit_schema`).
- Все запросы идут через `$conn` из `db.php`; каждая точка входа делает `include "db.php"`.
- Ключевые таблицы: `municipalities`, `users` (+ роли), `table_templates` (JSONB `template_headers`, `template_structure`), `filled_data` (JSONB `filled_data`), плюс таблицы обратной связи.

## Architecture

### Точки входа (PHP-скрипты в корне)

Приложение — набор PHP-скриптов, каждый отвечает за отдельную страницу/эндпоинт. Общие паттерны:

- `index.php` — лендинг + модалки регистрации/авторизации + форма обратной связи.
- `register.php` / `login.php` / `logout.php` / `submit_form.php` — POST-обработчики форм.
- `get_table.php` — основной UI заполнения активного шаблона пользователем МО.
- `save_table.php` — приём заполненной таблицы (JSON).
- `admin_view.php` + `constructor.js` — админский конструктор шаблонов.
- `save_template.php` — сохранение/активация шаблона.
- `minec_view.php` — просмотр агрегированных данных для роли `minec`.
- `chart_data.php` + `charts.js` — данные и рендер графиков.
- `export_excel.php` / `export_feedback_excel.php` — выгрузка в XLSX через PhpSpreadsheet.
- `get_municipalities.php` — JSON-эндпоинт для списков МО.

### Auth (`auth.php`)

Единственный модуль авторизации. Все защищённые точки входа начинаются с `require_once 'auth.php'` и вызывают одну из функций-гардов:

- `require_auth()` — любой залогиненный пользователь; иначе редирект на `index.php`.
- `require_admin()` — роль `admin`; иначе HTTP 403.
- `require_minec()` — роль `minec`; иначе HTTP 403.
- Хелперы `current_user_id()`, `current_user_name()`, `current_municipality_name()`, `is_admin()`, `is_minec()` читают `$_SESSION`.

Роль пользователя хранится в `$_SESSION['role']` после логина.

### Слой шаблонов (`core/`) — единственный ООП-островок

Работа с шаблонами таблиц инкапсулирована тремя классами — это фасад между PHP-скриптами и БД, остальной код не должен напрямую делать SELECT по `table_templates` / `filled_data`.

- `core/TemplateService.php` — **фасад**. Методы: `getActiveTemplate()`, `getTemplateById($id)`, `createTemplate(...)`, `setActiveTemplate($id)` (транзакционно снимает флаг со всех остальных), `saveFilledData(...)`. Принимает `\PgSql\Connection` в конструкторе.
- `core/Template.php` — сущность шаблона. `template_headers` и `template_structure` хранятся как JSONB и декодируются в массивы. `Template::createEmpty()` возвращает псевдо-шаблон со стейтом `NoTemplateState`, чтобы вызывающий код не проверял на null.
- `core/TemplateState.php` — паттерн State: `ActiveTemplateState` / `InactiveTemplateState` / `NoTemplateState`. Метод `canBeUsedForFill()` решает, может ли МО заполнять шаблон. Подключается транзитивно через `Template.php`.

Подключение: `require_once __DIR__ . '/core/TemplateService.php';` — автолоад не настроен (у `composer.json` нет секции `autoload`, только `require`).

### Frontend

- `styles.css` — общие стили.
- `script.js` — лендинг (модалки, карта, форма обратной связи).
- `constructor.js` — конструктор шаблонов (мерджи ячеек, заголовки, строки) для админа.
- `charts.js` — графики на данных из `chart_data.php`.

## Conventions

- БД-запросы: **только** `pg_query_params` с плейсхолдерами `$1, $2, ...` — не склеивать SQL строками.
- JSON из PostgreSQL: `json_decode($row['...'] ?? '[]', true) ?? []` — двойной фолбэк на случай NULL/битого JSON.
- Экранирование вывода: `htmlspecialchars($val, ENT_QUOTES)`.
- Сессии: не вызывать `session_start()` напрямую в новых скриптах — использовать `ensure_session_started()` из `auth.php`.
- Новые операции с шаблонами/заполненными данными добавлять методом в `TemplateService`, а не ad-hoc запросами в страницах.

## Notes

- В репозитории лежат дубликаты `composer (копия ...).lock` — игнорировать, канонический — `composer.lock`.
- Креды БД захардкожены в `db.php`. Перед любым деплоем/публикацией — вынести в окружение.
