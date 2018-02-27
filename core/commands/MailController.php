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
    private $_payBills = 0;

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

        $emails = $imap->search(['FROM "emails.tinkoff.ru"'], 0, 5, false, true);
        Console::output('tinkoff: ' . count($emails));
        foreach ($emails as $email) foreach ($email['attachment'] as $attach) if ($attach['text/plain']) {
            $result = \Yii::$app->cache->get($cacheId = 'mail' . $email['uid']);
            if (1 || $result === false) {
                foreach (['cp1251', 'koi8-r'] as $fromEncode) {
                    $text = iconv($fromEncode, 'utf8', $attach['text/plain']);
                    if ($text) break;
                }
                $result = $this->parse1CFile($text, $email['uid']);
                if ($result) {
                    $this->actionCheckData();
                    \Yii::$app->cache->set($cacheId, $result, 86400 * 7);
                }
            }
        }

        $emails = $imap->search(['FROM "ubrr.ru"'], 0, 5, false, true);
        Console::output('ubrr: ' . count($emails));
        foreach ($emails as $email) {
            $result = \Yii::$app->cache->get($cacheId = 'mail' . $email['uid']);
            if ($result === false) {
                $text = iconv('koi8-r', 'utf8', $email['body']['text/plain']);
                $result = $this->parseMail($text, $email['uid']);
                if ($result) {
                    $this->actionCheckData();
                    \Yii::$app->cache->set($cacheId, $result, 86400 * 7);
                }
            }
        }

        $imap->disconnect();

        if (date('i') == 33) {
            $this->actionCheckData();
        }
    }

    public function parse1CFile($text, $emailId = false)
    {
        $result = [];
        foreach (explode('СекцияДокумент=', $text) as $i => $row) {
            $emailId .= $i;
            if ($this->getData($emailId)) continue;
            if ($i && preg_match_all('#(.+)\=(.+)#', $row, $matches)) {
                $data = array_combine($matches[1], array_map('trim', $matches[2]));

                if ($data['ДатаПоступило']) {
                    if (!($requisiteFrom = $this->findRequisite($data['ПлательщикИНН'])))
                        $this->toAdmin('Не найден плательщик');
                    if (!($requisiteTo = $this->findRequisite($data['ПолучательИНН'])))
                        $this->toAdmin('Не найден получатель');

                    $result[] = $this->addData($data['Плательщик'], $data['ДатаПоступило'], $data['Сумма'], $requisiteFrom['ENTITY_ID'], $requisiteTo['ENTITY_ID'], $emailId);
                }
            }
        }
        return $result;
    }

    public function getData($emailId)
    {
        return $emailId && $this->bx('lists.element.get', ['IBLOCK_TYPE_ID' => 'lists', 'IBLOCK_ID' => 45, 'ELEMENT_CODE' => $emailId]);

    }

    public function bx($method, $get = null, $post = null)
    {
        if (!$this->_bx) $this->_bx = new BX24(['url' => $this->config['hook']]);
        return $this->_bx->run($method, $get, $post);
    }

    public function findRequisite($value, $columns = ['NAME', 'RQ_INN', 'RQ_COMPANY_NAME', 'RQ_COMPANY_FULL_NAME'])
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

    public function toAdmin($message)
    {
        mail('semyonchick@gmail.com', 'From bitrix controller', $message);
        return false;
    }

    public function addData($from, $date, $price, $fromId, $toId, $emailId)
    {
        $this->_payBills = 0;

        if ($toId && $fromId && !$this->config['skipBilling']) {
            if ($bill = $this->selectBill($fromId, $toId, $price)) {
                $this->payBill($bill, $price);
            } else {
                $this->toAdmin('Не найдено счетов на сумму ' . $price);
            }
        }

        // Регистрируем список
        return $this->bx('lists.element.add', [], [
            'IBLOCK_TYPE_ID' => 'lists',
            'IBLOCK_ID' => '45',
            'ELEMENT_CODE' => $emailId ?: uniqid(),
            'FIELDS' => [
                'ACTIVE_FROM' => $date,
                'NAME' => $from,
                'PROPERTY_171' => $price,
                'PROPERTY_173' => $fromId,
                'PROPERTY_175' => $toId,
                'PROPERTY_177' => $this->config['skipBilling'] ? $price : $this->_payBills,
            ],
        ]);
    }

    public function selectBill($fromId, $toId, $price)
    {
        $filter = ['UF_COMPANY_ID' => $fromId, 'UF_MYCOMPANY_ID' => $toId, 'PAYED' => 'N'];
        $filter['!%ACCOUNT_NUMBER'] = '#';
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

    public function actionCheckData()
    {
        $forPay = [];
        $data = $this->bx('lists.element.get', ['IBLOCK_TYPE_ID' => 'lists', 'IBLOCK_ID' => 45, 'ELEMENT_ORDER' => ['ID' => 'DESC']]);
        foreach ($data as $row) {
            if ($row['PROPERTY_173'] && $row['PROPERTY_175'] && current($row['PROPERTY_171']) > current($row['PROPERTY_177'])) {
                $forPay[] = $row;
            }
        }

        if (count($forPay)) {
            Console::output(count($forPay));

            array_reverse($forPay);
            foreach ($forPay as $row) {
                $this->_payBills = 0;

                $price = current($row['PROPERTY_171']) - current($row['PROPERTY_177']);
                if ($bill = $this->selectBill(current($row['PROPERTY_173']), current($row['PROPERTY_175']), $price)) {
                    $this->payBill($bill, $price);
                }

                if ($this->_payBills) {
                    $row['PROPERTY_177'] = $this->_payBills;
                    $this->bx('lists.element.update', [], [
                        'IBLOCK_TYPE_ID' => 'lists',
                        'IBLOCK_ID' => '45',
                        'ELEMENT_ID' => $row['ID'],
                        'FIELDS' => $row + [
                                'PROPERTY_177' => $this->_payBills,
                            ],
                    ]);
                }
            }
        }
    }

    public function parseMail($text, $emailId = false)
    {
        if (preg_match('#(\d{2}\.\d{2}\.\d{2}\s\d{2}\:\d{2}).+(\d{20}).+?\s([\d\s\.]+)\s.+\n?От:\s(.+?)(?:(?:р\/с|Р\/С).+)?\n?Ostatok po schetu#iu', $text, $match)) {
            if ($this->getData($emailId)) return true;

            list(, $date, $bill, $price, $from) = $match;
            $price = (float)str_replace(' ', '', $price);

            $requisiteTo = \Yii::$app->cache->get($bill);
            if ($requisiteTo === false) {
                $requisiteTo = ($data = $this->bx('crm.requisite.bankdetail.list', ['filter' => ['RQ_ACC_NUM' => $bill], 'select' => ['ENTITY_ID']])) ? $this->findRequisite($data[0]['ENTITY_ID'], ['ID']) : null;
                if (!$requisiteTo) {
                    $this->toAdmin('Не найден счет получателя ' . $bill);
                } else {
                    \Yii::$app->cache->set($bill, $requisiteTo, 86400);
                }
            }

            if (!($requisiteFrom = $this->findRequisite($from))) {
                $this->toAdmin('Не найден плательщик ' . $from);
            }

            return $this->addData($from, preg_replace('#\.(\d{2})\s#', '.20$1 ', $date), $price, $requisiteFrom['ENTITY_ID'], $requisiteTo['ENTITY_ID'], $emailId);
        }
        return false;
    }
}
