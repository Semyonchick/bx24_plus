<?php
/**
 * Created by PhpStorm.
 * User: semyonchick
 * Date: 07.12.2017
 * Time: 13:24
 */

namespace app\components;

use Exception;
use linslin\yii2\curl\Curl;
use yii\base\Component;
use yii\helpers\Console;

class BX24 extends Component
{
    public $bxLog = [];
    public $url = false;

    public function run($method, $get = false, $post = false){
        $cl = count($this->bxLog);
        if ($cl > 2 && ($spend = microtime(true) - $this->bxLog[$cl - 2]) && $spend < 1) {
            usleep ((1 - $spend) * 1000000);
            return self::run($method, $get, $post);
        }

        $url = $this->url;
        $url .= $method . '/';
        if ($get) $url .= '?' . http_build_query($get);

        try {
            $curl = new Curl();
            if ($post) {
                $curl->setPostParams($post);
                $result = $curl->post($url, true);
            } else {
                $result = $curl->get($url);
            }

            $result = json_decode($result, 1);
        } catch (Exception $e){
            Console::output('bitrix error');
            sleep(1);
            return self::run($method, $get, $post);
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