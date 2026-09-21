<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$dbHost = 'localhost';
$dbName = 'html_search';
$dbUser = 'root';
$dbPass = '';

try {
    $db = new mysqli(
        $dbHost,
        $dbUser,
        $dbPass,
        $dbName
    );

    $db->set_charset('utf8mb4');

} catch (mysqli_sql_exception $error) {
    http_response_code(500);
    exit('Ошибка подключения к базе данных');
}
