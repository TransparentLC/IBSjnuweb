<?php
namespace App;

use \App\Util;
use \GuzzleHttp\Promise;

class BillingData {
    private string $room;
    private array $data = [];

    public function __construct(string $room) {
        $this->room = strtoupper($room);

        $client = Util::getIBSClient();
        $loginResponse = json_decode(
            $client->post('Login', [
                'body' => json_encode([
                    'user' => $this->room,
                    'password' => base64_encode(Util::$aes->encrypt($this->room)),
                ], JSON_UNESCAPED_UNICODE_SLASHES),
            ])->getBody()->getContents(),
            true
        );

        if (!$loginResponse['d']['Success']) {
            throw new \Exception('Failed to fetch billing data');
        }

        $userID = $loginResponse['d']['ResultList'][0]['customerId'];

        $response = Promise\Utils::unwrap([
            'info' => $client->postAsync('GetUserInfo', [
                'headers' => Util::getIBSRequestHeader($userID),
            ]),
            'allowance' => $client->postAsync('GetSubsidy', [
                'headers' => Util::getIBSRequestHeader($userID),
                'body' => '{"startDate":"1000-01-01","endDate":"9999-12-31"}',
            ]),
            'bill' => $client->postAsync('GetBillCost', [
                'headers' => Util::getIBSRequestHeader($userID),
                'body' => '{"energyType":0,"startDate":"1000-01-01","endDate":"9999-12-31"}',
            ]),
        ]);
        $response = array_map(fn ($r) => json_decode($r->getBody()->getContents(), true), $response);

        $this->data['balance'] = (float)$response['info']['d']['ResultList'][0]['roomInfo'][1]['keyValue'];

        $this->data['allowance'] = [
            'electricity' => [
                'total' => $response['allowance']['d']['ResultList'][0]['totalValue'],
                'available' => $response['allowance']['d']['ResultList'][0]['avalibleValue'],
            ],
            'coldWater' => [
                'total' => $response['allowance']['d']['ResultList'][1]['totalValue'],
                'available' => $response['allowance']['d']['ResultList'][1]['avalibleValue'],
            ],
            'hotWater' => [
                'total' => $response['allowance']['d']['ResultList'][2]['totalValue'],
                'available' => $response['allowance']['d']['ResultList'][2]['avalibleValue'],
            ],
        ];

        $this->data['bill'] = [
            'electricity' => [
                'price' => $response['bill']['d']['ResultList'][0]['unitPrice'],
                'start' => $response['bill']['d']['ResultList'][0]['energyCostDetails'][0]['billItemValues'][0]['preValue'],
                'current' => $response['bill']['d']['ResultList'][0]['energyCostDetails'][0]['billItemValues'][0]['curValue'],
                'usage' => $response['bill']['d']['ResultList'][0]['energyCostDetails'][0]['billItemValues'][0]['energyValue'],
            ],
            'coldWater' => [
                'price' => $response['bill']['d']['ResultList'][1]['unitPrice'],
                'start' => $response['bill']['d']['ResultList'][1]['energyCostDetails'][0]['billItemValues'][0]['preValue'],
                'current' => $response['bill']['d']['ResultList'][1]['energyCostDetails'][0]['billItemValues'][0]['curValue'],
                'usage' => $response['bill']['d']['ResultList'][1]['energyCostDetails'][0]['billItemValues'][0]['energyValue'],
            ],
            'hotWater' => [
                'price' => $response['bill']['d']['ResultList'][2]['unitPrice'],
                'start' => $response['bill']['d']['ResultList'][2]['energyCostDetails'][0]['billItemValues'][0]['preValue'],
                'current' => $response['bill']['d']['ResultList'][2]['energyCostDetails'][0]['billItemValues'][0]['curValue'],
                'usage' => $response['bill']['d']['ResultList'][2]['energyCostDetails'][0]['billItemValues'][0]['energyValue'],
            ],
        ];
    }

