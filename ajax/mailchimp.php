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

$result = [];

$mc = new Mailchimp('544989de47041bba5b5552e0310edac4-us14');
$result = $mc->request('lists', [
    'fields' => 'lists.id,lists.name,lists.stats.member_count',
//    'offset' => 10,
    'count' => 50
]);


header('Content-Type: application/json');
echo json_encode([
    'result' => $result,
]);