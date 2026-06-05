<?php
/**
 * Контроллер регистрации. CSRF-защищён, скрывает ошибки БД.
 */
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Метод не поддерживается.'], JSON_UNESCAPED_UNICODE);
    exit;
}

Csrf::verifyOrFail();

$fullName = trim($_POST['fullname'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$municipalityId = (int)($_POST['municipality_id'] ?? 0);
$login = trim($_POST['username'] ?? '');
$password = (string)($_POST['password'] ?? '');

if ($fullName === '' || $phone === '' || $email === '' || $municipalityId <= 0 || $login === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Все поля обязательны для заполнения.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!preg_match('/^[А-ЯЁ][а-яё]+(\s[А-ЯЁ][а-яё]+)*$/u', $fullName)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ФИО должно содержать только буквы и начинаться с заглавной буквы.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!preg_match('/^\+7\d{10}$/', $phone)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Телефон должен начинаться с +7 и содержать 11 цифр.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Некорректный email.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$check = pg_query_params($conn, "SELECT 1 FROM cit_schema.users WHERE user_login = $1 OR user_email = $2 LIMIT 1", [$login, $email]);
if ($check && pg_num_rows($check) > 0) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Пользователь с таким логином или email уже существует.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$insert = pg_query_params(
    $conn,
    "INSERT INTO cit_schema.users (user_full_name, user_login, user_password, user_email, user_phone, municipality_id)
     VALUES ($1, $2, $3, $4, $5, $6)",
    [$fullName, $login, password_hash($password, PASSWORD_BCRYPT), $email, $phone, $municipalityId]
);

if (!$insert) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Не удалось создать пользователя.'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Регистрация успешна!'], JSON_UNESCAPED_UNICODE);
