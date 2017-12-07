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
        $update['UF_CRM_1512623740219'] = (float)$model['OPPORTUNITY'];

        // задолжность
        $update['UF_CRM_1511504908812'] = $model['OPPORTUNITY'] - (float)$model['UF_CRM_1511504826340'];

        $dealLists = $c->bx('lists.element.get', [
            'IBLOCK_TYPE_ID' => 'lists',
            'IBLOCK_ID' => 39,
        ]);

        // затраты
        $pay = $update['UF_CRM_1511504852012'] = 0;
        foreach ($dealLists as $deal) {
            $date = isset($deal['PROPERTY_281']) ? current($deal['PROPERTY_281']) : false;
            if (!empty($deal['PROPERTY_211']) && in_array($id, $deal['PROPERTY_211'])) {
                $update['UF_CRM_1511504852012'] += \app\components\Course::parseTo(current($deal['PROPERTY_189']), $model['CURRENCY_ID'], $date);
                if (isset($deal['PROPERTY_279'])) $pay += \app\components\Course::parseTo(current($deal['PROPERTY_279']), $model['CURRENCY_ID'], $date);
            }
        }

        // остаток по затратам
        $update['UF_CRM_1511504891151'] = $update['UF_CRM_1511504852012'] - $pay;

        // валовая моржа
        $update['UF_CRM_1512623759507'] = $update['UF_CRM_1512623740219'] - $update['UF_CRM_1511504852012'];

        foreach ($update as $key => $value) {
            $update[$key] = is_numeric($value) ? $value . '|' . $model['CURRENCY_ID'] : $value;
        }

        if (count(array_diff($update, $model))) {
            $save = $c->bx('crm.' . $type . '.update', [], ['id' => $id, 'fields' => $update, 'params' => ['REGISTER_SONET_EVENT' => 'N']]);
            mail('semyonchick@gmail.com', 'update deal', print_r([$_REQUEST, array_diff($update, $model), $save], 1));
        }
    }
}

if (isset($_REQUEST['event']) && $_REQUEST['event'] == 'ONCRMDEALUPDATE') {
    bx_prices_update('deal', $_REQUEST['data']['FIELDS']['ID']);
} elseif (!empty($_REQUEST['crm'])) {
    $params = [
        'IBLOCK_TYPE_ID' => 'lists',
        'IBLOCK_ID' => 41,
        'ELEMENT_ID' => $_REQUEST['document_id'][2],
    ];
    $lists = $c->bx('lists.element.get', $params);
    foreach ($lists as $row)
        foreach ($row['PROPERTY_205'] as $value)
            if (preg_match('#^(\w{1,2}_)?(\d+)$#i', $value, $match)) {
                if ($match[1] == 'D_') {
                    $deal = $c->bx('crm.' . array_search($match[1], $c->bxTypesMap) . '.get', ['id' => $match[2]]);
                    if ($deal['CONTACT_ID'] || $deal['COMPANY_ID']) {
                        if ($deal['CONTACT_ID']) $row['PROPERTY_205'][] = ['C_' . $deal['CONTACT_ID']];
                        if ($deal['COMPANY_ID']) $row['PROPERTY_205'][] = ['CO_' . $deal['COMPANY_ID']];
                        $c->bx('lists.element.update', [], [
                            'IBLOCK_TYPE_ID' => 'lists',
                            'IBLOCK_ID' => 41,
                            'ELEMENT_ID' => $row['ID'],
                            'FIELDS' => $row,
                        ]);
                    }
                }
            }
    return '123';
} elseif ($_REQUEST['document_id'][2]) {
    $params = [
        'IBLOCK_TYPE_ID' => 'lists',
        'IBLOCK_ID' => 39,
        'ELEMENT_ID' => $_REQUEST['document_id'][2],
    ];
    $lists = $c->bx('lists.element.get', $params);
    foreach ($lists as $row) {
        foreach ($row['PROPERTY_211'] as $value) {
            if (preg_match('#^(\w{1,2}_)?(\d+)$#i', $value, $match)) {
                bx_prices_update($match[1] ? array_search($match[1], $c->bxTypesMap) : 'deal', $match[2]);

            }
        }
    }
    if(isset($row['PROPERTY_213'])) foreach ($row['PROPERTY_213'] as $value) {
        if (preg_match('#^(\w{1,2}_)?(\d+)$#i', $value, $match)) {
            $id = $match[2];
            $type = array_search($match[1], $c->bxTypesMap);
            $key = $type == 'company' ? 'UF_CRM_1512569993082' : 'UF_CRM_1512570963680';
            $model = $c->bx('crm.' . $type . '.get', ['id' => $id]);
            if ($model) {
                $lists = $c->bx('lists.element.get', [
                    'IBLOCK_TYPE_ID' => 'lists',
                    'IBLOCK_ID' => 39,
                ]);

                // затраты
                $update[$key] = 0;
                foreach ($lists as $list) {
                    if (!empty($list['PROPERTY_213']) && in_array($match[0], $list['PROPERTY_213'])) {
                        $update[$key] += preg_match('#\d+#', current($list['PROPERTY_189']), $match) ? $match[0] : 0;
                        $update[$key] -= preg_match('#\d+#', current($list['PROPERTY_279']), $match) ? $match[0] : 0;
                        if(!$model['CURRENCY_ID']) $model['CURRENCY_ID'] = preg_replace('#[0-9\.]+\|#','', current($list['PROPERTY_189']));
                    }
                }

                foreach ($update as $key => $val) {
                    $update[$key] = is_numeric($val) ? $val . '|' . $model['CURRENCY_ID'] : $val;
                }

                if (count(array_diff($update, $model)))
                    $c->add('crm.' . $type . '.update', ['id' => $id, 'fields' => $update, 'params' => ['REGISTER_SONET_EVENT' => 'N']]);
            }
        }
    }

}

//mail('semyonchick@gmail.com', __FILE__, print_r($_REQUEST, 1));

header('Content-Type: application/json');
echo json_encode([
    'result' => $result,
]);