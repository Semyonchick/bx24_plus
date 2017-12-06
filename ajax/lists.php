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
require_once __DIR__ . '/../core/commands/MegaplanController.php';
$c = new \app\commands\MegaplanController('list', false);

function bx_prices_update($type, $id)
{
    global $c;
    $model = $c->bx('crm.' . $type . '.get', ['id' => $id]);
    if ($model) {
        $update = [];

        // сумма
        $update['UF_CRM_1511504908812'] = $model['OPPORTUNITY'] - (float)$model['UF_CRM_1511504826340'];

        $dealLists = $c->bx('lists.element.get', [
            'IBLOCK_TYPE_ID' => 'lists',
            'IBLOCK_ID' => 39,
        ]);

        // затраты
        $update['UF_CRM_1511504852012'] = 0;
        foreach ($dealLists as $deal) {
            if (!empty($deal['PROPERTY_211']) && in_array($id, $deal['PROPERTY_211'])) {
                $update['UF_CRM_1511504852012'] += preg_match('#\d+#', current($deal['PROPERTY_189']), $match) ? $match[0] : 0;
            }
        }

        // остаток по затратам
        $update['UF_CRM_1511504891151'] = $update['UF_CRM_1511504908812'] - $update['UF_CRM_1511504852012'];

        foreach ($update as $key => $value) {
            $update[$key] = is_numeric($value) ? $value . '|' . $model['CURRENCY_ID'] : $value;
        }

        $c->add('crm.' . $type . '.update', ['id' => $id, 'fields' => $update]);

        // аванс
        $model['UF_CRM_1511504826340'];
        // сумма
        $model['UF_CRM_1511504908812'];
        // затраты
        $model['UF_CRM_1511504852012'];
        // остаток по затратам
        $model['UF_CRM_1511504891151'];

//        var_dump($model);
    }
}

if ($_REQUEST['event'] == 'ONCRMDEALUPDATE') {
    bx_prices_update('deal', $_REQUEST['data']['FIELDS']['ID']);
} elseif ($_REQUEST['document_id'][2]) {
    $params = [
        'IBLOCK_TYPE_ID' => 'lists',
        'IBLOCK_ID' => 39,
        'ELEMENT_ID' => $_REQUEST['document_id'][2],
    ];
    $lists = $c->bx('lists.element.get', $params);
    foreach ($lists as $row)
        foreach ($row['PROPERTY_211'] as $value) {
            if (preg_match('#^(\w{1,2}_)?(\d+)$#i', $value, $match)) {
                bx_prices_update($match[1] ? array_search($match[1], $c->bxTypesMap) : 'deal', $match[2]);

            }
        }
}


//    mail('semyonchick@gmail.com', __FILE__, print_r([$row, $params], 1));

header('Content-Type: application/json');
echo json_encode([
    'result' => $result,
]);