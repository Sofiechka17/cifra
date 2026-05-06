<?php
/**
 * Контроллер формы обратной связи.
 */
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Метод не поддерживается.'], JSON_UNESCAPED_UNICODE);
    exit;
}

Csrf::verifyOrFail();

$fullName = trim($_POST['full-name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$problem = trim($_POST['problem-description'] ?? '');

$errors = [];

if (!preg_match('/^[А-ЯЁ][а-яё]+(\s[А-ЯЁ][а-яё]+)*$/u', $fullName)) {
    $errors[] = 'ФИО должно содержать только буквы и начинаться с заглавной буквы.';
}
if (!preg_match('/^\+7\d{10}$/', $phone)) {
    $errors[] = 'Телефон должен начинаться с +7 и содержать 11 цифр.';
}
if ($problem === '') {
    $errors[] = 'Текст обращения не может быть пустым.';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => $errors], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = current_user_id();
$res = pg_query_params(
    $conn,
    "INSERT INTO cit_schema.feedback_requests (user_id, full_name_feedback, phone_feedback, problem_description_feedback)
     VALUES ($1, $2, $3, $4)",
    [$userId, $fullName, $phone, $problem]
);

if (!$res) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Не удалось сохранить заявку.'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Заявка успешно отправлена.'], JSON_UNESCAPED_UNICODE);
