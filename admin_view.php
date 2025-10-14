<?php
session_start();
include "db.php";

if (empty($_SESSION["is_admin"])) {
    die("Доступ запрещён");
}

// --- Заполненные таблицы ---
$filledResult = pg_query($conn, "
    SELECT f.filled_data_id, u.user_full_name, m.municipality_name, f.filled_date 
    FROM cit_schema.filled_data f
    JOIN cit_schema.users u ON f.user_id = u.user_id
    JOIN cit_schema.municipalities m ON f.municipality_id = m.municipality_id
    ORDER BY f.filled_date DESC
");

// --- Обратная связь ---
$feedbackResult = pg_query($conn, "
    SELECT fr.feedback_id, 
           fr.full_name_feedback, 
           fr.phone_feedback, 
           fr.problem_description_feedback
    FROM cit_schema.feedback_requests fr
    ORDER BY fr.feedback_id DESC
");

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Админ — заполненные таблицы</title>
    <style>
        body {
            background: #000;
            color: #fff;
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        h2 {
            color: #fff;
            margin-top: 30px;
            margin-bottom: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        th, td {
            border: 1px solid #555;
            padding: 8px;
            text-align: left;
        }
        th {
            background: #111;
            color: #fff;
        }
        .btn {
            background: #444;
            color: #fff;
            border: none;
            padding: 6px 12px;
            cursor: pointer;
            border-radius: 5px;
        }
        .btn:hover {
            background: #666;
        }
    </style>
</head>
<body>
    <h2>Заполненные таблицы</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>Пользователь</th>
            <th>МО</th>
            <th>Дата</th>
            <th>Действие</th>
        </tr>
        <?php while ($row = pg_fetch_assoc($filledResult)): ?>
            <tr>
                <td><?= $row["filled_data_id"] ?></td>
                <td><?= htmlspecialchars($row["user_full_name"]) ?></td>
                <td><?= htmlspecialchars($row["municipality_name"]) ?></td>
                <td><?= $row["filled_date"] ?></td>
                <td>
                    <form action="export_excel.php" method="get" style="margin:0;">
                        <input type="hidden" name="filled_id" value="<?= $row["filled_data_id"] ?>">
                        <button type="submit" class="btn">Выгрузить в Excel</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>

    <h2>Список обратной связи</h2>
<table>
    <tr>
        <th>ID</th>
        <th>ФИО</th>
        <th>Телефон</th>
        <th>Текст обращения</th>
    </tr>
    <?php while ($fb = pg_fetch_assoc($feedbackResult)): ?>
        <tr>
            <td><?= $fb["feedback_id"] ?></td>
            <td><?= htmlspecialchars($fb["full_name_feedback"]) ?></td>
            <td><?= htmlspecialchars($fb["phone_feedback"]) ?></td>
            <td><?= nl2br(htmlspecialchars($fb["problem_description_feedback"])) ?></td>
        </tr>
    <?php endwhile; ?>
</table>

<form action="export_feedback_excel.php" method="get" style="margin-top:15px;">
    <button type="submit" class="btn">Выгрузить все заявки в Excel</button>
</form>

</body>
</html>