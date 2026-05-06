<?php
/**
 * Контроллер авторизации.
 * Проверяет CSRF, единое generic-сообщение, регенерирует session_id после успеха.
 */
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Метод не поддерживается.'], JSON_UNESCAPED_UNICODE);
    exit;
}

Csrf::verifyOrFail();

$login = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($login === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Введите логин и пароль.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$sql = "
    SELECT u.user_id, u.user_full_name, u.user_password, u.role,
           u.municipality_id, m.municipality_name
      FROM cit_schema.users u
      JOIN cit_schema.municipalities m ON m.municipality_id = u.municipality_id
     WHERE u.user_login = $1
     LIMIT 1
";
$res = pg_query_params($conn, $sql, [$login]);
$user = ($res && pg_num_rows($res) > 0) ? pg_fetch_assoc($res) : null;

// Generic сообщение (одинаковое для «нет пользователя» и «неверный пароль»)
$generic = 'Неверный логин или пароль.';

if ($user === null || !password_verify($password, $user['user_password'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => $generic], JSON_UNESCAPED_UNICODE);
    exit;
}

// Предотвращаем session fixation
session_regenerate_id(true);

$_SESSION['user_id'] = (int)$user['user_id'];
$_SESSION['user_full_name'] = $user['user_full_name'];
$_SESSION['role'] = $user['role'];
$_SESSION['municipality_id'] = (int)$user['municipality_id'];
$_SESSION['municipality_name'] = $user['municipality_name'];

session_write_close();

$redirect = match ($user['role']) {
    'admin' => 'admin_view.php',
    'minec' => 'minec_view.php',
    default => 'index.php',
};

echo json_encode(['success' => true, 'message' => 'Вы успешно вошли', 'redirect' => $redirect], JSON_UNESCAPED_UNICODE);
