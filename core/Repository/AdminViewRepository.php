<?php

/**
 * Репозиторий данных для admin/minec-страниц.
 */
final class AdminViewRepository
{
    /** @var \PgSql\Connection */
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /** @return array<int, array<string, mixed>> */
    public function getFilledTables(): array
    {
        $sql = "
            SELECT f.filled_data_id, f.template_id, f.filled_data,
                   u.user_full_name,
                   m.municipality_id, m.municipality_name,
                   t.template_name, f.filled_date
              FROM cit_schema.filled_data f
              JOIN cit_schema.users u ON f.user_id = u.user_id
              JOIN cit_schema.municipalities m ON f.municipality_id = m.municipality_id
              JOIN cit_schema.table_templates t ON t.template_id = f.template_id
             ORDER BY f.filled_date DESC
        ";
        $res = pg_query($this->conn, $sql);
        if (!$res) {
            return [];
        }
        $rows = [];
        while ($r = pg_fetch_assoc($res)) {
            $rows[] = $r;
        }
        return $rows;
    }

    /** @return array<int, array<string, string>> */
    public function getTemplatesList(): array
    {
        $sql = "SELECT template_id, template_name, is_active FROM cit_schema.table_templates ORDER BY template_id DESC";
        $res = pg_query($this->conn, $sql);
        if (!$res) {
            return [];
        }
        $list = [];
        while ($row = pg_fetch_assoc($res)) {
            $list[] = $row;
        }
        return $list;
    }

    /** @return array<int, array<string, string>> */
    public function getMunicipalitiesList(): array
    {
        $sql = "SELECT municipality_id, municipality_name FROM cit_schema.municipalities ORDER BY municipality_name";
        $res = pg_query($this->conn, $sql);
        if (!$res) {
            return [];
        }
        $list = [];
        while ($row = pg_fetch_assoc($res)) {
            $list[] = $row;
        }
        return $list;
    }
}
