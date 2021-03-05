<?php
declare(strict_types=1);

namespace App\HttpController;

use \App\BillingData;

class Billing extends \App\Component\HttpController {
    function index() {
        $room = strtoupper($this->args['room']);

        try {
            $billing = new BillingData($room);

            switch ($_GET['format'] ?? null) {
                case 'text':
                case 'markdown':
                    header('Content-Type:text/plain');
                    echo $billing->toText();
                    break;

                case 'html':
                    echo $billing->toHtml();
                    break;

                case 'json':
                default:
                    $this->writeJson(200, $billing->toArray(), "{$room} 查询成功");
                    break;
            }
        } catch (\Throwable $th) {
            switch ($_GET['format'] ?? null) {
                case 'text':
                case 'markdown':
                case 'chart':
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