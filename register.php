<?php
/**
 * Обработчик регистрации нового пользователя
 * Принимает POST-данные формы и создаёт запись в таблице users.
 */
session_start();
include "db.php";

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fullName = trim($_POST["fullname"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $municipality = intval($_POST["municipality_id"] ?? 0);
    $login = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    // Проверка обязательных полей
    if (!$fullName || !$phone || !$email || !$municipality || !$login || !$password) {
        echo json_encode(["success" => false, "message" => "Все поля обязательны для заполнения"]);
        exit;
    }

    // Проверка уникальности логина и email
    $checkQuery = "SELECT user_id FROM users WHERE user_login = $1 OR user_email = $2";
    $checkResult = pg_query_params($conn, $checkQuery, [$login, $email]);
    if (pg_num_rows($checkResult) > 0) {
        echo json_encode(["success" => false, "message" => "Пользователь с таким логином или email уже существует"]);
        exit;
    }

    // Хэш пароля
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    // is_admin по умолчанию false
    $isAdmin = 'f';

    $query = "INSERT INTO users 
              (user_full_name, user_login, user_password, user_email, user_phone, municipality_id, is_admin) 
              VALUES ($1, $2, $3, $4, $5, $6, $7)";

    $result = pg_query_params($conn, $query, [
        $fullName, $login, $hashedPassword, $email, $phone, $municipality, $isAdmin
    ]);

    if ($result) {
        echo json_encode(["success" => true, "message" => "Регистрация успешна!"]);
    } else {
        echo json_encode(["success" => false, "message" => "Ошибка: " . pg_last_error($conn)]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Неверный метод запроса"]);
}
?>
