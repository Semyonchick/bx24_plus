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

foreach (Config::$tasks as $group => $source) {
    $bxSource = new BX24(['url' => $source]);
    $bxTarget = new BX24(['url' => Config::$target]);

    $usersSource = [];
    foreach ($bxSource->run('user.get', ['ADMIN_MODE' => 'Y']) as $user)
        $usersSource[$user['ID']] = [$user['EMAIL'], trim("{$user['LAST_NAME']} {$user['NAME']} {$user['SECOND_NAME']}")];

    $usersTarget = [];
    foreach ($bxTarget->run('user.get', ['ADMIN_MODE' => 'Y']) as $user) {
        $usersTarget[$user['EMAIL']] = $user['ID'];
        $usersTarget[trim("{$user['LAST_NAME']} {$user['NAME']} {$user['SECOND_NAME']}")] = $user['ID'];
    }

    $getBxUser = function ($sourceId) use ($usersSource, $usersTarget, $bxTarget, $group) {
        $user = $usersSource[$sourceId];
        $targetId = $usersTarget[$user[0]] ?: $usersTarget[$user[1]];
        if (!$targetId) {
            $targetId = $bxTarget->run('user.add', [], [
                'EMAIL' => $user[0],
                'EXTRANET' => 'Y',
                'SONET_GROUP_ID' => [$group],
                'NAME' => $user[1]?:$user[0],
            ]);
            $usersTarget[$user[0]] = $usersTarget[$user[1]] = $targetId;
        }
        return $targetId;
    };

    $data = $bxSource->run('task.item.list', [['CHANGED_DATE' => 'desc'], ['RESPONSIBLE_ID' => $bxSource->user]]);
    foreach ($data as $row) {
        $row['GROUP_ID'] = $group;

        $tasks = $bxTarget->run('task.item.list', [['ID' => 'asc'], ['GROUP_ID' => $row['GROUP_ID'], 'TITLE' => $row['TITLE']]]);
        if (empty($tasks)) {
            $add = [
                'UF_AUTO_472428192293' => $row['ID'],
                'STATUS' => $row['REAL_STATUS'],
                'DESCRIPTION' => $row['DESCRIPTION'] . PHP_EOL . PHP_EOL . "https://{$bxSource->domain}/company/personal/user/{$row['RESPONSIBLE_ID']}/tasks/task/view/{$row['ID']}/",
                'CREATED_BY' => $getBxUser($row['CREATED_BY']),
                'RESPONSIBLE_ID' => $getBxUser($row['RESPONSIBLE_ID']),
            ];

            $list = ['TITLE', 'DEADLINE', 'START_DATE_PLAN', 'END_DATE_PLAN', 'PRIORITY', 'TAGS', 'DURATION_PLAN', 'DURATION_TYPE', 'MARK', 'GROUP_ID'];
            $bxTarget->run('task.item.add', [], ['fields' => array_filter($row, function ($key) use ($list) {
                    return in_array($key, $list);
                }, ARRAY_FILTER_USE_KEY) + $add]);
        }
    }
}

header('Content-Type: application/json');
echo json_encode([
    'result' => $result,
]);