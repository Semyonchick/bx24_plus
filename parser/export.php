<?php
/**
 * Created by PhpStorm.
 * User: semyonchick
 * Date: 19.03.2018
 * Time: 14:12
 */

$file = __DIR__ . '/parses.txt';
$saveFile = __DIR__ . '/parses.csv';

$keys = [];

if (($handle = fopen($file, "r")) !== false) {
    while (!feof($handle)) {
        $data = unserialize(trim(fgets($handle)));
        if ($data) {
            $keys = array_unique(array_merge($keys, array_keys($data)));
        }
    }
}
fclose($handle);

file_put_contents($saveFile, null);

$fp = fopen($saveFile, 'w');
fputcsv($fp, $keys);

if (($handle = fopen($file, "r")) !== false) {
    while (!feof($handle)) {
        $data = unserialize(trim(fgets($handle)));

        if ($data) {
            $string = [];
            foreach ($keys as $key) $string[] = $data[$key];
            fputcsv($fp, $string);
        }
    }
}
fclose($handle);

fclose($fp);