<?php
/** 
 * Подключение к базе данных PostgreSQL
 */
$host = getenv("DB_HOST") ?: "localhost";
$port = getenv("DB_PORT") ?: "5432";
$dbname = getenv("DB_NAME") ?: "postgres";
$user = getenv("DB_USER") ?: "postgres";
$password = getenv("DB_PASSWORD") ?: "postgres";

$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");

if (!$conn) {
    die("Ошибка подключения к базе данных: " . pg_last_error());
}

pg_query($conn, "SET search_path TO cit_schema");
pg_set_client_encoding($conn, "UTF8");
?>