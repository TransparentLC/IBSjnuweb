<?php
declare(strict_types=1);

namespace App;

use \App\Util;
use \GuzzleHttp\Promise;

class BillingData {
    private string $room;
    private array $data = [];

    public function __construct(string $room) {
        $this->room = strtoupper($room);

        $client = Util::getIBSClient($this->room);

        $response = Promise\Utils::unwrap([
            'info' => $client->postAsync('GetUserInfo'),
            'allowance' => $client->postAsync('GetSubsidy', [
                'body' => '{"startDate":"1000-01-01","endDate":"9999-12-31"}',
            ]),
            'bill' => $client->postAsync('GetBillCost', [
                'body' => '{"energyType":0,"startDate":"1000-01-01","endDate":"9999-12-31"}',
            ]),
        ]);
        $response = array_map(fn ($r) => json_decode($r->getBody()->getContents(), true), $response);
        if (array_search(false, array_map(fn ($e) => $e['d']['Success'], $response)) !== false) {
            throw new \Exception('Failed to fetch billing data');
        }

        $this->data['balance'] = (float)$response['info']['d']['ResultList'][0]['roomInfo'][1]['keyValue'];

        $allowanceElectricity = Util::arraySearch($response['allowance']['d']['ResultList'], fn ($e) => $e['itemType'] === 2);
        $allowanceColdWater = Util::arraySearch($response['allowance']['d']['ResultList'], fn ($e) => $e['itemType'] === 3);
        $allowanceHotWater = Util::arraySearch($response['allowance']['d']['ResultList'], fn ($e) => $e['itemType'] === 4);
        $emptyAllowanceData = [
            'total' => null,
            'available' => null,
        ];
        $this->data['allowance'] = [
            'electricity' => $allowanceElectricity ? [
                'total' => $allowanceElectricity['totalValue'],
                'available' => $allowanceElectricity['avalibleValue'],
            ] : $emptyAllowanceData,
            'coldWater' => $allowanceColdWater ? [
                'total' => $allowanceColdWater['totalValue'],
                'available' => $allowanceColdWater['avalibleValue'],
            ] : $emptyAllowanceData,
            'hotWater' => $allowanceHotWater ? [
                'total' => $allowanceHotWater['totalValue'],
                'available' => $allowanceHotWater['avalibleValue'],
            ] : $emptyAllowanceData,
        ];

        $billElectricity = Util::arraySearch($response['bill']['d']['ResultList'], fn ($e) => $e['energyType'] === 2);
        $billColdWater = Util::arraySearch($response['bill']['d']['ResultList'], fn ($e) => $e['energyType'] === 3);
        $billHotWater = Util::arraySearch($response['bill']['d']['ResultList'], fn ($e) => $e['energyType'] === 4);
        $emptyBillingData = [
            'price' => null,
            'start' => null,
            'current' => null,
            'usage' => null,
        ];
        $this->data['bill'] = [
            'electricity' => $billElectricity ? [
                'price' => $billElectricity['unitPrice'],
                'start' => $billElectricity['energyCostDetails'][0]['billItemValues'][0]['preValue'],
                'current' => $billElectricity['energyCostDetails'][0]['billItemValues'][0]['curValue'],
                'usage' => $billElectricity['energyCostDetails'][0]['billItemValues'][0]['energyValue'],
            ] : $emptyBillingData,
            'coldWater' => $billColdWater ? [
                'price' => $billColdWater['unitPrice'],
                'start' => $billColdWater['energyCostDetails'][0]['billItemValues'][0]['preValue'],
                'current' => $billColdWater['energyCostDetails'][0]['billItemValues'][0]['curValue'],
                'usage' => $billColdWater['energyCostDetails'][0]['billItemValues'][0]['energyValue'],
            ] : $emptyBillingData,
            'hotWater' => $billHotWater ? [
                'price' => $billHotWater['unitPrice'],
                'start' => $billHotWater['energyCostDetails'][0]['billItemValues'][0]['preValue'],
                'current' => $billHotWater['energyCostDetails'][0]['billItemValues'][0]['curValue'],
                'usage' => $billHotWater['energyCostDetails'][0]['billItemValues'][0]['energyValue'],
            ] : $emptyBillingData,
        ];
    }

    private function getFormatParamArray(): array {
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

            $this->data['balance'], ($this->data['balance'] < 30) ? ' **低于预警值 30 元，请及时充值！**' : '',

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

    public function toArray(): array {
        return $this->data;
    }

    public function toText(): string {
        $formatParam = $this->getFormatParamArray();
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

    function toHtml(): string {
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