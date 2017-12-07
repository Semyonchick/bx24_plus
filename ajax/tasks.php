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

$result = [];
require_once __DIR__ . '/../core/commands/MegaplanController.php';

$bxEspanaRusa = new BX24(['url' => 'https://espanarusa.bitrix24.ru/rest/49/n7o1e5qd6w4ajolu/']);
$bxReRe = new BX24(['url' => 'https://rere.bitrix24.ru/rest/9/hd7o331e53m7dgne/']);

$usersEspanaRusa = [];
foreach ($bxEspanaRusa->run('user.get') as $user)
    $usersEspanaRusa[$user['ID']] = [$user['EMAIL'], trim("{$user['LAST_NAME']} {$user['NAME']} {$user['SECOND_NAME']}")];
$usersReRe = [];
foreach ($bxReRe->run('user.get', ['ADMIN_MODE'=>'Y']) as $user) {
    $usersReRe[$user['EMAIL']] = $user['ID'];
    $usersReRe[trim("{$user['LAST_NAME']} {$user['NAME']} {$user['SECOND_NAME']}")] = $user['ID'];
}

$data = $bxEspanaRusa->run('task.item.list', [['CHANGED_DATE' => 'desc'], ['RESPONSIBLE_ID' => 49]]);
foreach ($data as $row) {
    $row['GROUP_ID'] = 45;

    $tasks = $bxReRe->run('task.item.list', [['ID' => 'asc'], ['GROUP_ID' => $row['GROUP_ID'], 'TITLE' => $row['TITLE']]]);
    if (empty($tasks)) {
        $add = [];
        $add['UF_AUTO_472428192293'] = $row['ID'];
        $add['STATUS'] = $row['REAL_STATUS'];
        $add['DESCRIPTION'] = $row['DESCRIPTION'] . PHP_EOL . PHP_EOL . "https://espanarusa.bitrix24.ru/company/personal/user/{$row['RESPONSIBLE_ID']}/tasks/task/view/{$row['ID']}/";

        $user = $usersEspanaRusa[$row['CREATED_BY']];
        $add['CREATED_BY'] = $usersReRe[$user[0]] ?: $usersReRe[$user[1]];
        if (!$add['CREATED_BY']) {
            $add['CREATED_BY'] = $bxReRe->run('user.add', [], [
                'EMAIL' => $user[0],
                'EXTRANET' => 'Y',
                'SONET_GROUP_ID' => [$row['GROUP_ID']],
                'NAME' => $user[1],
            ]);
            $usersReRe[$user[0]] = $add['CREATED_BY'];
            $usersReRe[$user[1]] = $add['CREATED_BY'];
        }

        $user = $usersEspanaRusa[$row['RESPONSIBLE_ID']];
        $add['RESPONSIBLE_ID'] = $usersReRe[$user[0]] ?: $usersReRe[$user[1]];

        $list = ['TITLE', 'DEADLINE', 'START_DATE_PLAN', 'END_DATE_PLAN', 'PRIORITY', 'TAGS', 'DURATION_PLAN', 'DURATION_TYPE', 'MARK', 'GROUP_ID'];
        $bxReRe->run('task.item.add', [], ['fields' => array_filter($row, function ($key) use ($list) {
                return in_array($key, $list);
            }, ARRAY_FILTER_USE_KEY) + $add]);
    }
}
//var_dump($data);

header('Content-Type: application/json');
echo json_encode([
    'result' => $result,
]);