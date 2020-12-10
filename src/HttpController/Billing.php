<?php
namespace App\HttpController;

use \App\BillingData;

class Billing extends \App\Component\HttpController {
    function index() {
        $room = strtoupper($this->args['room']);

        try {
            $billing = new BillingData($room);
            if (isset($_GET['text'])) {
                header('Content-Type:text/plain');
                echo $billing->toText();
            } else if (isset($_GET['html'])) {
                echo $billing->toHtml();
            } else {
                $this->writeJson(200, $billing->toArray(), "{$room} 查询成功");
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