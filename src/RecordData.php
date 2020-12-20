<?php
declare(strict_types=1);

namespace App;

use \App\Util;

class RecordData {
    private string $room;
    private int $page;
    private int $count;
    private int $total;
    private int $pageCount;
    /** @var RecordItem[] */
    private array $records = [];

    public function __construct(string $room, int $page = 1, int $count = 10) {
        $this->room = strtoupper($room);
        $this->page = (int)max(1, $page);
        $this->count = (int)min(100, $count);

        $client = Util::getIBSClient();
        $userID = Util::doIBSLogin($client, $this->room);

        $response = json_decode($client->post('GetPaymentRecord', [
            'headers' => Util::getIBSRequestHeader($userID),
            'body' => json_encode([
                'startIdx' => ($this->page - 1) * $this->count,
                'recordCount' => $this->count,
            ], JSON_UNESCAPED_UNICODE_SLASHES),
        ])->getBody()->getContents(), true);
        if (!$response['d']['Success']) {
            throw new \Exception('Failed to fetch record data');
        }

        $this->total = $response['d']['TotalCounts'];
        $this->pageCount = (int)ceil($this->total / $this->count);
        $this->records = array_map(
            fn ($e) => new RecordItem(
                (int)($e['logTime'] / 1000),
                "{$e['paymentType']} ({$e['itemType']})",
                $e['dataValue']
            ),
            $response['d']['ResultList']
        );
    }

    public function toArray(): array {
        return [
            'total' => $this->total,
            'pageCount' => $this->pageCount,
            'records' => array_map(fn ($e) => [
                'time' => $e->time,
                'event' => $e->event,
                'amount' => $e->amount,
            ], $this->records),
        ];
    }

    public function toText(): string {
        $template = <<< 'AKARIN'
%s 水电费充值记录（第 %s/%s 页）
================================

%s

> 数据获取时间：%s
>
> Powered by Akarin ⁄(⁄⁄•⁄ω⁄•⁄⁄)⁄

AKARIN;

        $table = Util::markdownTable(
            ['时间', '充值类型', '金额'],
            array_map(
                fn ($e) => [date('Y-m-d H:i:s', $e->time), $e->event, (string)$e->amount],
                $this->records
            )
        );
        return sprintf(
            $template,
            $this->room,
            $this->page,
            $this->pageCount,
            $table,
            // join("\n", $rows),
            date('Y-m-d H:i:s'),
        );
    }

    public function toHtml(): string {
        /*
            <h1>%s 水电费充值记录（第 %s/%s 页）</h1>
            <table>
                <thead>
                    <tr>
                        <th>时间</th>
                        <th>充值类型</th>
                        <th>金额</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>2020-12-01 01:41:55</td>
                        <td>补贴发放 (热水)</td>
                        <td>200</td>
                    </tr>
                </tbody>
            </table>
            <blockquote>
                <p>数据获取时间：%s</p>
                <p>Powered by Akarin ⁄(⁄⁄•⁄ω⁄•⁄⁄)⁄</p>
            </blockquote>
        */
        $template = '<h1>%s 水电费充值记录（第 %s/%s 页）</h1><table><thead><tr><th>时间</th><th>充值类型</th><th>金额</th></tr></thead><tbody>%s</tbody></table><blockquote><p>数据获取时间：%s</p><p>Powered by Akarin ⁄(⁄⁄•⁄ω⁄•⁄⁄)⁄</p></blockquote>';
        return sprintf(
            $template,
            $this->room,
            $this->page,
            $this->pageCount,
            join(
                '',
                array_map(
                    fn ($e) => '<tr><td>' . date('Y-m-d H:i:s', $e->time) . '</td><td>' . $e->event . '</td><td>' . $e->amount . '</td></tr>',
                    $this->records
                )
            ),
            date('Y-m-d H:i:s')
        );
    }
}

class RecordItem {
    public int $time;
    public string $event;
    public float $amount;

    public function __construct(int $time, string $event, float $amount) {
        $this->time = $time;
        $this->event = $event;
        $this->amount = $amount;
    }
}