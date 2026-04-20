<?php

/**
 * Репозиторий для feedback_requests.
 */
final class FeedbackRepository
{
    /** @var \PgSql\Connection */
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function create(?int $userId, string $fullName, string $phone, string $problem): void
    {
        $sql = "
            INSERT INTO cit_schema.feedback_requests
                (user_id, full_name_feedback, phone_feedback, problem_description_feedback)
            VALUES ($1, $2, $3, $4)
        ";
        $res = pg_query_params($this->conn, $sql, [$userId, $fullName, $phone, $problem]);
        if (!$res) {
            throw new RuntimeException('Не удалось сохранить заявку.');
        }
    }

    /** @return array<int, array<string, string>> */
    public function listAll(): array
    {
        $sql = "
            SELECT feedback_id, full_name_feedback, phone_feedback, problem_description_feedback
              FROM cit_schema.feedback_requests
             ORDER BY feedback_id DESC
        ";
        $res = pg_query($this->conn, $sql);
        if (!$res) {
            return [];
        }
        $rows = [];
        while ($row = pg_fetch_assoc($res)) {
            $rows[] = $row;
        }
        return $rows;
    }
}
