<?php
declare(strict_types=1);

namespace App;

use \Exception;
use \GuzzleHttp\Client;
use \GuzzleHttp\Cookie\CookieJar;
use \Redis;
use \phpseclib3\Crypt\AES;

class Util {
    static AES $aes;

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

    static function doIBSLogin(Client $client, string $user): int {
        $loginResponse = json_decode(
            $client->post('Login', [
                'body' => json_encode([
                    'user' => $user,
                    'password' => base64_encode(static::$aes->encrypt($user)),
                ], JSON_UNESCAPED_UNICODE_SLASHES),
            ])->getBody()->getContents(),
            true
        );

        if (!$loginResponse['d']['Success']) {
            throw new \Exception('Invalid user');
        }

        return $loginResponse['d']['ResultList'][0]['customerId'];
    }

    static function markdownTable(array $head, array $rows): string {
        $_rows = [$head, ...$rows];
        $rowLength = array_map(
            fn ($e) => array_map(
                fn ($t) => strlen(mb_convert_encoding($t, 'gbk', 'utf-8')),
                $e
            ),
            $_rows
        );
        $rowMaxLength = array_map(
            fn ($e) => max(
                array_map(
                    fn ($t) => $t[$e],
                    $rowLength
                )
            ),
            range(0, count($head) - 1)
        );
        for ($j = 0; $j < count($head); $j++) {
            for ($i = 0; $i < count($_rows); $i++) {
                $_rows[$i][$j] .= str_repeat(' ', $rowMaxLength[$j] - $rowLength[$i][$j]);
            }
        }
        array_splice(
            $_rows,
            1,
            0,
            [
                array_map(
                    fn ($e) => str_repeat('-', $rowMaxLength[$e]),
                    range(0, count($head) - 1)
                )
            ]
        );
        return join(
            "\n",
            array_map(
                fn ($e) => '| ' . join(' | ', $e) . ' |',
                $_rows
            )
        );
    }

    static function htmlTable(array $head, array $rows): string {
        $head = join('', array_map(fn ($e) => "<th>{$e}</th>", $head));
        $rows = join('', array_map(fn ($e) => '<tr>' . join('', array_map(fn ($t) => "<td>{$t}</td>", $e)) . '</tr>', $rows));
        return "<table><thead><tr>{$head}</tr></thead><tbody>{$rows}</tbody></table>";
    }

    static function arraySearch(array $arr, callable $func) {
        foreach ($arr as $key => $value) {
            if ($func($value, $key)) {
                return $value;
            }
        }
        return null;
    }

    static function getRedisClient(): Redis {
        if (!extension_loaded('redis')) {
            throw new Exception('Redis extension is required');
        }
        if (!is_file(PHAR_PATH . '/redis.config')) {
            throw new Exception('Redis config file (' . PHAR_PATH . '/redis.config) is not found');
        }

        list($host, $port, $auth) = explode(':', file_get_contents(PHAR_PATH . '/redis.config'));
        $r = new Redis;
        if (substr($host, -strlen('.sock')) || empty($port)) {
            $r->pconnect($host);
        } else {
            $r->pconnect($host, $port);
        }
        if (!empty($auth)) {
            $r->auth($auth);
        }
        return $r;
    }
}

Util::$aes = new AES('CBC');
Util::$aes->setKey('CetSoftEEMSysWeb');
Util::$aes->setIV("\x19\x34\x57\x72\x90\xAB\xCD\xEF\x12\x64\x14\x78\x90\xAC\xAE\x45");