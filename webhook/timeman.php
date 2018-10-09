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

mail('semyonchick@gmail.com', 'WorkTrack', print_r([$_GET], 1));

require_once __DIR__ . '/../core/server.php';

// приводим к нужному форму пришедшие данные
$postJSON = file_get_contents('php://input');

$result = [];

$bx = new BX24(['url' => Config::$timeman[$_GET['c']]]);

$users = [
    'semyonchick' => '9',
    'elokhov' => '56',
    'shigabuga' => '104',
];
$user = $users[$_GET['u']];

if(!$user)
    mail('semyonchick@gmail.com', 'WorkTrack Error', print_r([$_GET], 1));

$data = [
    'USER_ID' => $user,
    'REPORT' => 'from server api',
//    'TIME' => str_replace('00:00', '05:00', date('c', strtotime($_GET['d']))),
];

if ($_GET['ip']) {
    if (stripos($_GET['ip'], '192.168.1.') !== false) $_GET['ip'] = '78.159.225.99';
    $geo = \Yii::$app->cache->get('sypexgeo' . $_GET['ip']);
    if ($geo === false) {
        $geo = json_decode(file_get_contents('http://api.sypexgeo.net/json/' . $_GET['ip']), 1);
        if ($geo) \Yii::$app->cache->set('sypexgeo' . $_GET['ip'], $geo, 86400 * 7);
    }
    if ($geo) $data += [
        'LAT' => $geo['city']['lat'],
        'LON' => $geo['city']['lon'],
    ];
    $data['IP_OPEN'] = $_GET['ip'];
}

try {
    if ($_GET['s'] == 'logon') {
        $result = $bx->run('timeman.open', $data);
    }
    if ($_GET['s'] == 'logoff') {
        $result = $bx->run('timeman.pause', $data);
    }
} catch (Exception $e){
    mail('semyonchick@gmail.com', 'WorkTrack Error', print_r([$_GET, $data, $result, $e->getMessage()], 1));
}

mail('semyonchick@gmail.com', 'WorkTrack', print_r([$_GET, $data, $result], 1));

header('Content-Type: application/json');
echo json_encode([
    'result' => $result,
]);