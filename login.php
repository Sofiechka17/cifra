<?php
session_start();
include "db.php";

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "message" => "Некорректный запрос"]);
    exit;
}

$login = trim($_POST["username"] ?? "");
$password = $_POST["password"] ?? "";

if (!$login || !$password) {
    echo json_encode(["success" => false, "message" => "Введите логин и пароль"]);
    exit;
}

// Поиск пользователя в БД
$query = "SELECT user_id, user_full_name, user_password, is_admin 
          FROM users WHERE user_login = $1 LIMIT 1";
$result = pg_query_params($conn, $query, [$login]);

if ($row = pg_fetch_assoc($result)) {
    if (password_verify($password, $row["user_password"])) {
        $_SESSION["user_id"] = $row["user_id"];
        $_SESSION["user_full_name"] = $row["user_full_name"];
        $_SESSION["is_admin"] = ($row["is_admin"] === 't' || $row["is_admin"] === true);

        session_write_close();

        $redirect = $_SESSION["is_admin"] ? "admin_view.php" : "index.php";

        echo json_encode([
            "success" => true,
            "message" => $_SESSION["is_admin"] ? "Вы вошли как админ" : "Вы вошли как пользователь",
            "redirect" => $redirect
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "Неверный пароль"]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Пользователь не найден"]);
}
?>
