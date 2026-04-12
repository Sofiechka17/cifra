<?php
// login.php — исправленная версия
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Некорректный метод']);
    exit;
}

$login    = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($login) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Введите логин и пароль']);
    exit;
}

$query = "
    SELECT 
        u.user_id, 
        u.user_full_name, 
        u.user_password, 
        u.role,
        u.municipality_id, 
        m.municipality_name
    FROM cit_schema.users u
    JOIN cit_schema.municipalities m ON m.municipality_id = u.municipality_id
    WHERE u.user_login = $1 
    LIMIT 1
";

$res = pg_query_params($conn, $query, [$login]);

if (!$res || !($row = pg_fetch_assoc($res))) {
    echo json_encode(['success' => false, 'message' => 'Пользователь не найден']);
    exit;
}

if (!password_verify($password, $row['user_password'])) {
    echo json_encode(['success' => false, 'message' => 'Неверный пароль']);
    exit;
}

// Успешный вход
$_SESSION['user_id']          = (int)$row['user_id'];
$_SESSION['user_full_name']   = $row['user_full_name'];
$_SESSION['role']             = $row['role'] ?? 'user';
$_SESSION['municipality_id']  = (int)$row['municipality_id'];
$_SESSION['municipality_name']= $row['municipality_name'];

session_write_close();

$redirect = match($_SESSION['role'] ?? 'user') {
    'admin' => 'admin_view.php',
    'minec' => 'minec_view.php',
    default => 'index.php'
};

echo json_encode([
    'success'  => true,
    'message'  => 'Вы успешно вошли',
    'redirect' => $redirect
], JSON_UNESCAPED_UNICODE);