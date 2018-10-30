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
class TimeManController extends Controller
{
    public $config = [];

    private $_bx;

    public function init()
    {
        $this->config = [
            'hook' => \Config::$timeman['rere'],
        ];
    }

    public function actionIndex()
    {
        $users = $this->bx('user.get', ['ACTIVE' => 'Y']);
        foreach ($users as $user) {
            $data = $this->bx('timeman.status', ['USER_ID' => $user['ID']]);
            if (in_array($data['STATUS'], ['PAUSED', 'EXPIRED'])) {
                $params = ['USER_ID' => $user['ID'], 'TIME_LEAKS'=>$data['TIME_LEAKS'], 'REPORT' => 'from server api control'];
                $result = $this->bx('timeman.close', $params);
                Console::output(print_r([$data, $params, $result], 1));
            }
        }
    }

    public function bx($method, $get = null, $post = null, $test = false)
    {
        if (!$this->_bx) $this->_bx = new BX24(['url' => $this->config['hook']]);
        return $this->_bx->run($method, $get, $post, $test);
    }
}
