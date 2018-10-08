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

use app\components\BX24;

require_once __DIR__ . '/../core/server.php';

// приводим к нужному форму пришедшие данные
$postJSON = file_get_contents('php://input');

$result = [];

$bx = new BX24(['url'=>$_GET['c']]);

$result += $_GET;

$users = [
    'semyonchick' => '9',
    'elokhov' => '56',
    'shigabuga' => '104',
];

$data = [
    'USER_ID' => $users[$_GET['u']],
    'REPORT' => 'from server api',
    'TIME' => str_replace('00:00', '05:00', date('c', strtotime($_GET['d']))),
];

{
    if ($_GET['ip'] && stripos($_GET['ip'], '192.168.1.') !== false) $_GET['ip'] = '78.159.225.99';
    $geo = \Yii::$app->cache->get('sypexgeo' . $_GET['ip']);
    if ($geo === false) {
        $geo = json_decode(file_get_contents('http://api.sypexgeo.net/json/' . $_GET['ip']), 1);
        if ($geo) \Yii::$app->cache->set('sypexgeo' . $_GET['ip'], $geo, 86400 * 7);
    }
    if ($geo) $data += [
        'LAT' => $geo['city']['lat'],
        'LON' => $geo['city']['lon'],
    ];
}

if (strpos($row[1], 'Logon') !== false) {
    $result = $this->get('timeman.open', $data+ ['IP_OPEN' => $_GET['ip']]);
}
if (strpos($row[1], 'Logoff') !== false) {
    $result = $this->get('timeman.close', $data + ['IP_CLOSE' => $_GET['ip']]);
}

header('Content-Type: application/json');
echo json_encode([
    'result' => $result,
]);