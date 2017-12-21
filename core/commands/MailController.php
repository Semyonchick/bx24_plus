<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace app\commands;

use app\components\BX24;
use Eden\Mail\Imap;
use yii\console\Controller;
use yii\helpers\Console;

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
            'mail' => ['host' => 'imap.yandex.ru', 'user' => 'info@rere-design.ru', 'password' => '6ozUh0uThY1jNZT7CByQ', 'ssl' => 'SSL'],
            'skipBilling' => false,
        ];
    }

    public function actionIndex()
    {
        /** @var Imap $imap */
        $imap = \Eden\Core\Control::i()->__invoke('mail')->imap(
            $this->config['mail']['host'],
            $this->config['mail']['user'],
            $this->config['mail']['password'],
            993,
            true);

        $imap->setActiveMailbox('INBOX');

        $emails = $imap->search(['FROM "bank@ubrr.ru"'], 0, 5, false, true);
        foreach ($emails as $email) {
            $result = \Yii::$app->cache->get($cacheId = 'mail' . $email['uid']);
            if ($result === false) {
                $text = iconv('koi8-r', 'utf8', $email['body']['text/plain']);
                $result = $this->parseMail($text, $email['uid']);
                if ($result) \Yii::$app->cache->set($cacheId, $result, 86400 * 365);
            }
        }

        $imap->disconnect();
    }

    public function actionBank()
    {
        $storage = new \afinogen89\getmail\storage\Pop3($this->config['mail']);

        $count = $storage->countMessages();

        Console::output($count);

        for ($i = 0; $i < $count; $i++) {
            $msg = $storage->getMessage($i + 1);
            if ($msg->getHeaders()->getFrom() == 'bank@ubrr.ru' && $this->parseMail(strip_tags($msg->getMsgBody()))) {
                $storage->removeMessage($msg->id);
            }
        }

        Console::output('end');
    }

    private $_payBills = 0;

    public function parseMail($text, $emailId = false)
    {
        $this->_payBills = 0;

        if (preg_match('#(\d{2}\.\d{2}\.\d{2}\s\d{2}\:\d{2}).+(\d{20}).+?\s([\d\s\.]+)\s.+\n?От:\s(.+?)(?:(?:р\/с|Р\/С).+)?\n?Ostatok po schetu#iu', $text, $match)) {
            if ($emailId && $this->bx('lists.element.get', ['IBLOCK_TYPE_ID' => 'lists', 'IBLOCK_ID' => 45, 'ELEMENT_CODE' => $emailId])) return true;

            list(, $date, $bill, $price, $from) = $match;
            $price = (float)str_replace(' ', '', $price);

            $requisiteTo = \Yii::$app->cache->get($bill);
            if ($requisiteTo === false) {
                $requisiteTo = ($data = $this->bx('crm.requisite.bankdetail.list', ['filter' => ['RQ_ACC_NUM' => $bill], 'select' => ['ENTITY_ID']])) ? $this->findRequisite($data[0]['ENTITY_ID'], ['ID']) : null;
                \Yii::$app->cache->set($bill, $requisiteTo, 86400);
            }

            if ($requisiteFrom = $this->findRequisite($from)) {
                if ($requisiteTo) {
                    if (!$this->config['skipBilling']) {
                        if ($bill = $this->selectBill(['UF_COMPANY_ID' => $requisiteFrom['ENTITY_ID'], 'UF_MYCOMPANY_ID' => $requisiteTo['ENTITY_ID'], 'PAYED' => 'N'], $price)) {
                            $this->payBill($bill, $price);
                        } else {
                            $this->toAdmin('Не найдено счетов на сумму ' . $price);
                        }
                    }
                } else {
                    $this->toAdmin('Не найден счет получателя ' . $bill);
                }
            } else {
                $this->toAdmin('Не найден плательщик ' . $from);
            }

            return $this->bx('lists.element.add', [], [
                'IBLOCK_TYPE_ID' => 'lists',
                'IBLOCK_ID' => '45',
                'ELEMENT_CODE' => $emailId ?: uniqid(),
                'FIELDS' => [
                    'ACTIVE_FROM' => preg_replace('#\.(\d{2})\s#', '.20$1 ', $date),
                    'NAME' => $from,
                    'PROPERTY_171' => $price,
                    'PROPERTY_173' => $requisiteFrom['ENTITY_ID'],
                    'PROPERTY_175' => $requisiteTo['ENTITY_ID'],
                    'PROPERTY_177' => $this->config['skipBilling'] ? $price : $this->_payBills,
                ],
            ]);
        }
        return false;
    }

    public function bx($method, $get = null, $post = null)
    {
        if (!$this->_bx) $this->_bx = new BX24(['url' => $this->config['hook']]);
        return $this->_bx->run($method, $get, $post);
    }

    public function findRequisite($value, $columns = ['NAME', 'RQ_COMPANY_NAME', 'RQ_COMPANY_FULL_NAME'])
    {
        foreach ($columns as $column) {
            $filter = [$column => mb_strtoupper(trim($value))];
            $data = $this->bx('crm.requisite.list', ['filter' => $filter, 'select' => ['ENTITY_ID']]);
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
        /*foreach ($bills as $bill) {
            if (($bill['PRICE'] - $bill['UF_CRM_1513599139']) > $price) {
                return $bill;
            }
        }*/

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
        $params['UF_CRM_1513599139'] = $bill['UF_CRM_1513599139'] + $pay;
        $params['STATUS_ID'] = $bill['PRICE'] == $params['UF_CRM_1513599139'] ? 'P' : 'U';

        if ($this->bx('crm.invoice.update', [], ['id' => $bill['ID'], 'fields' => $params]))
            $this->_payBills += $pay;
        else
            $this->toAdmin('Не удалось оплатить счет ' . $bill['ID'] . ' на сумму ' . $pay);
    }

    public function toAdmin($message)
    {
        mail('semyonchick@gmail.com', 'From bitrix controller', $message);
        return false;
    }
}
