<?php

/**
 * Контроллер формы обратной связи.
 */
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    JsonResponse::error(405, 'Метод не поддерживается.');
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

$guard = new SessionGuard();
$repo = new FeedbackRepository($conn);
$repo->create($guard->userId(), $fullName, $phone, $problem);

JsonResponse::success('Заявка успешно отправлена.');
