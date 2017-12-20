<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace app\commands;

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
        $data = $this->bx('crm.contact.list', ['filter'=>['ASSIGNED_BY_ID' => 12, '!=CREATED_BY_ID' => 12]]);

        foreach ($data as $row) if ($row['ASSIGNED_BY_ID'] != $row['CREATED_BY_ID']) {
            $this->bx('crm.contact.update', ['id'=>$row['ID'], 'fields'=>['ASSIGNED_BY_ID' => $row['CREATED_BY_ID']]]);
        }

        if(count($data)) $this->actionIndex();
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

    public function actionMail()
    {
        $this->url = \Config::$control['rere'];

        $storage = new \afinogen89\getmail\storage\Pop3(['host' => 'pop.yandex.ru', 'user' => 'info@rere-design.ru', 'password' => '6ozUh0uThY1jNZT7CByQ', 'ssl' => 'SSL']);
        $count = $storage->countMessages();
        for ($i = 0; $i < $count; $i++) {
            $msg = $storage->getMessage($i + 1);
            if ($msg->getHeaders()->getFrom() == 'bank@ubrr.ru') {
                $text = strip_tags($msg->getMsgBody());
                if (preg_match('#(\d{20}).+?\s([\d\s\.]+)\s.+\n?От:\s(.+)\n?Ostatok po schetu#', $text, $match)) {
                    list(, $bill, $price, $from) = $match;
                    $price = (float)str_replace(' ', '', $price);

                    if (($data = $this->bx('crm.requisite.bankdetail.list', ['filter' => ['RQ_ACC_NUM' => $bill], 'select' => ['ENTITY_ID']])) &&
                        ($requisiteFrom = $this->findRequisite($from)) &&
                        ($requisiteTo = $this->findRequisite($data[0]['ENTITY_ID'], ['ID'])) &&
                        ($bill = $this->selectBill(['UF_COMPANY_ID' => $requisiteFrom['ENTITY_ID'], 'UF_MYCOMPANY_ID' => $requisiteTo['ENTITY_ID'], 'PAYED' => 'N'], $price))) {
                        $this->payBill($bill, $price);
                        $storage->removeMessage($msg->id);
                    }
                }
            }
        }

    }

    public function findRequisite($value, $columns = ['NAME', 'RQ_COMPANY_NAME', 'RQ_COMPANY_FULL_NAME'])
    {
        foreach ($columns as $column) {
            $data = $this->bx('crm.requisite.list', ['filter' => [$column => $value], 'select' => ['ENTITY_ID']]);
            if ($data) {
                return current($data);
            }
        }
        return false;
    }

    public function selectBill($filter, $price)
    {
        $bills = $this->bx('crm.invoice.list', ['order' => ['ID' => 'ASC'], 'filter' => $filter, 'select' => ['ID', 'PRICE', 'UF_CRM_1513599139']]);

        foreach ($bills as $bill) {
            if (($bill['PRICE'] - $bill['UF_CRM_1513599139']) == $price) {
                return $bill;
            }
        }

        foreach ($bills as $bill) {
            if (($bill['PRICE'] - $bill['UF_CRM_1513599139']) == $price * 2) {
                return $bill;
            }
        }

        foreach ($bills as $bill) {
            if (($bill['PRICE'] - $bill['UF_CRM_1513599139']) > $price) {
                return $bill;
            }
        }

        foreach ($bills as $bill) {
            if (($bill['PRICE'] - $bill['UF_CRM_1513599139']) > $price) {
                return $bill;
            } else {
                $this->payBill($bill, $bill['PRICE'] - $bill['UF_CRM_1513599139']);
                $price = $price - $bill['PRICE'] - $bill['UF_CRM_1513599139'];
            }
        }

        if ($price > 0) $this->toAdmin('Не найдено счетов для оплаты ' . $price);
        return false;
    }

    public function payBill($bill, $pay)
    {
        $this->toAdmin('Оплачиваем счет ' . $bill['ID'] . ' на сумму ' . $pay);
    }

    public function toAdmin($message)
    {
        mail('semyonchick@gmail.com', 'From bitrix controller', $message);

    }
}
