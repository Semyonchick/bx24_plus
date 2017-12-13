<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace app\commands;

use app\components\BX24;
use yii\console\Controller;

/**
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 *
 * @params $amo \AmoCRM\Client
 */
class AmoController extends Controller
{
    private $_amo;

    public function actionIndex($thisComments = false)
    {
        $bx = new BX24(['url' => 'https://holding-gel.bitrix24.ru/rest/112/5m56ebgk24ijxt0y/']);
        $amoInfo = $this->getAmo()->account->apiCurrent();
        $amoUsers = [];
        foreach ($amoInfo['users'] as $row) {
            $amoUsers[$row['id']] = trim("{$row['name']} {$row['last_name']}");
        }

        $bxProperties = [];
        foreach ($bx->run('crm.deal.userfield.list') as $row) {
            $bxProperties[$row['FIELD_NAME']] = $row;
        }

        $from = 0;
        while ($list = $this->getAmo()->lead->apiList(['limit_rows' => 500, 'limit_offset' => $from, 'id' => 17142391])) {
            foreach ($list as $row) {
                $result = $bx->run('crm.deal.list', ['filter' => ['ORIGINATOR_ID' => $row['id'], 'ORIGIN_ID' => 'amoCRM'], 'select' => ['UF_*']]);
                if ($result = $result[0]) {
                    $toSave = [
//                        'OPPORTUNITY' => $row['price'],
                        'UF_CRM_1512981007275' => date('d.m.Y', $row['date_create']),
                    ];

                    foreach ([
                                 'Стоимость' => 'OPPORTUNITY',
                                 'Аванс' => 'UF_CRM_1512967601319',
                             ] as $key => $code)
                        if ($property = array_filter($row['custom_fields'], function ($row) use ($key) {
                            return trim($row['name']) == $key;
                    })) {
                            $toSave[$code] = current($property)['values'][0]['value'] . '|RUB';
                    }

                    foreach ([
                                 'Источник заявки' => 'UF_CRM_1512969036',
                                 'Цель приобретения' => 'UF_CRM_1512969753',
                                 'Источник финансирования' => 'UF_CRM_1512978243',
                                 'Тип недвижимости' => 'UF_CRM_1512978545',
                                 'Дата регистрации ДДУ' => 'UF_CRM_1512978954235',
                             ] as $key => $code) {
                        if ($property = array_filter($row['custom_fields'], function ($row) use ($key) {
                            return trim($row['name']) == $key;
                        })) {
                            $id = current(array_filter($bxProperties[$code]['LIST'], function ($row) use ($property) {
                                return current($property)['values'][0]['value'] == $row['VALUE'];
                            }))['ID'];
                            $toSave[$code] = $id;
                        }
                    }

                    if (array_diff($toSave, $result)) {
                        $bx->run('crm.deal.update', [], [
                            'id' => $result['ID'],
                            'fields' => $toSave
                        ]);
                    }

                    if ($thisComments) {
                        $comments = [];
                        $notes = $this->getAmo()->note->apiList(['type' => 'lead', 'element_id' => $row['id']]);
                        foreach ($notes as $note) {
                            if (!json_decode($note['text']) && $note['text'] != 'Добавлен новый объект') {
                                $comments[$note['last_modified']] = $amoUsers[$note['responsible_user_id']] . ' - ' . date('d.m.Y H:i:s', $note['last_modified']) .
                                    PHP_EOL . $note['text'];
                            }
                        }

                        $tasks = $this->getAmo()->task->apiList(['type' => 'lead', 'element_id' => $row['id']]);
                        foreach ($tasks as $task) {
                            $comments[$task['last_modified']] = $amoUsers[$task['responsible_user_id']] . ' - ' . date('d.m.Y H:i:s', $task['last_modified']) .
                                PHP_EOL . trim('Задача: ' . $task['text'] . PHP_EOL . $task['result']['text']);
                        }

                        ksort($comments);
                        foreach ($comments as $comment)
                            $bx->run('crm.livefeedmessage.add', [], [
                                'fields' => [
                                    'POST_TITLE' => 'Из амо',
                                    'MESSAGE' => $comment,
                                    'ENTITYTYPEID' => 2,
                                    'ENTITYID' => $result['ID'],
                                ]
                            ]);
                    }
                }
            }
            $from += count($list);
        }

    }

    public function getAmo()
    {
        if (!$this->_amo) {
            $amo = new \yii\amocrm\Client([
                'subdomain' => 'sunsity',
                'login' => 'sunsity.anapa@yandex.ru',
                'hash' => '7a5a265bd37d237f310cde1eac81b98a',
            ]);
            $this->_amo = $amo->getClient();
        }
        return $this->_amo;
    }
}
