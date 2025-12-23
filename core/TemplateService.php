<?php
require_once __DIR__ . '/Template.php';

/**
 * Фасад для работы с шаблонами таблиц и заполненными данными
 *
 * Снаружи система работает только с этим классом, код не лезет в бд напрямую
 *  - получить активный шаблон
 *  - сохранить шаблон
 *  - сделать шаблон активным
 *  - сохранить заполненную таблицу
 */
class TemplateService
{
    private \PgSql\Connection $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Загружает активный шаблон.
     * Если активный не найден — возвращает Template::createEmpty() , специальный "пустой" шаблон
     */
    public function getActiveTemplate(): Template
    {
        $sql = "SELECT template_id,
                       template_name,
                       template_headers,
                       template_structure,
                       is_active
                  FROM cit_schema.table_templates
                 WHERE is_active = TRUE
                 ORDER BY template_id DESC
                 LIMIT 1";

        $res = pg_query($this->conn, $sql);
        if (!$res || pg_num_rows($res) === 0) {
            return Template::createEmpty();
        }

        $row = pg_fetch_assoc($res);

        return new Template(
            (int)$row['template_id'],
            (string)$row['template_name'],
            json_decode($row['template_headers'] ?? '[]', true) ?? [],
            json_decode($row['template_structure'] ?? '{"rows":[]}', true) ?? ['rows' => []],
            (bool)$row['is_active']
        );
    }

    /**
     * Получает шаблон по его ID
     */
    public function getTemplateById(int $templateId): Template
    {
        $sql = "SELECT template_id,
                       template_name,
                       template_headers,
                       template_structure,
                       is_active
                  FROM cit_schema.table_templates
                 WHERE template_id = $1
                 LIMIT 1";

        $res = pg_query_params($this->conn, $sql, [$templateId]);
        if (!$res || pg_num_rows($res) === 0) {
            return Template::createEmpty();
        }

        $row = pg_fetch_assoc($res);

        return new Template(
            (int)$row['template_id'],
            (string)$row['template_name'],
            json_decode($row['template_headers'] ?? '[]', true) ?? [],
            json_decode($row['template_structure'] ?? '{"rows":[]}', true) ?? ['rows' => []],
            (bool)$row['is_active']
        );
    }

    /**
     * Сохраняет новый шаблон, созданный в конструкторе
     */
    public function createTemplate(string $name, array $headers, array $structure, bool $makeActive = false): int
    {
        $sql = "INSERT INTO cit_schema.table_templates (template_name, template_headers, template_structure, is_active)
                VALUES ($1, $2::jsonb, $3::jsonb, $4)
                RETURNING template_id";

        $res = pg_query_params($this->conn, $sql, [
            $name,
            json_encode($headers, JSON_UNESCAPED_UNICODE),
            json_encode($structure, JSON_UNESCAPED_UNICODE),
            $makeActive ? 't' : 'f',
        ]);

        if (!$res) {
            throw new RuntimeException('Ошибка создания шаблона: ' . pg_last_error($this->conn));
        }

        $row = pg_fetch_assoc($res);
        return (int)$row['template_id'];
    }

    /**
     * Делает указанный шаблон активным и все остальные деактивирует.
     * Вызывается, когда админ нажимает кнопку «Отправить».
     */
    public function setActiveTemplate(int $templateId): void
    {
        pg_query($this->conn, "BEGIN");

        try {
            // Сначала отключаем все активные шаблоны
            $sqlOff = "UPDATE cit_schema.table_templates SET is_active = FALSE WHERE is_active = TRUE";
            if (!pg_query($this->conn, $sqlOff)) {
                throw new RuntimeException(pg_last_error($this->conn));
            }

            // Затем включаем нужный
            $sqlOn  = "UPDATE cit_schema.table_templates SET is_active = TRUE WHERE template_id = $1";
            if (!pg_query_params($this->conn, $sqlOn, [$templateId])) {
                throw new RuntimeException(pg_last_error($this->conn));
            }

            pg_query($this->conn, "COMMIT");
        } catch (\Throwable $e) {
            pg_query($this->conn, "ROLLBACK");
            throw $e;
        }
    }

    /**
     * Сохраняет заполненную пользователем таблицу в JSON-формате
     */
    public function saveFilledData(int $userId, int $templateId, int $municipalityId, array $rows): void
    {
        $sql = "INSERT INTO cit_schema.filled_data (user_id, template_id, municipality_id, filled_data)
                VALUES ($1, $2, $3, $4::jsonb)";

        $json = json_encode($rows, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Не удалось сериализовать данные таблицы в JSON');
        }

        $res = pg_query_params($this->conn, $sql, [
            $userId,
            $templateId,
            $municipalityId,
            $json,
        ]);

        if (!$res) {
            throw new RuntimeException('Ошибка сохранения данных таблицы: ' . pg_last_error($this->conn));
        }
    }
}
