<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace app\commands;

use app\components\BX24;
use Config;
use linslin\yii2\curl\Curl;
use yii\console\Controller;
use yii\console\Exception;
use yii\helpers\Console;

/**
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class BitrixController extends Controller
{
    public $url = 'https://holding-gel.bitrix24.ru/rest/112/5m56ebgk24ijxt0y/';
    private $bxLog = [];

    public function actionIndex($skipErrors = true)
    {
        $data = $this->bx('crm.contact.list', ['filter' => ['ASSIGNED_BY_ID' => 12, '!=CREATED_BY_ID' => 12]]);

        foreach ($data as $row) if ($row['ASSIGNED_BY_ID'] != $row['CREATED_BY_ID']) {
            $this->bx('crm.contact.update', ['id' => $row['ID'], 'fields' => ['ASSIGNED_BY_ID' => $row['CREATED_BY_ID']]]);
        }

        if (count($data)) $this->actionIndex();
    }

    public function bx($method, $get = null, $post = null)
    {
        $cl = count($this->bxLog);
        if ($cl > 2 && ($spend = microtime(true) - $this->bxLog[$cl - 2]) && $spend < 1) {
            usleep((1 - $spend) * 1000000);
            return $this->bx($method, $get, $post);
        }

        $url = $this->url;
        $url .= $method . '/';
        if ($get) $url .= '?' . http_build_query($get);

        try {
            $curl = new Curl();
            if ($post) {
                $curl->setPostParams($post);
                $result = $curl->post($this->url . '' . $method . '/', true);
            } else {
                $result = $curl->get($url);
            }

            $result = json_decode($result, 1);
        } catch (Exception $e) {
            Console::output('bitrix error');
            sleep(1);
            return $this->bx($method, $get, $post);
        }

        if (!isset($result['result'])) {
            print_r($url);
            print_r($post);
            throw new Exception(print_r($result, 1));
        }

        $this->bxLog[] = microtime(true);

        return $result['result'];
    }

    public function actionSetYesIfDealExist()
    {
        $bx = new BX24(['url' => Config::$control['holding-gel']]);
        $i = 0;
        $ids = [];
        while (($data = $bx->run('crm.deal.list', ['order' => ['ID' => 'DESC'], 'select' => ['CONTACT_ID', 'COMPANY_ID', 'STAGE_ID'], 'start' => 50 * $i])) && count($data)) {
            $i++;
            Console::output('--' . $data[0]['ID']);
            foreach ($data as $row) {
                if ($row['CONTACT_ID'] && !$ids['contact'][$row['CONTACT_ID']]) $ids['contact'][$row['CONTACT_ID']] = $row['STAGE_ID'] == 'WON';
                if ($row['COMPANY_ID'] && !$ids['contact'][$row['COMPANY_ID']]) $ids['contact'][$row['COMPANY_ID']] = $row['STAGE_ID'] == 'WON';
            }
            if ($i > 100) break;
            if ($i % 10 == 0) sleep(10);
        }

        Console::output($i);
        foreach ($ids as $type => $values) {
            foreach ($values as $id => $value) {
                $field = [
                    'contact' => 'UF_CRM_1527664648',
                    'company' => 'UF_CRM_1527664648',
                ];
                if ($field[$type]) {
                    Console::output($type . '-' . $id);
                    $bx->run('crm.' . $type . '.update', ['id' => $id, 'fields' => [$field[$type] => $value]]);
                }
            }
        }
    }
}
