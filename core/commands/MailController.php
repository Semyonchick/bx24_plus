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
 */
class MailController extends Controller
{
    public $config = [];

    private $_bx;

    public function init()
    {
        $this->config = [
            'hook' => \Config::$control['rere'],
            'mail' => ['host' => 'pop.yandex.ru', 'user' => 'info@rere-design.ru', 'password' => '6ozUh0uThY1jNZT7CByQ', 'ssl' => 'SSL'],
        ];
    }

    public function actionIndex()
    {
    }

    public function actionBank()
    {
        $storage = new \afinogen89\getmail\storage\Pop3($this->config['mail']);

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
                    } else {
                        $this->toAdmin('Не найдено счетов для оплаты ' . $price);
                    }
                }
            }
        }
    }

    public function bx($method, $get = null, $post = null)
    {
        if (!$this->_bx) $this->_bx = new BX24(['url' => $this->config['hook']]);
        return $this->_bx->run($method, $get, $post);
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

        // Ищем такую же сумму
        foreach ($bills as $bill) {
            if (($bill['PRICE'] - $bill['UF_CRM_1513599139']) == $price) {
                return $bill;
            }
        }

        // Ищем сумму в 2 раза больше
        foreach ($bills as $bill) {
            if (($bill['PRICE'] - $bill['UF_CRM_1513599139']) == $price * 2) {
                return $bill;
            }
        }

        // Ищем любую сумму больше
        foreach ($bills as $bill) {
            if (($bill['PRICE'] - $bill['UF_CRM_1513599139']) > $price) {
                return $bill;
            }
        }

        // Ищем и списываем все что найдем
        foreach ($bills as $bill) {
            if (($bill['PRICE'] - $bill['UF_CRM_1513599139']) > $price) {
                return $bill;
            } else {
                $this->payBill($bill, $bill['PRICE'] - $bill['UF_CRM_1513599139']);
                $price = $price - $bill['PRICE'] - $bill['UF_CRM_1513599139'];
            }
        }

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
