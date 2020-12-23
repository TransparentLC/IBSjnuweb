<?php
declare(strict_types=1);

namespace App\HttpController;

use \App\Util;

class Metrical extends \App\Component\HttpController {
    function index() {
        try {
            $room = strtoupper($this->args['room']);

            $client = Util::getIBSClient();
            $userID = Util::doIBSLogin($client, $room);

            $year = (int)$this->args['year'];
            $month = isset($this->args['month']) ? (int)$this->args['month'] : 0;

            if ($month) {
                $dt = new \DateTime;
                $dt->setDate($year, $month, 1);
                $startDate = $dt->format('Y-m-d');
                $endDate = $dt->format('Y-m-t');
            } else {
                $startDate = "{$year}-01-01";
                $endDate = "{$year}-12-31";
            }

            $response = json_decode($client->post('GetCustomerMetricalData', [
                'headers' => Util::getIBSRequestHeader($userID),
                'body' => json_encode([
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'interval' => $month ? 1 : 3,
                    'energyType' => 0,
                ], JSON_UNESCAPED_UNICODE_SLASHES),
            ])->getBody()->getContents(), true);

            if (empty($response['d']['ResultList'])) {
                throw new \Exception('No data, only recent metrical data is saved.');
            }

            $func0 = fn ($e) => [
                'time' => (int)($e['recordTime'] / 1000),
                'usage' => $e['dataValue'],
            ];
            $metricalData = [
                'electricity' => array_map($func0, $response['d']['ResultList'][0]['datas']),
                'coldWater' => array_map($func0, $response['d']['ResultList'][1]['datas']),
                'hotWater' => array_map($func0, $response['d']['ResultList'][2]['datas']),
            ];
            foreach ($metricalData as &$md) {
                usort($md, fn ($a, $b) => $a['time'] <=> $b['time']);
            }

            $func1 = fn ($e) => [
                date($month ? 'Y-m-d' : 'Y-m', $e['time']),
                (string)$e['usage'],
            ];
            $func2 = fn ($acc, $cur) => $acc += $cur['usage'];
            if (isset($_GET['text'])) {
                header('Content-Type:text/plain');
                $template = <<< 'AKARIN'
%s 耗能记录
================================

电能使用记录（度）：

%s

冷水使用记录（吨）：

%s

热水使用记录（吨）：

%s

> 数据获取时间：%s
>
> Powered by Akarin ⁄(⁄⁄•⁄ω⁄•⁄⁄)⁄

AKARIN;
                echo sprintf(
                    $template,
                    $room,
                    Util::markdownTable(
                        ['日期', '使用量'],
                        [
                            ...array_map($func1, $metricalData['electricity']),
                            ['总计', (string)array_reduce($metricalData['electricity'], $func2, 0)],
                        ]
                    ),
                    Util::markdownTable(
                        ['日期', '使用量'],
                        [
                            ...array_map($func1, $metricalData['coldWater']),
                            ['总计', (string)array_reduce($metricalData['coldWater'], $func2, 0)],
                        ]
                    ),
                    Util::markdownTable(
                        ['日期', '使用量'],
                        [
                            ...array_map($func1, $metricalData['hotWater']),
                            ['总计', (string)array_reduce($metricalData['hotWater'], $func2, 0)],
                        ]
                    ),
                    date('Y-m-d H:i:s')
                );
            } else if (isset($_GET['html'])) {
                /*
                    <h1>%s 耗能记录</h1>
                    <p>电能使用记录（度）：</p>
                    <table>...</table>
                    <p>冷水使用记录（吨）：</p>
                    <table>...</table>
                    <p>热水使用记录（吨）：</p>
                    <table>...</table>
                    <blockquote>
                        <p>数据获取时间：%s</p>
                        <p>Powered by Akarin ⁄(⁄⁄•⁄ω⁄•⁄⁄)⁄</p>
                    </blockquote>
                */
                $template = '<h1>%s 耗能记录</h1><p>电能使用记录（度）：</p>%s<p>冷水使用记录（吨）：</p>%s<p>热水使用记录（吨）：</p>%s<blockquote><p>数据获取时间：%s</p><p>Powered by Akarin ⁄(⁄⁄•⁄ω⁄•⁄⁄)⁄</p></blockquote>';
                echo sprintf(
                    $template,
                    $room,
                    Util::htmlTable(
                        ['日期', '使用量'],
                        [
                            ...array_map($func1, $metricalData['electricity']),
                            ['总计', (string)array_reduce($metricalData['electricity'], $func2, 0)],
                        ]
                    ),
                    Util::htmlTable(
                        ['日期', '使用量'],
                        [
                            ...array_map($func1, $metricalData['coldWater']),
                            ['总计', (string)array_reduce($metricalData['coldWater'], $func2, 0)],
                        ]
                    ),
                    Util::htmlTable(
                        ['日期', '使用量'],
                        [
                            ...array_map($func1, $metricalData['hotWater']),
                            ['总计', (string)array_reduce($metricalData['hotWater'], $func2, 0)],
                        ]
                    ),
                    date('Y-m-d H:i:s')
                );
            } else {
                $this->writeJson(200, $metricalData, "{$room} 查询成功");
            }
        } catch (\Throwable $th) {
            if (isset($_GET['text'])) {
                header('Content-Type:text/plain');
                echo "查询失败：{$th->getMessage()}";
            } else if (isset($_GET['html'])) {
                echo "<p>查询失败：{$th->getMessage()}</p>";
            } else {
                $this->writeJson(400, null, "查询失败：{$th->getMessage()}");
            }
        }
    }
}