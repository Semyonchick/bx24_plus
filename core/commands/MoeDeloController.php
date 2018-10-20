<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace app\commands;

use app\components\BX24;
use Exception;
use linslin\yii2\curl\Curl;
use yii\console\Controller;
use yii\helpers\Console;

/**
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class MoeDeloController extends Controller
{
    public $config = [];
    private $key = 'eb027a65-4807-480c-9921-178363c38dbe';

    public function actionIndex()
    {
        $fieldCode = 'UF_CRM_1539972908';
        $bx = new BX24(['url' => \Config::$control['rere']]);
        $bills = $bx->run('crm.invoice.list', ['filter' => ['UF_MYCOMPANY_ID' => 193, $fieldCode => false]]);
        $nomenclature = $this->run('/stock/api/v1/nomenclature');
        foreach ($bills as $bill) {
            $find = $this->run('/accounting/api/v1/sales/bill', ['number' => $bill['ACCOUNT_NUMBER']]);
            if (count($find)) continue;

            $requisite = false;
            if ($bill['UF_COMPANY_ID']) $requisite = $bx->run('crm.requisite.list', ['filter' => ['ENTITY_ID' => $bill['UF_COMPANY_ID'], 'ENTITY_TYPE_ID' => 4]]);
            if (!$requisite && $bill['UF_CONTACT_ID']) $requisite = $bx->run('crm.requisite.list', ['filter' => ['ENTITY_ID' => $bill['UF_CONTACT_ID'], 'ENTITY_TYPE_ID' => 3]]);
            if (empty($requisite)) {
                Console::output('Не найдены реквизиты');
                continue;
            } else $requisite = $requisite[0];

            $clients = $this->run('/kontragents/api/v1/kontragent', ['inn' => $requisite['RQ_INN']]);
            if (empty($clients)) {
                $clients = [$this->run('/kontragents/api/v1/kontragent', [
                    "Inn" => $requisite['RQ_INN'],
                    "Ogrn" => $requisite['RQ_OGRN'],
                    "Okpo" => $requisite['RQ_OKPO'],
                    "Name" => $requisite['RQ_COMPANY_FULL_NAME'] ?: $requisite['RQ_COMPANY_NAME'] ?: $requisite['NAME'],
                ], 'post')];
            }
            if ($client = $clients[0]) {
                $items = [];
                $products = $bx->run('crm.productrow.list', ['filter' => ['OWNER_ID' => $bill['ID'], 'OWNER_TYPE' => 'I']]);
                foreach ($products as $product) {
//                    $list = $this->run('/stock/api/v1/good', ['name' => $product['PRODUCT_NAME']]);
//                    if (!$list[0]) {
//                        $item = $this->run('/stock/api/v1/good', [
//                            'Name' => $product['PRODUCT_NAME'],
//                            'UnitOfMeasurement' => $product['MEASURE_NAME'],
//                            'Nds' => $product['TAX_RATE'],
//                            'NdsPositionType' => 1,
//                            'Type' => 0,
//                            'NomenclatureId' => end($nomenclature)['Id'], //10947437
//                            'SalePrice' => $product['PRICE'],
//                        ], 'post');
//                    } else $item = $list[0];
                    $items[] = [
                        "Name" => $product['PRODUCT_NAME'],
                        "Unit" => $product['MEASURE_NAME'],
                        "Type" => 2,
                        "NdsType" => $product['TAX_RATE'],
                        'Count' => $product['QUANTITY'],
                        'Price' => $product['PRICE']];
                }

                $add = $this->run('/accounting/api/v1/sales/bill', [
                    "Number" => $bill['ACCOUNT_NUMBER'],
                    "DocDate" => $bill['DATE_BILL'],
                    "Type" => 1,
                    "Status" => 4,
                    "KontragentId" => $client['Id'],
                    "DeadLine" => $bill['DATE_PAY_BEFORE'],
                    "AdditionalInfo" => trim($bill['COMMENT'] . "\r\n" . $bill['USER_DESCRIPTION']),
                    "ContractSubject" => $bill['ORDER_TOPIC'],
                    "NdsPositionType" => 1,
                    "Items" => $items,
                ], 'post');

                $bx->run('crm.invoice.update', ['id' => $bill['ID'], 'fields' => [$fieldCode => $add['Id']]]);
            }
        }
    }

    /*
     * @return array
     */
    public function run($method, $data = null, $type = 'get')
    {
        $url = 'https://restapi.moedelo.org';
        $url .= $method;

        try {
            $curl = new Curl();
            if ($data) {
                if ($type == 'get') $url .= '?' . http_build_query($data);
                else $curl->setPostParams($data);
            }
            $curl->setHeader('md-api-key', $this->key);
            $result = $curl->{$type}($url);

            $result = json_decode($result, 1);
        } catch (Exception $e) {
            Console::output($e->getMessage());
            die;
        }

        if (isset($result['ResourceList'])) return $result['ResourceList'];
        if ($result['Message']) {
            print_r($data);
            print_r($result);
            throw new Exception($result['Message']);
        }

        return $result;
    }
}
