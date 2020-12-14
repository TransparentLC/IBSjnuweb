<?php
declare(strict_types=1);

namespace App\HttpController;

use \App\RecordData;

class Record extends \App\Component\HttpController {
    function index() {
        $room = strtoupper($this->args['room']);

        try {
            $record = new RecordData(
                $room,
                empty($_GET['page']) ? 1 : (int)$_GET['page'],
                empty($_GET['count']) ? 10 : (int)$_GET['count']
            );
            if (isset($_GET['text'])) {
                header('Content-Type:text/plain');
                echo $record->toText();
            } else if (isset($_GET['html'])) {
                echo $record->toHtml();
            } else {
                $this->writeJson(200, $record->toArray(), "{$room} 查询成功");
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