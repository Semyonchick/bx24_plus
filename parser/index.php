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
    static $count = 0;

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
        print_r('---' . PHP_EOL . $data['name'] . PHP_EOL);
        $urls = [];
        if ($data['art'] && $data['art'] != '(к/к)') $urls[] = strtr(self::$url, ['{q}' => urlencode($data['art'])]);
        $urls[] = strtr(self::$url, ['{q}' => urlencode($data['name'])]);

        foreach ($urls as $url) {
            $dom = self::getUrl($url);

            /** @var Dom\Collection $list */
            $list = $dom->find('#cat-list .catalog-list-item h5 a');

            // Если найдено много определяем схожесть и удаляем непохожие
            if ($list->count() > 1) {
                foreach ($list as $key => $a) {
                    similar_text($data['name'], $a->text, $percent = 0);
                    if ($percent < 70) unset($list[$key]);
                }
            }

            /** @var Dom\Tag $a */
            foreach ($list as $a) {
                return self::get('https://avselectro.ru' . $a->getAttribute('href'), $data);
            }
        }

        sleep(15);

        // ищем по гуглу
        $url = 'https://www.google.ru/search?q=' . urlencode($data['name']) . '+site%3Aavselectro.ru';
        $dom = self::getUrl($url);
        $dom->load(iconv('cp1251', 'utf8', $dom->innerHtml));

        /** @var Dom\Collection $list */
        $list = $dom->find('h3 a');

        /** @var Dom\Tag $a */
        foreach ($list as $a) {
            similar_text($data['name'], explode(' | ', trim($a->text, '. '))[0], $percent = 0);
            if ($percent > 70)
                return self::get($a->getAttribute('href'), $data);
        }

        if (self::$count++ > 100) {
            die;
        }

        flush();
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
        $result = $data + [
                'наименование' => $dom->find('h1')[0]->text,
                'цена' => $dom->find('.upl-price')[0]->text,
                'старая цена' => $dom->find('.item-card-price mark')[0]->text,
            ];

        foreach ($dom->find('.breadcrumbs a') as $i => $row) {
            if ($i) $result['категория ' . $i] = $row->text;
        }

        foreach ($dom->find('.item-label span') as $row) {
            if (($i = $row->find('i')) && count($i)) {
                $key = trim(str_replace($i[0]->outerHtml, '', $row->innerHtml));
                if ($a = $i[0]->find('a')) {
                    $result[$key] = $a[0]->text;
                    $result[$key . ' подробно'] = $a[0]->getAttribute('data-title');
                }
            } elseif (preg_match('#^(.+)\s([^\s]+)$#', $row->text, $match)) {
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