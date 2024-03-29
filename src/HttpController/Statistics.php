<?php
declare(strict_types=1);

namespace App\HttpController;

use \App\Util;
use \DateTime;
use \GuzzleHttp\Client;

class Statistics extends \App\Component\HttpController {
    function index() {
        try {
            $r = Util::getRedisClient();
        } catch (\Throwable $th) {
            $this->writeJson(500, null, 'Redis 扩展未安装或未正确配置');
            return;
        }

        $keys = [];
        $scanIter = null;
        $k = false;
        do {
            $k = $r->scan($scanIter, 'IBSjnuweb:Statistics:*');
            if (is_array($k)) {
                $keys = array_merge($keys, $k);
            }
        } while ($k);

        $statistics = array_map(function (string $e) use ($r) {
            $s = [
                'time' => (int)DateTime::createFromFormat('YmdH', explode(':', $e)[2])->format('U'),
                'billing' => 0,
                'payment' => 0,
                'metrical' => 0,
            ];
            foreach ($r->hGetAll($e) as $key => $value) {
                if (isset($s[$key])) {
                    $s[$key] = (int)$value;
                }
            }
            return $s;
        }, $keys);
        if (count($statistics)) {
            $times = [];
            foreach ($statistics as $value) {
                $times[$value['time']] = 0;
            }
            $minTime = min(array_keys($times));
            $maxTime = time();
            while ($minTime < $maxTime) {
                if (!isset($times[$minTime])) {
                    $times[$minTime] = 0;
                    array_push($statistics, [
                        'time' => $minTime,
                        'billing' => 0,
                        'payment' => 0,
                        'metrical' => 0,
                    ]);
                }
                $minTime += 3600;
            }
        }
        usort($statistics, fn ($a, $b) => $a['time'] <=> $b['time']);
        array_pop($statistics);

        $chartData = [
            'type' => 'line',
            'options' => [
                'title' => [
                    'display' => true,
                    'text' => 'API 请求次数统计',
                ],
                'scales' => [
                    'yAxes' => [
                        [
                            'ticks' => [
                                'stepSize' => 1,
                            ],
                        ],
                    ],
                    'xAxes' => [
                        [
                            'offset' => true,
                            'type' => 'time',
                            'time' => [
                                'displayFormats' => [
                                    'hour' => 'YYYY-MM-DD HH:00',
                                ],
                                'unit' => 'hour',
                            ],
                        ],
                    ],
                ],
            ],
            'data' => [
                'datasets' => [
                    [
                        'label' => '水电费余额和读数',
                        'data' => array_map(fn ($e) => ['x' => date('Y-m-d H:00', $e['time']), 'y' => $e['billing']], $statistics),
                        'fill' => false,
                        'cubicInterpolationMode' => 'monotone',
                        'lineTension' => .5,
                        'borderColor' => '#2d98da',
                        'pointBackgroundColor' => '#2d98da',
                    ],
                    [
                        'label' => '充值记录',
                        'data' => array_map(fn ($e) => ['x' => date('Y-m-d H:00', $e['time']), 'y' => $e['payment']], $statistics),
                        'fill' => false,
                        'cubicInterpolationMode' => 'monotone',
                        'lineTension' => .5,
                        'borderColor' => '#20bf6b',
                        'pointBackgroundColor' => '#20bf6b',
                    ],
                    [
                        'label' => '耗能记录',
                        'data' => array_map(fn ($e) => ['x' => date('Y-m-d H:00', $e['time']), 'y' => $e['metrical']], $statistics),
                        'fill' => false,
                        'cubicInterpolationMode' => 'monotone',
                        'lineTension' => .5,
                        'borderColor' => '#eb3b5a',
                        'pointBackgroundColor' => '#eb3b5a',
                    ],
                ],
            ],
        ];

        $chartDataHash = hash('sha256', json_encode($chartData, JSON_UNESCAPED_UNICODE_SLASHES), true);
        $chartUrlCache = $r->get('IBSjnuweb:ChartUrlCache');
        if ($chartUrlCache === false || substr($chartUrlCache, 0, strlen($chartDataHash)) !== $chartDataHash) {
            $client = new Client;
            $chartImage = $client
                ->post(
                    'https://quickchart.io/chart',
                    [
                        'json' => [
                            'width' => 1080,
                            'height' => 480,
                            'format' => Util::$config['svgChart'] ? 'svg' : 'png',
                            'backgroundColor' => '#fff',
                            'chart' => $chartData,
                        ],
                    ]
                )
                ->getBody()
                ->getContents();
            if (Util::$config['svgChart']) {
                $chartCompressed = $client
                    ->post(
                        'https://vector.express/api/v2/public/convert/svg/svgo/svg/?svgo-precision=2',
                        [
                            'body' => $chartImage
                        ]
                    )
                    ->getBody()
                    ->getContents();
                $chartUrl = 'data:image/svg+xml;utf8,' . rawurlencode($client->get(json_decode($chartCompressed, true)['resultUrl'])->getBody()->getContents());
            } else {
                $chartCompressed = $client
                    ->post(
                        'https://tinypng.com/web/shrink',
                        [
                            'body' => $chartImage,
                        ]
                    )
                    ->getBody()
                    ->getContents();
                $chartCompressed = json_decode($chartCompressed, true)['output']['url'];
                $chartImage = $client->get($chartCompressed)->getBody()->getContents();
                $chartUrl = $client
                    ->post(
                        'https://yzf.qq.com/fsnb/kf-file/upload_wx_media',
                        [
                            'multipart' => [
                                [
                                    'name' => 'mid',
                                    'contents' => 'fsnb',
                                ],
                                [
                                    'name' => 'media_type',
                                    'contents' => 'image',
                                ],
                                [
                                    'name' => 'userid',
                                    'contents' => 'kf' . Util::randomString(16) . '_' . Util::randomString(2),
                                ],
                                [
                                    'name' => 'file',
                                    'contents' => $chartImage,
                                    'filename' => Util::randomString(16) . '.png',
                                ],
                            ],
                        ]
                    )
                    ->getBody()
                    ->getContents();
                $chartUrl = preg_replace('/\?.*$/', '', urldecode(json_decode($chartUrl, true)['KfPicUrl']));
            }

            $r->set('IBSjnuweb:ChartUrlCache', $chartDataHash . $chartUrl, 3600);
        } else {
            $chartUrl = substr($chartUrlCache, strlen($chartDataHash));
        }

        $this->writeJson(200, [
            'chart' => $chartUrl,
            'statistics' => $statistics,
        ]);
    }
}