    private function getFormatParamArray() {
        $datetime = date('Y-m-d H:i:s');
        $fee = [
            'electricity' => round(
                max(
                    0,
                    $this->data['bill']['electricity']['usage']
                    - $this->data['allowance']['electricity']['total']
                    + $this->data['allowance']['electricity']['available']
                ) * $this->data['bill']['electricity']['price'],
                2
            ),
            'coldWater' => round(
                max(
                    0,
                    $this->data['bill']['coldWater']['usage']
                    - $this->data['allowance']['coldWater']['total']
                    + $this->data['allowance']['coldWater']['available']
                ) * $this->data['bill']['coldWater']['price'],
                2
            ),
            'hotWater' => round(
                max(
                    0,
                    $this->data['bill']['hotWater']['usage']
                    - $this->data['allowance']['hotWater']['total']
                    + $this->data['allowance']['hotWater']['available']
                ) * $this->data['bill']['hotWater']['price'],
                2
            ),
        ];

        return [
            $this->room,

            $this->data['balance'], ($this->data['balance'] < 30) ? '低于预警值 30 元，请及时充值！' : '',

            $this->data['allowance']['electricity']['available'], $this->data['allowance']['electricity']['total'],
            $this->data['allowance']['coldWater']['available'], $this->data['allowance']['coldWater']['total'],
            $this->data['allowance']['hotWater']['available'], $this->data['allowance']['hotWater']['total'],

            $this->data['bill']['electricity']['usage'],
            $this->data['bill']['electricity']['start'], $this->data['bill']['electricity']['current'],

            $this->data['bill']['coldWater']['usage'],
            $this->data['bill']['coldWater']['start'], $this->data['bill']['coldWater']['current'],

            $this->data['bill']['hotWater']['usage'],
            $this->data['bill']['hotWater']['start'], $this->data['bill']['hotWater']['current'],

            $fee['electricity'], $this->data['bill']['electricity']['price'],
            $fee['coldWater'], $this->data['bill']['coldWater']['price'],
            $fee['hotWater'], $this->data['bill']['hotWater']['price'],
            array_sum($fee),

            $datetime,
        ];
    }

    public function toArray() {
        return $this->data;
    }

    public function toText() {
        $formatParam = $this->getFormatParamArray();
        if (!empty($formatParam[2])) $formatParam[2] = " **{$formatParam[2]}**";
        $template = <<< 'AKARIN'
%s 水电费数据
================================

余额：%s 元%s

补贴（剩余量/总量）：
* 电能 %s / %s 度
* 冷水 %s / %s 吨
* 热水 %s / %s 吨

使用量（读数从月初开始计算）：
* 电能 %s 度 (%s -> %s)
* 冷水 %s 吨 (%s -> %s)
* 热水 %s 吨 (%s -> %s)

费用（已减去补贴额，计算值仅供参考）：
* 电能 %s 元，单价 %s 元/度
* 冷水 %s 元，单价 %s 元/吨
* 热水 %s 元，单价 %s 元/吨
* 以上共计 %s 元

> 数据获取时间：%s
>
> Powered by Akarin ⁄(⁄⁄•⁄ω⁄•⁄⁄)⁄

AKARIN;
        return sprintf($template, ...$formatParam);
    }

    function toHtml() {
        $formatParam = $this->getFormatParamArray();
        if (!empty($formatParam[2])) $formatParam[2] = " <strong style=\"color:red\">{$formatParam[2]}</strong>";
        /*
            <h1>%s 水电费数据</h1>
            <p>余额：%s 元%s</p>
            <p>补贴（剩余量/总量）：</p>
            <ul>
            <li>电能 %s / %s 度</li>
            <li>冷水 %s / %s 吨</li>
            <li>热水 %s / %s 吨</li>
            </ul>
            <p>使用量（读数从月初开始计算）：</p>
            <ul>
            <li>电能 %s 度 (%s -&gt; %s)</li>
            <li>冷水 %s 吨 (%s -&gt; %s)</li>
            <li>热水 %s 吨 (%s -&gt; %s)</li>
            </ul>
            <p>费用（已减去补贴额，计算值仅供参考）：</p>
            <ul>
            <li>电能 %s 元，单价 %s 元/度</li>
            <li>冷水 %s 元，单价 %s 元/吨</li>
            <li>热水 %s 元，单价 %s 元/吨</li>
            <li>以上共计 %s 元</li>
            </ul>
            <blockquote>
            <p>数据获取时间：%s</p>
            <p>Powered by Akarin ⁄(⁄⁄•⁄ω⁄•⁄⁄)⁄</p>
            </blockquote>
        */
        $template = '<h1>%s 水电费数据</h1><p>余额：%s 元%s</p><p>补贴（剩余量/总量）：</p><ul><li>电能 %s / %s 度</li><li>冷水 %s / %s 吨</li><li>热水 %s / %s 吨</li></ul><p>使用量（读数从月初开始计算）：</p><ul><li>电能 %s 度 (%s -&gt; %s)</li><li>冷水 %s 吨 (%s -&gt; %s)</li><li>热水 %s 吨 (%s -&gt; %s)</li></ul><p>费用（已减去补贴额，计算值仅供参考）：</p><ul><li>电能 %s 元，单价 %s 元/度</li><li>冷水 %s 元，单价 %s 元/吨</li><li>热水 %s 元，单价 %s 元/吨</li><li>以上共计 %s 元</li></ul><blockquote><p>数据获取时间：%s</p><p>Powered by Akarin ⁄(⁄⁄•⁄ω⁄•⁄⁄)⁄</p></blockquote>';
        return sprintf($template, ...$formatParam);
    }
}