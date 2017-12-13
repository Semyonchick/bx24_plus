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

use Mailchimp\Mailchimp;

require_once __DIR__ . '/../core/server.php';

// приводим к нужному форму пришедшие данные
$postJSON = file_get_contents('php://input');
$_POST = json_decode($postJSON, TRUE);

$result = [];

mail('semyonchick@gmail.com', 'chimp', print_r([$_REQUEST, $_POST], 1));

header('Content-Type: application/json');
echo json_encode([
    'result' => $result,
]);