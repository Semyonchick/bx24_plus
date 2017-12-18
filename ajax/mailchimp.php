<?php
/**
 * Created by PhpStorm.
 * User: semyonchick
 * Date: 06.10.2017
 * Time: 9:12
 *
 * Для получения персонализиварованых данных скрипта
 * getData();
 *
 * Для записи персонализиварованых данных скрипта
 * setData();
 */

use Mailchimp\Mailchimp;

require_once __DIR__ . '/../core/server.php';

$result = [];

$list = [
    'holding-gel' => '1c3db67f2aaee2c39dd8937d5cdfcec9-us16',
    'espanarusa' => '544989de47041bba5b5552e0310edac4-us14',
];

$mc = new Mailchimp($list[$domain]);

if ($_POST['type'] == 'subscribed') {
    $result = $mc->request('lists/' . $_POST['listId'] . '/members/' . md5($_POST['email']), [
        'email_address' => $_POST['email'],
        'email_type' => 'html',
        'status' => 'subscribed',
        'merge_fields' => !empty($_POST['fields']) ? $_POST['fields'] : ['FNAME' => ''],
    ], 'PUT')->toArray();
} else {
    $result = $mc->request('lists', [
        'fields' => 'lists.id,lists.name,lists.stats.member_count',
        'count' => 50
    ])->toArray();
    if ($_POST['email']) {
        $list = $mc->request('lists', [
            'fields' => 'lists.id,lists.name,lists.stats.member_count',
            'email' => $_POST['email'],
            'count' => 50
        ])->toArray();
        $result = json_decode(json_encode($result), 1);
        $list = json_decode(json_encode($list), 1);
        foreach ($list as $row) {
            foreach ($result as $key => $value)
                if ($value['id'] == $row['id']) $result[$key]['exist'] = true;
        }
    }
}

usort($result, function ($a, $b) {
    return $a['name'] > $b['name'];
});

header('Content-Type: application/json');
echo json_encode([
    'result' => $result,
]);