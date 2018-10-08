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

// приводим к нужному форму пришедшие данные
$postJSON = file_get_contents('php://input');

$result = [];

//mail('semyonchick@gmail.com', 'mailChimp', print_r([$_REQUEST, $_POST, $postJSON], 1));

header('Content-Type: application/json');
echo json_encode([
    'result' => $result,
]);