<?php
/**
 * Обработчик авторизации пользователя.
 * Принимает POST (username, password), проверяет в БД и возвращает JSON-ответ.
 */
session_start();
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Некорректный запрос"], JSON_UNESCAPED_UNICODE);
    exit;
}

$login    = trim($_POST["username"] ?? "");
$password = $_POST["password"] ?? "";

if ($login === "" || $password === "") {
    echo json_encode(["success" => false, "message" => "Введите логин и пароль"], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Ищем пользователя в БД вместе с его муниципалитетом
 * users.municipality_id и municipalities.municipality_name
 */
$query = "
    SELECT 
        u.user_id,
        u.user_full_name,
        u.user_password,
        u.is_admin,
        u.municipality_id,
        m.municipality_name
    FROM cit_schema.users u
    JOIN cit_schema.municipalities m ON m.municipality_id = u.municipality_id
    WHERE u.user_login = $1
    LIMIT 1
";
$result = pg_query_params($conn, $query, [$login]);

if ($row = pg_fetch_assoc($result)) {
    // Проверяем пароль по хэшу
    if (password_verify($password, $row["user_password"])) {

        // Записываем всё нужное в сессию
        $_SESSION["user_id"]            = $row["user_id"];
        $_SESSION["user_full_name"]    = $row["user_full_name"];
        $_SESSION["is_admin"]          = ($row["is_admin"] === 't' || $row["is_admin"] === true);
        $_SESSION["municipality_id"]   = $row["municipality_id"];
        $_SESSION["municipality_name"] = $row["municipality_name"];

        session_write_close();

        // Куда перенаправлять после входа
        $redirect = $_SESSION["is_admin"] ? "admin_view.php" : "index.php";

        echo json_encode([
            "success"  => true,
            "message"  => $_SESSION["is_admin"]
                ? "Вы вошли как администратор"
                : "Вы вошли как пользователь",
            "redirect" => $redirect,
        ], JSON_UNESCAPED_UNICODE);

    } else {
        echo json_encode(["success" => false, "message" => "Неверный пароль"], JSON_UNESCAPED_UNICODE);
    }
} else {
    echo json_encode(["success" => false, "message" => "Пользователь не найден"], JSON_UNESCAPED_UNICODE);
}