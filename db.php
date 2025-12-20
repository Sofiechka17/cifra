<?php
/** 
 * Подключение к базе данных PostgreSQL
 */
$host = "localhost"; 
$port = "5432";
$dbname = "postgres"; 
$user = "postgres";   
$password = "postgres"; 

$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");

if (!$conn) {
    die("Ошибка подключения к базе данных: " . pg_last_error());
}

pg_query($conn, "SET search_path TO cit_schema");
pg_set_client_encoding($conn, "UTF8");
?>