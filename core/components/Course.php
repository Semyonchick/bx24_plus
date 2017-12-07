<?php
/**
 * Created by PhpStorm.
 * User: semyonchick
 * Date: 07.12.2017
 * Time: 13:24
 */

namespace app\components;

use yii\base\Component;

class Course extends Component
{
    static function parseTo($priceData, $toCurrency = 'RUB', $currencyDate = false)
    {
        $data = self::data($currencyDate);
        list($price, $currency) = explode('|', $priceData);

        if ($currency == $toCurrency) $k = 1;
        elseif ($currency == 'RUB') $k = 1 / $data[$toCurrency];
        elseif ($toCurrency == 'RUB') $k = $data[$currency];
        else $k = $data[$currency] / $data[$toCurrency];

        return ceil($k * $price * 100) / 100;
    }

    static function data($date = false, $resetCache = false)
    {
        $formattedDate = date('d/m/Y', $date ? (is_numeric($date) ? $date : strtotime($date)) : time());

        $result = \Yii::$app->cache->get($cacheId = __CLASS__ . __METHOD__ . $formattedDate);
        if ($result === false || $resetCache) {
            $url = 'http://www.cbr.ru/scripts/XML_daily.asp?date_req=' . $formattedDate;
            $data = simplexml_load_file($url);

            $result = [];
            foreach ($data->Valute as $row) {
                $result[(string)$row->CharCode] = (float)str_replace(',', '.', (string)$row->Value);
            }

            \Yii::$app->cache->set($cacheId, $result, 86400 * 365 * 10);
        }
        return $result;
    }

}