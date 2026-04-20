<?php

/**
 * Контроллер регистрации. CSRF-защищён, скрывает ошибки БД.
 */
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    JsonResponse::error(405, 'Метод не поддерживается.');
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
    JsonResponse::error(400, 'Все поля обязательны для заполнения.');
    exit;
}

if (!preg_match('/^[А-ЯЁ][а-яё]+(\s[А-ЯЁ][а-яё]+)*$/u', $fullName)) {
    JsonResponse::error(400, 'ФИО должно содержать только буквы и начинаться с заглавной буквы.');
    exit;
}

if (!preg_match('/^\+7\d{10}$/', $phone)) {
    JsonResponse::error(400, 'Телефон должен начинаться с +7 и содержать 11 цифр.');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    JsonResponse::error(400, 'Некорректный email.');
    exit;
}

$repo = new UserRepository($conn);

if ($repo->existsByLoginOrEmail($login, $email)) {
    JsonResponse::error(409, 'Пользователь с таким логином или email уже существует.');
    exit;
}

$repo->create(
    $fullName,
    $login,
    password_hash($password, PASSWORD_BCRYPT),
    $email,
    $phone,
    $municipalityId
);

JsonResponse::success('Регистрация успешна!');
