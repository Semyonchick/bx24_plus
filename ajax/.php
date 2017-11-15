<?php
/**
 * Created by PhpStorm.
 * User: semyonchick
 * Date: 06.10.2017
 * Time: 9:12
 *
 * Для получения персонализиварованых данных скрипта
 * getData();
 *
 * Для записи персонализиварованых данных скрипта
 * setData();
 */
require_once __DIR__ . '/../core/server.php';

$result = [];

// Код

header('Content-Type: application/json');
echo json_encode([
    'result' => $result,
]);