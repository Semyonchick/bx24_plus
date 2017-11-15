<?php
/**
 * Created by PhpStorm.
 * User: semyonchick
 * Date: 25.05.2017
 * Time: 22:20
 */

require_once __DIR__ . '/vendor/autoload.php';

header('Content-Type: text/html; charset=utf-8');

header('Access-Control-Allow-Origin: https://hyper-script.ru');
header('Access-Control-Allow-Methods: POST');
//header('Access-Control-Max-Age: 10000');

if (!($domain = $_GET['id'] ?: $_POST['id'])) throw new HttpException('Can`t find domain');

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
}