<?php
/**
 * Created by PhpStorm.
 * User: semyonchick
 * Date: 13.03.2018
 * Time: 17:51
 */

error_reporting(E_ALL ^ E_NOTICE ^ E_STRICT);
ini_set('display_errors', 1);
ini_set('html_errors', 1);
ob_implicit_flush(1);

use PHPHtmlParser\Dom;

require_once __DIR__ . '/vendor/autoload.php';

header('Content-Type: text/plain; charset=utf-8');

Parser::$headers = ['id', 'path', 'name', 'art', 0];
Parser::$url = 'https://avselectro.ru/search/index.php?q={q}';
Parser::dir(__DIR__ . '/upload/');

class Parser
{
    static $cp1251 = true;
    static $headers = false;
    static $url = false;

    static function dir($dir)
    {
        foreach (scandir($dir) as $file) {
            self::file($dir . $file);
        };
    }

    static function file($path)
    {
        $headers = self::$headers ?: [];
        if (($handle = fopen($path, "r")) !== false) {
            while (($data = fgetcsv($handle, 0, ";")) !== false) {
                if (self::$cp1251) {
                    $data = array_map(function ($row) {
                        return trim(iconv('cp1251', 'utf8', $row));
                    }, $data);
                }

                if (empty($headers)) {
                    $headers = $data;
                } else {
                    self::find(array_combine($headers, $data));
                }
            }
        }
        fclose($handle);
    }

    static function find($data)
    {
        var_dump($data['name']);
        $urls = [];
        if ($data['art'] && $data['art'] != '(к/к)') $urls[] = strtr(self::$url, ['{q}' => $data['art']]);
        $urls[] = strtr(self::$url, ['{q}' => $data['name']]);

        foreach ($urls as $url) {
            $dom = self::getUrl($url);

            $list = $dom->find('#cat-list .catalog-list-item h5 a');
            if (count($list) > 1) {
                var_dump(count($list), $url);
                continue;
            }
            /** @var DOMElement $a */
            foreach ($list as $a) {
                return self::get('https://avselectro.ru' . $a->getAttribute('href'), $data);
            }
        }
    }

    static function getUrl($url)
    {
        $dom = new Dom;
        $dom->setOptions([
            'cleanupInput' => true,
        ]);

        $html = self::getCache($url);
        if (!$html) {
            $dom->load($url);
            self::setCache($url, $dom->innerHtml);
        } else {
            $dom->load($html);
        }
        return $dom;
    }

    static function getCache($id)
    {
        return file_exists(self::cacheFile($id)) ? file_get_contents(self::cacheFile($id)) : false;
    }

    static function cacheFile($id)
    {
        $dir = __DIR__ . '/cache/';
        if (!file_exists($dir)) mkdir($dir, 777, true);
        return $dir . md5($id) . '.bin';
    }

    static function setCache($id, $content)
    {
        file_put_contents(self::cacheFile($id), $content, LOCK_EX);
    }

    static function get($url, $data)
    {
        $dom = self::getUrl($url);
        $result = [
            'name' => $dom->find('h1')[0]->text,
            'price' => $dom->find('.upl-price')[0]->text,
            'старая цена' => $dom->find('.item-card-price mark')[0]->text,
        ];

        foreach($dom->find('.breadcrumbs a') as $i => $row){
            if($i) $result['категория ' . $i] = $row->text;
        }

        foreach ($dom->find('.item-label span') as $row) {
            if (($i = $row->find('i')) && count($i)) {
                $key = trim(str_replace($i[0]->outerHtml, '', $row->innerHtml));
                if ($a = $i[0]->find('a')){
                    $result[$key] = $a[0]->text;
                    $result[$key . ' подробно'] = $a[0]->getAttribute('data-title');
                }
            } elseif(preg_match('#^(.+)\s([^\s]+)$#', $row->text, $match)) {
                $result[$match[1]] = $match[2];
            }
        }

        foreach ($dom->find('.article .char-name') as $i => $row)
            $result[trim(str_replace(':&nbsp;', ' ', $row->text))] = $dom->find('.article .char-param')[$i]->text;

        print_r($url);
        print_r($result);

        return $result;
    }
}