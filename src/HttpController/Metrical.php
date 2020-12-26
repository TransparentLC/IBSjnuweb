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

            switch ($_GET['format'] ?? null) {
                case 'text':
                case 'markdown':
                    header('Content-Type:text/plain');
                    #region
                    $template = <<< 'AKARIN'
%s %s耗能记录
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
                    #endregion
                    echo sprintf(
                        $template,
                        $room,
                        $month ? "{$year} 年 {$month} 月" : "{$year} 年",
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
                    break;

                case 'html':
                    /*
                        <h1>%s %s耗能记录</h1>
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
                    $template = '<h1>%s %s耗能记录</h1><p>电能使用记录（度）：</p>%s<p>冷水使用记录（吨）：</p>%s<p>热水使用记录（吨）：</p>%s<blockquote><p>数据获取时间：%s</p><p>Powered by Akarin ⁄(⁄⁄•⁄ω⁄•⁄⁄)⁄</p></blockquote>';
                    echo sprintf(
                        $template,
                        $room,
                        $month ? "{$year} 年 {$month} 月" : "{$year} 年",
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
                    break;

                case 'chart':
                case 'graph':
                    $func3 = fn ($e) => [
                        'x' => date($month ? 'Y-m-d' : 'Y-m', $e['time']),
                        'y' => $e['usage'],
                    ];
                    header('Location:https://quickchart.io/chart?' . http_build_query([
                        'w' => $month ? 1440 : 1080,
                        'h' => 480,
                        'f' => (strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false) ? 'webp' : 'png',
                        'c' => json_encode([
                            'type' => 'line',
                            'options' => [
                                'title' => [
                                    'display' => true,
                                    'text' => sprintf(
                                        '%s %s耗能记录',
                                        $room,
                                        $month ? "{$year} 年 {$month} 月" : "{$year} 年"
                                    ),
                                ],
                                'scales' => [
                                    'xAxes' => [
                                        [
                                            'offset' => true,
                                            'type' => 'time',
                                            'time' => [
                                                'displayFormats' => [
                                                    'day' => 'YYYY-MM-DD',
                                                    'month' => 'YYYY-MM',
                                                ],
                                                'unit' => $month ? 'day' : 'month'
                                            ],
                                        ],
                                    ],
                                    'yAxes' => [
                                        [
                                            'id' => 0,
                                            'position' => 'left',
                                            'scaleLabel' => [
                                                'display' => true,
                                                'labelString' => '电能（度）'
                                            ],
                                            'ticks' => [
                                                'min' => 0,
                                            ],
                                        ],
                                        [
                                            'id' => 1,
                                            'position' => 'right',
                                            'scaleLabel' => [
                                                'display' => true,
                                                'labelString' => '用水量（吨）'
                                            ],
                                            'ticks' => [
                                                'min' => 0,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            'data' => [
                                'datasets' => [
                                    [
                                        'yAxisID' => 0,
                                        'label' => '电能',
                                        'data' => array_map($func3, $metricalData['electricity']),
                                        'fill' => false,
                                        'cubicInterpolationMode' => 'monotone',
                                        'lineTension' => .5,
                                        'borderColor' => '#4bc0c0',
                                        'pointBackgroundColor' => '#4bc0c0',
                                    ],
                                    [
                                        'yAxisID' => 1,
                                        'label' => '冷水',
                                        'data' => array_map($func3, $metricalData['coldWater']),
                                        'fill' => false,
                                        'cubicInterpolationMode' => 'monotone',
                                        'lineTension' => .5,
                                        'borderColor' => '#36a2eb',
                                        'pointBackgroundColor' => '#36a2eb',
                                    ],
                                    [
                                        'yAxisID' => 1,
                                        'label' => '热水',
                                        'data' => array_map($func3, $metricalData['hotWater']),
                                        'fill' => false,
                                        'cubicInterpolationMode' => 'monotone',
                                        'lineTension' => .5,
                                        'borderColor' => '#ff6384',
                                        'pointBackgroundColor' => '#ff6384',
                                    ],
                                ],
                            ],
                        ], JSON_UNESCAPED_UNICODE_SLASHES),
                    ]), true, 302);
                    break;

                case 'json':
                default:
                    $this->writeJson(200, $metricalData, "{$room} 查询成功");
                    break;
            }
        } catch (\Throwable $th) {
            switch ($_GET['format'] ?? null) {
                case 'text':
                case 'markdown':
                case 'chart':
                case 'graph':
                    header('Content-Type:text/plain');
                    echo "查询失败：{$th->getMessage()}";
                    break;

                case 'html':
                    echo "<p>查询失败：{$th->getMessage()}</p>";
                    break;

                case 'json':
                default:
                    $this->writeJson(400, null, "查询失败：{$th->getMessage()}");
                    break;
            }
        }
    }
}