<?php
/**
 * Выход пользователя из системы
 * Очищает сессию и перенаправляет на главную страницу.
 */
session_start();
session_destroy();
header("Location: index.php");
exit;
?>