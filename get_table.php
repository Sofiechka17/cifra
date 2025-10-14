<?php
session_start();
include "db.php";

header('Content-Type: application/json');

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["error" => "Необходима авторизация"]);
    exit;
}

$userId = $_SESSION["user_id"];

// Муниципальное образование пользователя
$query = "
    SELECT u.municipality_id, m.municipality_name
    FROM users u
    JOIN municipalities m ON u.municipality_id = m.municipality_id
    WHERE u.user_id = $1
    LIMIT 1
";
$result = pg_query_params($conn, $query, [$userId]);
$row = pg_fetch_assoc($result);

if (!$row) {
    echo json_encode(["error" => "Данные не найдены"]);
    exit;
}

// Шапка таблицы
$headers = [
    ["name" => "Показатели", "type" => "text", "readonly" => true],
    ["name" => "Единица измерения", "type" => "text", "readonly" => true],
    ["name" => "2022", "type" => "number", "readonly" => false],
    ["name" => "2023", "type" => "number", "readonly" => false],
    ["name" => "2024", "type" => "number", "readonly" => false],
    ["name" => "2025", "type" => "number", "readonly" => false],
    ["name" => "2026_консервативный", "type" => "number", "readonly" => false],
    ["name" => "2026_базовый", "type" => "number", "readonly" => false],
    ["name" => "2027_консервативный", "type" => "number", "readonly" => false],
    ["name" => "2027_базовый", "type" => "number", "readonly" => false],
    ["name" => "2028_консервативный", "type" => "number", "readonly" => false],
    ["name" => "2028_базовый", "type" => "number", "readonly" => false]
];

// Строки таблицы с пустыми ячейками для ввода данных
$rows = [
    ["Показатели"=>"Численность населения (в среднегодовом исчислении)","Единица измерения"=>"тыс. чел."],
    ["Показатели"=>"Численность населения старше трудоспособного возраста (на 1 января года)","Единица измерения"=>"тыс. чел."],
    ["Показатели"=>"Общий коэффициент рождаемости","Единица измерения"=>"на 1000 чел."],
    ["Показатели"=>"Общий коэффициент смертности","Единица измерения"=>"на 1000 чел."],
    ["Показатели"=>"Коэффициент естественного прироста населения","Единица измерения"=>"на 1000 чел."],
    ["Показатели"=>"Миграционный прирост (убыль)","Единица измерения"=>"тыс. чел."]
];

// Пустые значения для всех годов
foreach ($rows as &$r) {
    for ($i = 2; $i < count($headers); $i++) {
        $r[$headers[$i]["name"]] = "";
    }
}

echo json_encode([
    "municipality_name" => $row["municipality_name"],
    "template_id" => 1, 
    "headers" => $headers,
    "rows" => $rows
], JSON_UNESCAPED_UNICODE);