<?php

/**
 * Контроллер авторизации.
 * Проверяет CSRF, единое generic-сообщение, регенерирует session_id после успеха.
 */
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    JsonResponse::error(405, 'Метод не поддерживается.');
    exit;
}

Csrf::verifyOrFail();

$login = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($login === '' || $password === '') {
    JsonResponse::error(400, 'Введите логин и пароль.');
    exit;
}

$repo = new UserRepository($conn);
$user = $repo->findForLoginByLogin($login);

// Generic сообщение (одинаковое для «нет пользователя» и «неверный пароль»)
$generic = 'Неверный логин или пароль.';

if ($user === null || !password_verify($password, $user['user_password'])) {
    JsonResponse::error(401, $generic);
    exit;
}

// Предотвращаем session fixation
session_regenerate_id(true);

$_SESSION['user_id'] = $user['user_id'];
$_SESSION['user_full_name'] = $user['user_full_name'];
$_SESSION['role'] = $user['role'];
$_SESSION['municipality_id'] = $user['municipality_id'];
$_SESSION['municipality_name'] = $user['municipality_name'];

session_write_close();

$redirect = match ($user['role']) {
    'admin' => 'admin_view.php',
    'minec' => 'minec_view.php',
    default => 'index.php',
};

JsonResponse::success('Вы успешно вошли', ['redirect' => $redirect]);
