<?php
/**
 * Created by PhpStorm.
 * User: semyonchick
 * Date: 25.05.2017
 * Time: 22:20
 */

Define('YII_DEBUG', 0);
use linslin\yii2\curl\Curl;
require_once __DIR__ . '/vendor/autoload.php';

error_reporting(E_ALL);
ini_set('display_errors', true);
ini_set('display_startup_errors', true);
ini_set('html_errors', true);

header('Content-Type: text/html; charset=utf-8');

header('Access-Control-Allow-Origin: https://espanarusa.bitrix24.ru');
header('Access-Control-Allow-Methods: POST');
//header('Access-Control-Max-Age: 10000');

//if (!($domain = $_GET['id'] ?: $_POST['id'])) throw new HttpException('Can`t find domain');
$domain = 'espanarusa';

$file = __DIR__ . '/../data/' . $domain . '/' . basename($_SERVER['SCRIPT_FILENAME'], '.php') . '.json';

function getData()
{
    global $file;
    $data = file_exists($file) ? file_get_contents($file) : null;
    if ($tmpData = json_decode($data, 1)) if (is_array($tmpData)) $data = $tmpData;
    return $data;
}

function setData($data)
{
    global $file;
    if (!file_exists(dirname($file))) mkdir(dirname($file), 0777, true);
    return file_put_contents($file, is_array($data) ? json_encode($data) : $data);
}

function p($data, $return = false)
{
    if (!$data) var_dump($data);
    else
        if ($return) return '<pre>' . print_r($data, 1) . '</pre>';
        else echo '<pre>' . print_r($data, 1) . '</pre>';
    return false;
}

$bxLog = [];
function bx($method, $get = null, $post = null) {
    global $bxLog;

    $cl = count($bxLog);
    if ($cl > 2 && ($spend = microtime(true) - $bxLog[$cl - 2]) && $spend < 1) {
        usleep ((1 - $spend) * 1000000);
        return $this->bx($method, $get, $post);
    }

//    if(!$get) $get = [];
//    $get['auth'] = $_REQUEST['auth']['member_id'];
//    $url = $_REQUEST['auth']['client_endpoint'];
    $url = 'https://espanarusa.bitrix24.ru/rest/49/yzmwq2ftvomdnpre/';
    $url .= $method . '/';
    if($get) $url .= '?' . http_build_query($get);

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
        sleep(1);
        return $this->bx($method, $get, $post);
    }

    if (!isset($result['result'])) {
        print_r($url);
        print_r($post);
        throw new Exception(print_r($result, 1));
    }

    $bxLog[] = microtime(true);

    return $result['result'];
}