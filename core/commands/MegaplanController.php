<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace app\commands;

use linslin\yii2\curl\Curl;
use Megaplan\SimpleClient\Client;
use yii\console\Controller;
use yii\console\Exception;
use yii\helpers\ArrayHelper;
use yii\helpers\Console;

/**
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class MegaplanController extends Controller
{
    public $url = 'https://espanarusa.bitrix24.ru/rest/49/yzmwq2ftvomdnpre/';

    public $employeesMap = [
        1000002 => 69,
        1000015 => 55,
        1000007 => 75,
        1000040 => 37,
        1000005 => 73,
        1000006 => 61,
        1000030 => 57,
        1000041 => 63,
        1000001 => 41,
        1000017 => 51,
        1000033 => 59,
    ];
    public $bxTypesMap = ['company' => 'CO_', 'contact' => 'C_', 'deal' => 'D_', 'lead' => 'L_'];
    public $categoryMap = [
        0 => ['Продажа наших услуг', 'Аренда недвижимости', 'Продажа рекламы', 'Базовая'],
        3 => ['Получение комиссии'],
        1 => ['Продажа Икры'],
    ];
    public $statusMap = [
        'NEW' => ['Интерес', 'Интрес', 'Запрос', 'Начало сделки'],
        'PREPARATION' => ['Отложено', 'Заказ', 'Коммерческое предложение', 'Договор', 'Договор', 'Подписание договора', 'Комиссия подтверждена', 'Оформление заказа'],
        'PREPAYMENT_INVOICE' => ['Заказ подтверждён', 'Заказ подтверждён', 'Внесение предоплаты', 'Оплачено', 'Оплачен', 'Предоплата', 'Окончательные расчеты', 'Комиссия получена'],
        'EXECUTING' => ['Отгрузили', 'Услуги оказываются', 'Приемка', 'Устранение замечаний', 'Выполнение работ', 'Отправили счет-фактуру'],
        'FINAL_INVOICE' => ['Постоплата', 'Внесение остатка за аренду'],
        'WON' => ['Услуги оказаны', 'Закрыто', 'Постоплата', 'Устранение замечаний', 'Завершена'],
        'LOSE' => ['Отказ', 'Отвал'],
    ];

    private $log = [];
    private $bxLog = [];
    private $auth = [];

    public function actionIndex($skipErrors = true)
    {
        Console::output(PHP_EOL . '-');
        $remember = \Yii::$app->cache->get('parseHistory');
        $i = $remember ?: 0;
        while (($list = $this->get('/BumsCrmApiV01/Contractor/list.api', ['Offset' => $i])) && count($list)) {
            Console::output('/Offset ' . $i . ':' . count($list));
            foreach ($list as $key => $client) if ($i + $key > $remember) {
                Console::output(($i + $key) . ': ' . $client['Name']);
                if ($skipErrors) $this->skip('addClient', $client);
                else $this->addClient($client);
                \Yii::$app->cache->set('parseHistory', $i + $key, 3600000);
            }
            $i += count($list);
        }
        Console::output('Обработано ' . ($i-500 + $key) . ' контактов. Обработка заверешена.');
    }

    public function actionTest()
    {
        $data = $this->get('/BumsCrmApiV01/Contractor/card.api', ['Id' => 1000938]);
        $this->addClient($data);
//        print_r($data);

//        var_dump($this->bx('crm.productrow.fields'));
    }

    public function actionCsv()
    {
        $remember = \Yii::$app->cache->get('csvCommentHistory');
        $i = 0;
        $headers = [];
        if (($handle = fopen(\Yii::getAlias('@app/../data/comment_201710271105.csv'), "r")) !== false) {
            while (($data = fgetcsv($handle, 0, ",")) !== false) {
                if (!$headers) $headers = $data;
                else {
                    $i++;
                    if ($i < $remember) continue;
                    $data = array_combine($headers, $data);

                    $params = [
                        'IBLOCK_TYPE_ID' => 'lists',
                        'IBLOCK_ID' => 55,
                        'ELEMENT_CODE' => $data['comment_id'],
                    ];
                    Console::output("{$i}: {$data['comment_id']}");

                    $row = $this->bx('lists.element.get', $params);
                    if (!$row) {
                        $crmId = str_replace(',', '', $data['subject_id']);

                        $types = ['lead', 'contact', 'company'];
                        if ($crmId < 1000000) array_unshift($types, 'deal');

                        $bitrixId = false;
                        foreach ($types as $object) if (!$bitrixId) {
                            $bitrixId = $this->exist('crm.' . $object . '.list', [
                                "ORIGINATOR_ID" => "megaplan", // Идентификатор внешней информационной базы
                                "ORIGIN_ID" => $crmId, // Внешний ключ
                            ]);
                            if ($bitrixId) $bitrixId = $this->bxTypesMap[$object] . $bitrixId;
                        }
                        if (!$bitrixId || !$data['text']) continue;

                        $params['FIELDS'] = [
                            'NAME' => $data['comment_id'],
                            'DETAIL_TEXT' => $data['text_html'],
                            'ACTIVE_FROM' => date('d.m.Y H:i:s', strtotime($data['time_created'])),
                            'PROPERTY_229' => $this->employeesMap[str_replace(',', '', $data['user_created'])] ?: 61,
                            'PROPERTY_231' => $bitrixId,
                        ];
                        $this->add('lists.element.add', $params);
                    }
                    \Yii::$app->cache->set('csvCommentHistory', $i, 3600000);
                }
            }
            fclose($handle);
        }
    }

    public function skip($method, $data)
    {
        try {
            $this->$method($data);
        } catch (Exception $e) {
            Console::error('ERROR: ' . $e->getCode() . ' ' . $e->getMessage());
            $this->skip($method, $data);
        }
    }

    public function actionDelete()
    {
        $delete = 0;
        foreach (['lead', 'deal', 'contact', 'company'] as $object) {
            foreach ($this->bx("crm.{$object}.list", ['filter' => ["ORIGINATOR_ID" => "megaplan"]]) as $row) {
                if ($row['ORIGINATOR_ID'] == 'megaplan') {
                    $this->bx("crm.{$object}.delete", ['id' => $row['ID']]);
                    $delete++;
                }
            }
        }
        Console::output($delete);
        if ($delete) $this->actionDelete();
    }

    public function addClient($data)
    {
        $list = $this->get('/BumsTradeApiV01/Deal/list.api', ['FilterFields' => ['Contractor' => $data['Id']]]);
//        $list = array_filter($list, function ($row) use ($data) {
//            return $row['Contractor']['Id'] == $data['Id'];
//        });

        if (empty($list) && empty($data['ParentCompany'])) $object = 'lead';
        elseif ($data['PersonType'] == 'human') $object = 'contact';
        else $object = 'company';

        $params = [
            "ORIGINATOR_ID" => "megaplan", // Идентификатор внешней информационной базы
            "ORIGIN_ID" => $data['Id'], // Внешний ключ
        ];

        $result = $this->exist('crm.' . $object . '.list', $params);
        if (!$result) {
            $data = ArrayHelper::merge($data, $this->get('/BumsCrmApiV01/Contractor/card.api', ['Id' => $data['Id'], 'RequestedFields' => array_map(function ($row) {
                return $row['Name'];
            }, $this->get('/BumsCrmApiV01/Contractor/listFields.api'))]));
            $params += [
                'TITLE' => $data['Name'], // Название. Обязательное поле.
                "POST" => "", // Должность
                "COMMENTS" => nl2br(trim($data['Description'])), // Комментарии
                "EMAIL" => array_map(function ($row) {
                    return ["VALUE" => trim($row), "VALUE_TYPE" => "WORK"];
                }, explode(',', $data['Email'])), // e-mail
                "PHONE" => array_map(function ($row) {
                    return ["VALUE" => $row, "VALUE_TYPE" => "WORK"];
                }, array_unique($data['Phones'])), // Телефон
                "ADDRESS" => preg_replace('#<br[^>]*>#', "\n", implode(",\n ", array_map(function ($row) {
                    return $row['Address'];
                }, array_diff_key($data['Locations'], ['', 'Адрес не указан'])))), // Адрес
                "ASSIGNED_BY_ID" => $this->employeesMap[$data['Responsibles'][0]['Id']] ?: 61, // Ответственный
                "SOURCE_ID" => 'OTHER', // Источник
                "BIRTHDATE" => $data['Birthday'] ?: $data['Category183CustomFieldDenRozhdeniya'], // Дата рождения
                "WEB" => $data['Site'], // веб-сайт
                "OPENED" => 'Y', // Доступен всем

                "STATUS_ID" => "1", // Идентификатор статуса лида

                "NAME" => $data['FirstName'], // Имя
                "SECOND_NAME" => $data['MiddleName'], // Отчество
                "LAST_NAME" => $data['LastName'], // Фамилия
                "COMPANY_TITLE" => $data['CompanyName'], // Название компании
                "TYPE_ID" => $data['Type']['Name'] == 'Партнеры' ? 'PARTNER' : 'CLIENT', // Тип контакта
                "HONORIFIC" => $data['Gender'] ? $data['Gender'] == 'male' ? 'HNR_RU_1' : 'HNR_RU_2' : '', // Обращение

                "COMPANY_TYPE" => $data['Type']['Name'] == 'Партнеры' ? 'PARTNER' : 'CUSTOMER', // Тип компании
            ];
            foreach (['Icq', 'Facebook', 'Jabber', 'Skype', 'Twitter'] as $im) if ($data[$im]) {
                $params['IM'][] = ["VALUE" => $data[$im], "VALUE_TYPE" => $im];
            }
            if ($data['ParentCompany']) {
                $company = $this->exist('crm.company.list', [
                    "ORIGINATOR_ID" => "megaplan", // Идентификатор внешней информационной базы
                    "ORIGIN_ID" => $data['ParentCompany']['Id'],// Внешний ключ
                ]);
                $params['COMPANY_ID'] = $company;
            }

            $result = $this->add('crm.' . $object . '.add', $params);
        }

        foreach ($list as $lead) $this->addLead($result, $object, $lead);

        $this->addAttaches($result, $object, $data['Attaches']);
    }

    public function addLead($clientId, $object, $data)
    {
        $params = [
            "ORIGINATOR_ID" => "megaplan", // Идентификатор внешней информационной базы
            "ORIGIN_ID" => $data['Id'], // Внешний ключ
        ];

        $result = $this->exist('crm.deal.list', $params);
        if (!$result) {
            $data = ArrayHelper::merge($data, $this->get('/BumsTradeApiV01/Deal/card.api', ['Id' => $data['Id'], 'RequestedFields' => array_map(function ($row) {
                return $row['Name'];
            }, $this->get('/BumsTradeApiV01/Deal/listFields.api'))]));

            $cat = key(array_filter($this->categoryMap, function ($row) use ($data) {
                return in_array($data['Program']['Name'], $row);
            }));
            $stage = key(array_filter($this->statusMap, function ($row) use ($data) {
                return in_array(trim($data['Status']['Name']), $row);
            })) ?: $data['Status']['Name'];
            if ($cat && $stage) $stage = "C{$cat}:{$stage}";

            $params += [
                'TITLE' => $data['Name'], // Название сделки. Обязательное поле.
                'TYPE_ID' => '', // Идентификатор типа сделки.
                "STAGE_ID" => $stage ?: $data['Status']['Name'], // Идентификатор этапа сделки
                "CATEGORY_ID" => $cat, // Идентификатор направления сделки.
                "CURRENCY_ID" => $data['Cost']['CurrencyAbbreviation'], // Валюта сделки
                "OPPORTUNITY" => $data['Cost']['Value'], // Сумма в валюте сделки
                "COMPANY_ID" => $object == 'company' ? $clientId : '', // Идентификатор компании-контрагента сделки
                "CONTACT_ID" => $object == 'contact' ? $clientId : '', // Идентификатор контактного лица
                "BEGINDATE" => $data['TimeCreated'], // Дата открытия сделки
                "CLOSEDATE" => '', // Дата закрытия сделки
                "OPENED" => 'Y', // Сделка доступна для всех
                "CLOSED" => '', // Сделка закрыта
                "COMMENTS" => nl2br(trim($data['Description'])), // Комментарии
                "ASSIGNED_BY_ID" => $this->employeesMap[$data['Manager']['Id']] ?: 61, // Ответственный

                "ADDITIONAL_INFO" => "", // Дополнительная информация
            ];
            if (in_array($params['STAGE_ID'], ['WON', 'LOSE'])) {
                $params['CLOSEDATE'] = $data['TimeUpdated'];
            }
//            $params['TYPE_ID'] = $params['CATEGORY_ID'];
//            if ($params['CATEGORY_ID'] > 0) {
//                var_dump($params['CATEGORY_ID']);
//                die;
//            }

            if (!$params['STAGE_ID'] || $params['CATEGORY_ID'] === false) {
                var_dump(1);
                print_r($clientId);
                print_r($data);
                print_r($params);
                die;
            }

            $result = $this->add('crm.deal.add', $params);
        }

        $this->addProducts($result, $data['Positions']);

        $this->addAttaches($result, 'deal', $data['Attaches']);

        return $result;
    }

    public function addAttaches($itemId, $type, $data)
    {
        if ($data && $type != 'lead') {
            foreach ($data as $fileData) {
                $params = [
                    'IBLOCK_TYPE_ID' => 'lists',
                    'IBLOCK_ID' => '41',
                    'ELEMENT_CODE' => $fileData['Url'],
                ];
                if (count($this->bx('lists.element.get', $params))) continue;

                try {
                    $result = $this->add('disk.storage.uploadfile', [
                        'id' => 11,
                        'data' => ['NAME' => $fileData['Name']],
                        'fileContent' => [$fileData['FileName'], base64_encode($this->auth()->get($fileData['Url']))],
                    ]);
                    $this->bx('disk.file.moveto', ['id' => $result['ID'], 'targetFolderId' => '671']);
                } catch (Exception $e) {
                    Console::output($e->getMessage());
                    continue;
                }

                $params['FIELDS'] = [
                    'NAME' => current(explode('.', $fileData['Name'])),
                    'PROPERTY_209' => [['VALUE' => 'n' . $result['ID']]],
                    'PROPERTY_205' => [$this->bxTypesMap[$type] . $itemId],
                ];
                $this->add('lists.element.add', $params);
            }
        }
    }

    public function addProducts($dealId, $data)
    {
        if ($data) {
            $exist = $this->bx('crm.deal.productrows.get', ['id' => $dealId]);

            $rows = [];
            foreach ($data as $row) {
                if(empty($row['Offer']) || empty($row['DeclaredPrice'])) continue;
                $id = $exist ? array_filter($exist, function ($item) use ($row) {
                    return $item['PRODUCT_NAME'] == $row['Name'] && $item['PRICE'] == $row['DeclaredPrice']['Value'] && $item['QUANTITY'] == $row['Count'];
                }) : false;
                if ($id) $id = current($id)['ID'];

                $rows[] = [
                    'ID' => $id ?: 0,
                    'PRODUCT_ID' => 0,
                    'OWNER_ID' => 0,
                    'OWNER_TYPE' => 'Q',
                    'PRODUCT_NAME' => $row['Name'],
                    'PRICE' => $row['Cost']['Value'] / $row['Count'],
                    'DISCOUNT_TYPE_ID' => 1,
                    'DISCOUNT_SUM' => $row['DiscountValue'] / $row['Count'],
//                    'DISCOUNT_SUM' => $row['DiscountValue'],
                    'QUANTITY' => $row['Count'],
                    'MEASURE_NAME' => $row['Offer']['Unit']['Name'],
                    'CUSTOMIZED' => 'Y',
                ];
            }

            $result = $this->add('crm.deal.productrows.set', [
                'id' => $dealId,
                'rows' => $rows,
            ]);
            return $result;
        }
        return false;
    }

    public function get($method, $params = null)
    {
        $cacheId = $method . serialize($params ?: []);
        $data = \Yii::$app->cache->get($cacheId);
        if (1 || $data === false) {
            $ll = count($this->log);
            if (($ll > 3000 && ($spend = time() - $this->log[$ll - 3000]) && $spend < ($max = 3600)) ||
                ($ll >= 3 && ($spend = microtime(true) - $this->log[$ll - 3]) && $spend < ($max = 1))
            ) {
                usleep (($max - $spend) * 1000000);
                return $this->get($method, $params);
            }

            $this->log[] = microtime(true);

            try {
                $result = $this->auth()->get($method, $params);
            } catch (Exception $e) {
                sleep(1);
                Console::output('сбой мегаплан');
                return $this->get($method, $params);
            }


            if (is_string($result)) $result = json_decode($result);
            if ($result->status->code != 'ok') {
                print_r($result);
                throw new Exception($result->status->message);
            }

            $data = current(json_decode(json_encode($result->data), 1));

            \Yii::$app->cache->set($cacheId, $data, 3600);
        }

        return $data;
    }

    public function auth()
    {
        return $this->auth ?: ($this->auth = (new Client('espanarusa.megaplan.ru'))->auth('info@espanarusa.com', 'pylypenko1984'));
    }

    public function exist($method, $data)
    {
        if (!$data['ORIGIN_ID']) return false;

        $result = $this->bx(str_replace(['.add', '.update'], '.list', $method), ['filter' => [
            'ORIGINATOR_ID' => $data['ORIGINATOR_ID'],
            'ORIGIN_ID' => $data['ORIGIN_ID']
        ]]);

        if (count($result)) return $result[0]['ID'];

        return false;
    }

    public function add($method, $data)
    {
        if (!$data['id'] && !$data['IBLOCK_ID']) $data = ['fields' => $data, 'params' => ['REGISTER_SONET_EVENT' => 'N']];
        return $this->bx($method, [], $data);
    }

    public function bx($method, $get = null, $post = null)
    {
        $cl = count($this->bxLog);
        if ($cl > 2 && ($spend = microtime(true) - $this->bxLog[$cl - 2]) && $spend < 1) {
            usleep ((1 - $spend) * 1000000);
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
        } catch (Exception $e){
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
}
