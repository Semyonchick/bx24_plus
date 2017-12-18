<?php
/**
 * Created by PhpStorm.
 * User: semyonchick
 * Date: 25.05.2017
 * Time: 22:20
 */


defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'dev');

require_once __DIR__ . '/vendor/autoload.php';
require(__DIR__ . '/vendor/yiisoft/yii2/Yii.php');

$config = [
    'id' => 'web',
    'basePath' => __DIR__,
    'components' => [
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
    ],
];

$application = new yii\console\Application($config);
$application->run();

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', true);
ini_set('display_startup_errors', true);
ini_set('html_errors', true);

header('Content-Type: text/plain; charset=utf-8');

if ([preg_match('#([^\.\/]+)\.bitrix24\.ru#', $_SERVER['HTTP_REFERER'], $match)]) $domain = $match[1];
elseif (!($domain = $_GET['id'] ?: $_POST['id'])) throw new Exception('Can`t find domain');

header('Access-Control-Allow-Origin: https://'.$domain.'.bitrix24.ru');
header('Access-Control-Allow-Methods: POST');

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

require_once __DIR__ . '/.config.php';