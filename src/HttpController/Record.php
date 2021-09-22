<?php
declare(strict_types=1);

namespace App\HttpController;

use \App\RecordData;
use \App\Util;

class Record extends \App\Component\HttpController {
    function index() {
        try {
            $r = Util::getRedisClient();
            $rateLimitRemain = Util::getRateLimitRemaining($r);
        } catch (\Throwable $th) {
            /** @var \Redis */
            $r = null;
            $rateLimitRemain = PHP_INT_MAX;
        }

        try {
            if ($rateLimitRemain <= 0) {
                $responseCode = 429;
                throw new \Exception('当前 IP ' . Util::getIp() . ' 请求次数过多，请在 ' . date('Y-m-d H:i:s', Util::getRateLimitResetTime($r)) . ' 后重试（可以检查 X-RateLimit-* 响应头获取请求次数限制信息）');
            }

            $room = strtoupper($this->args['room']);
            $record = new RecordData(
                $room,
                empty($_GET['page']) ? 1 : (int)$_GET['page'],
                empty($_GET['count']) ? 10 : (int)$_GET['count']
            );

            switch ($_GET['format'] ?? null) {
                case 'text':
                case 'markdown':
                    header('Content-Type:text/plain');
                    echo $record->toText();
                    break;

                case 'html':
                    echo $record->toHtml();
                    break;

                case 'json':
                default:
                    $this->writeJson(200, $record->toArray(), "{$room} 查询成功");
                    break;
            }

            if ($r) {
                $r->hIncrBy('IBSjnuweb:Statistics:' . date('YmdH'), 'payment', 1);
                $r->expire('IBSjnuweb:Statistics:' . date('YmdH'), 604800);
            }
        } catch (\Throwable $th) {
            http_response_code($responseCode ?? 400);
            switch ($_GET['format'] ?? null) {
                case 'text':
                case 'markdown':
                    header('Content-Type:text/plain');
                    echo "查询失败：{$th->getMessage()}";
                    break;

                case 'html':
                    echo "<p>查询失败：{$th->getMessage()}</p>";
                    break;

                case 'json':
                default:
                    $this->writeJson($responseCode ?? 400, null, "查询失败：{$th->getMessage()}");
                    break;
            }
        }

        if ($r) {
            if (Util::getRateLimitRemaining($r) > 0) {
                Util::decrRateLimit($r);
            }
            Util::setRateLimitHeaders($r);
        }
    }
}