<?php
/**
 * Сохранение заполненной пользователем таблицы
 * Принимает POST: template_id, table_data (JSON с данными по ячейкам).
 */
session_start();
include "db.php";

if (!isset($_SESSION["user_id"])) {
    die("Необходима авторизация");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $userId = $_SESSION["user_id"];

    // Достаём МО пользователя
    $queryUser = "SELECT m.municipality_name 
                  FROM users u 
                  JOIN municipalities m ON u.municipality_id = m.municipality_id 
                  WHERE u.user_id = $1";
    $resUser = pg_query_params($conn, $queryUser, [$userId]);
    $rowUser = pg_fetch_assoc($resUser);
    $municipalityName = $rowUser["municipality_name"];

    $templateId = intval($_POST["template_id"]);
    $data = $_POST["table_data"]; // JSON из JS

    /**
     * Записываем JSON-данные в filled_data
     * municipality_id подставляется по текущему пользователю.
     */
    $query = "INSERT INTO filled_data (user_id, template_id, municipality_id, filled_data) 
              VALUES ($1, $2, (SELECT municipality_id FROM users WHERE user_id=$1), $3)";
    $result = pg_query_params($conn, $query, [$userId, $templateId, json_encode($data, JSON_UNESCAPED_UNICODE)]);

    if ($result) {
        echo "Данные сохранены для МО: " . htmlspecialchars($municipalityName);
    } else {
        echo "Ошибка сохранения: " . pg_last_error($conn);
    }
}