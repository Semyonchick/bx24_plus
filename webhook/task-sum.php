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

$tokens = ['7zidwgrkth70ip8eyed87zt2d1ijh2vj', '3ygdclf2k89pnxg11amt5kpacefbtv6s', 'ck6evct2ytyo6788p07i3joyw81wv8al'];

$result = false;

function sumChildTasks($taskIs, $bx)
{
    foreach ($bx->run('task.item.list', [['ID' => 'asc'], ['ID' => $taskIs, '!CHANGED_BY' => $bx->getUser()], ['NAV_PARAMS' => ['nPageSize' => 1]], ['ID', 'PARENT_ID', 'UF_*']]) as $task) {
        $tasks = $bx->run('task.item.list', [['ID' => 'asc'], ['PARENT_ID' => $taskIs], ['NAV_PARAMS' => ['nPageSize' => 50]], ['ID', 'UF_*']]);

        $params = [];
        if (count($tasks)) {
            $params['UF_AUTO_813593154674'] = $params['UF_AUTO_378796169189'] = 0;

            foreach ($tasks as $childTask) {
                $params['UF_AUTO_813593154674'] += $childTask['UF_AUTO_813593154674'];
                $params['UF_AUTO_378796169189'] += $childTask['UF_AUTO_378796169189'];
            }

            $params['UF_AUTO_965311856607'] = $params['UF_AUTO_813593154674'] - $params['UF_AUTO_378796169189'];
        } else {
            $params['UF_AUTO_965311856607'] = $task['UF_AUTO_813593154674'] - $task['UF_AUTO_378796169189'];
        }

        if (array_diff($params, $task)) {
            $bx->run('task.item.update', [], ['TASKID' => $task['ID'], 'TASKDATA' => $params]);
        }

        if ($task['PARENT_ID']) sumChildTasks($task['PARENT_ID'], $bx);
    }
}

if ($_POST['auth']['domain'] == 'holding-gel.bitrix24.ru' && in_array($_POST['auth']['application_token'], $tokens)) {
    if ($id = $_POST['data']['FIELDS_AFTER']['ID']) {
        $bx = new BX24(['url' => 'https://holding-gel.bitrix24.ru/rest/112/5m56ebgk24ijxt0y/']);
        sumChildTasks($id, $bx);
        $result = true;
    }
}

header('Content-Type: application/json');
echo json_encode([
    'result' => $result,
]);