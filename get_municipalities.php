<?php
/**
 * Возвращает список МО для выпадающего списка 
 */
$conn = pg_connect("host=localhost port=5432 dbname=postgres user=postgres password=postgres");
if (!$conn) { die("Ошибка подключения к базе данных."); }

$result = pg_query($conn, "SELECT municipality_id, municipality_name FROM cit_schema.municipalities ORDER BY municipality_name");
if ($result) {
    while ($row = pg_fetch_assoc($result)) {
        echo "<option value='" . htmlspecialchars($row['municipality_id'], ENT_QUOTES) . "'>" . htmlspecialchars($row['municipality_name'], ENT_QUOTES) . "</option>";
    }
} else {
    echo "<option disabled>Ошибка загрузки данных</option>";
}

pg_close($conn);
?>