<?php
declare(strict_types=1);

namespace App;

use \Exception;
use \GuzzleHttp\Client;
use \GuzzleHttp\Cookie\CookieJar;
use \GuzzleHttp\Cookie\SetCookie;
use \GuzzleHttp\Handler\CurlMultiHandler;
use \GuzzleHttp\HandlerStack;
use \GuzzleHttp\Middleware;
use \Redis;
use \Psr\Http\Message\RequestInterface;
use \phpseclib3\Crypt\AES;

class Util {
    static AES $aes;

    static function getIBSClient(string $room): Client {
        $cookieJar = new CookieJar;
        $handlerStack = HandlerStack::create(new CurlMultiHandler);
        $client = new Client([
            'base_uri' => 'https://pynhcx.jnu.edu.cn/ibsjnuweb/WebService/JNUService.asmx/',
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'cookies' => $cookieJar,
            'handler' => $handlerStack,
        ]);


        $room = strtoupper($room);
        /** @var string */
        $cookieSessionID = null;
        /** @var int */
        $userID = null;
        /** @var Redis */
        $r = null;
        try {
            $r = static::getRedisClient();
            $sessionCache = $r->get('IBSjnuweb:RoomSessionCache:' . $room);
            if ($sessionCache) {
                list($cookieSessionID, $userID) = explode(':', $sessionCache);
                $userID = (int)$userID;
            };
        } catch (\Throwable $th) {}

        if ($cookieSessionID && $userID) {
            $sc = new SetCookie;
            $sc->setName('ASP.NET_SessionId');
            $sc->setValue($cookieSessionID);
            $sc->setDomain('pynhcx.jnu.edu.cn');
            $sc->setPath('/');
            $cookieJar->setCookie($sc);
        } else {
            $loginResponse = json_decode(
                $client->post('Login', [
                    'body' => json_encode([
                        'user' => $room,
                        'password' => base64_encode(static::$aes->encrypt($room)),
                    ], JSON_UNESCAPED_UNICODE_SLASHES),
                ])->getBody()->getContents(),
                true
            );
            if (!$loginResponse['d']['Success']) {
                throw new \Exception('Invalid room');
            }
            $cookieSessionID = $cookieJar->getCookieByName('ASP.NET_SessionId')->getValue();
            /** @var int */
            $userID = $loginResponse['d']['ResultList'][0]['customerId'];

            if ($r) {
                $r->set(
                    'IBSjnuweb:RoomSessionCache:' . $room,
                    join(':', [$cookieSessionID, $userID]),
                    1200
                );
            }
        }

        $handlerStack->push(Middleware::mapRequest(function (RequestInterface $request) use ($userID) {
            $timestamp = time();
            $datetime = date('Y-m-d H:i:s', $timestamp);
            $token = base64_encode(
                self::$aes->encrypt(
                    json_encode([
                        'userID' => $userID,
                        'tokenTime' => $datetime,
                    ])
                )
            );
            return $request
                ->withHeader('Token', $token)
                ->withHeader('DateTime', $datetime);
        }));
        return $client;
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
        if (substr($host, -strlen('.sock')) === '.sock' || empty($port)) {
            $r->pconnect($host);
        } else {
            $r->pconnect($host, $port);
        }
        if (!empty($auth)) {
            $r->auth($auth);
        }
        return $r;
    }

    static function randomString(int $length, string $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'): string {
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $result;
    }
}

Util::$aes = new AES('CBC');
Util::$aes->setKey('CetSoftEEMSysWeb');
Util::$aes->setIV("\x19\x34\x57\x72\x90\xAB\xCD\xEF\x12\x64\x14\x78\x90\xAC\xAE\x45");