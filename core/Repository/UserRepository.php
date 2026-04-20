<?php

/**
 * Репозиторий доступа к таблице users.
 */
final class UserRepository
{
    /** @var \PgSql\Connection */
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * @return array{user_id:int,user_full_name:string,user_password:string,role:string,municipality_id:int,municipality_name:string}|null
     */
    public function findForLoginByLogin(string $login): ?array
    {
        $sql = "
            SELECT u.user_id, u.user_full_name, u.user_password, u.role,
                   u.municipality_id, m.municipality_name
              FROM cit_schema.users u
              JOIN cit_schema.municipalities m ON m.municipality_id = u.municipality_id
             WHERE u.user_login = $1
             LIMIT 1
        ";
        $res = pg_query_params($this->conn, $sql, [$login]);
        if (!$res || !($row = pg_fetch_assoc($res))) {
            return null;
        }
        return [
            'user_id' => (int)$row['user_id'],
            'user_full_name' => (string)$row['user_full_name'],
            'user_password' => (string)$row['user_password'],
            'role' => (string)($row['role'] ?? 'user'),
            'municipality_id' => (int)$row['municipality_id'],
            'municipality_name' => (string)$row['municipality_name'],
        ];
    }

    public function existsByLoginOrEmail(string $login, string $email): bool
    {
        $sql = "SELECT 1 FROM cit_schema.users WHERE user_login = $1 OR user_email = $2 LIMIT 1";
        $res = pg_query_params($this->conn, $sql, [$login, $email]);
        return $res !== false && pg_num_rows($res) > 0;
    }

    public function create(
        string $fullName,
        string $login,
        string $passwordHash,
        string $email,
        string $phone,
        int $municipalityId
    ): void {
        $sql = "
            INSERT INTO cit_schema.users
                (user_full_name, user_login, user_password, user_email, user_phone, municipality_id, is_admin)
            VALUES ($1, $2, $3, $4, $5, $6, false)
        ";
        $res = pg_query_params($this->conn, $sql, [$fullName, $login, $passwordHash, $email, $phone, $municipalityId]);
        if (!$res) {
            throw new RuntimeException('Не удалось создать пользователя.');
        }
    }

    public function findMunicipalityIdByUserId(int $userId): ?int
    {
        $sql = "SELECT municipality_id FROM cit_schema.users WHERE user_id = $1 LIMIT 1";
        $res = pg_query_params($this->conn, $sql, [$userId]);
        if (!$res || pg_num_rows($res) === 0) {
            return null;
        }
        $row = pg_fetch_assoc($res);
        return isset($row['municipality_id']) ? (int)$row['municipality_id'] : null;
    }
}
