<?php
/**
 * Обработчик формы обратной связи 
 * Проводит серверную валидацию и сохраняет запись в feedback_requests.
 */
session_start();
include "db.php";

// Устанавливаем кодировку соединения с PostgreSQL в UTF-8
pg_set_client_encoding($conn, "UTF8");

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false, "errors" => ["Неверный метод запроса"]]);
    exit;
}

// Получаем и очищаем данные из формы
$fullName = trim($_POST["full-name"] ?? '');
$phone = trim($_POST["phone"] ?? '');
$problem = trim($_POST["problem-description"] ?? '');

// Массив для ошибок валидации
$errors = [];

// Проверка ФИО
if (!preg_match("/^[А-ЯЁ][а-яё]+(\s[А-ЯЁ][а-яё]+)*$/u", $fullName)) {
    $errors[] = "ФИО должно содержать только буквы и начинаться с заглавной буквы";
}

// Проверка телефона
if (!preg_match("/^\+7\d{10}$/", $phone)) {
    $errors[] = "Телефон должен начинаться с +7 и содержать 11 цифр";
}

// Проверка текста обращения
if (empty($problem)) {
    $errors[] = "Текст обращения не может быть пустым";
}

// Если есть ошибки, возвращаем их
if (!empty($errors)) {
    echo json_encode(["success" => false, "errors" => $errors]);
    exit;
}

// ID пользователя (если авторизован)
$userId = $_SESSION["user_id"] ?? null;

// Запрос на вставку данных
$query = "
    INSERT INTO cit_schema.feedback_requests 
        (user_id, full_name_feedback, phone_feedback, problem_description_feedback) 
    VALUES ($1, $2, $3, $4)
";

$result = pg_query_params($conn, $query, [$userId, $fullName, $phone, $problem]);

if ($result) {
    echo json_encode(["success" => true, "message" => "Заявка успешно отправлена"]);
} else {
    echo json_encode([
        "success" => false, 
        "errors" => ["Ошибка при сохранении: " . pg_last_error($conn)]
    ]);
}
