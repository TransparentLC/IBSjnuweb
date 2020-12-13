<?php
declare(strict_types=1);

namespace App;

use \GuzzleHttp\Client;
use \GuzzleHttp\Cookie\CookieJar;
use \phpseclib\Crypt\AES;

class Util {
    static ?AES $aes = null;

    static function getIBSClient(): Client {
        $client = new Client([
            'base_uri' => 'http://10.136.2.5/IBSjnuweb/WebService/JNUService.asmx/',
            'cookies' => new CookieJar,
            'headers' => [
                'Content-Type' => 'application/json;charset=utf-8',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; WOW64; Trident/7.0; rv:11.0) like Gecko',
                'X-Forwarded-For' => '127.0.0.1',
            ],
        ]);
        return $client;
    }

    static function generateIBSToken(int $userID): string {
        $timestamp = time();
        $arr = [
            'userID' => $userID,
            'tokenTime' => date('Y-m-d H:i:s', $timestamp),
        ];
        $encrypted = base64_encode(self::$aes->encrypt(json_encode($arr)));
        return (strlen($encrypted) > 64) ? join('%0A', str_split($encrypted, 64)) : ($encrypted . '%0A');
    }

    static function getIBSRequestHeader(int $userID): array {
        return [
            'Token' => self::generateIBSToken($userID),
            'DateTime' => date('Y-m-d H:i:s', time()),
        ];
    }
}

Util::$aes = new AES(AES::MODE_CBC);
Util::$aes->setKey('CetSoftEEMSysWeb');
Util::$aes->setIV("\x19\x34\x57\x72\x90\xAB\xCD\xEF\x12\x64\x14\x78\x90\xAC\xAE\x45");