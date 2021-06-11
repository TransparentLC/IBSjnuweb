<?php
declare(strict_types=1);

namespace App\HttpController;

use \App\RecordData;
use \App\Util;

class Record extends \App\Component\HttpController {
    function index() {
        $room = strtoupper($this->args['room']);

        try {
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

            try {
                $r = Util::getRedisClient();
                $r->hIncrBy('IBSjnuweb:Statistics:' . date('YmdH'), 'payment', 1);
                $r->expire('IBSjnuweb:Statistics:' . date('YmdH'), 604800);
            } catch (\Throwable $th) {}
        } catch (\Throwable $th) {
            switch ($_GET['format'] ?? null) {
                case 'text':
                case 'markdown':
                    http_response_code(400);
                    header('Content-Type:text/plain');
                    echo "查询失败：{$th->getMessage()}";
                    break;

                case 'html':
                    http_response_code(400);
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