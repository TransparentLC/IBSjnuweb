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
use function \AlecRabbit\Helpers\wcswidth;

class Util {
    static AES $aes;
    static Array $config;

    static function getIBSClient(string $room): Client {
        $cookieJar = new CookieJar;
        $handlerStack = HandlerStack::create(new CurlMultiHandler);
        $client = new Client([
            'base_uri' => 'http://10.136.2.5/IBSjnuweb/WebService/JNUService.asmx/',
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
            $sc->setDomain('10.136.2.5');
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
            fn ($e) => array_map(fn ($t) => wcswidth($t), $e),
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
        if (empty(self::$config['redis'])) {
            throw new Exception('Redis-related config is missing in config file');
        }

        $host = self::$config['redis']['host'];
        $port = self::$config['redis']['port'];
        $auth = self::$config['redis']['auth'];
        $db = self::$config['redis']['db'];

        $r = new Redis;
        if (substr($host, -strlen('.sock')) === '.sock') {
            $r->pconnect($host);
        } else {
            $r->pconnect($host, $port);
        }
        if (!empty($auth)) {
            $r->auth($auth);
        }
        $r->select($db);
        return $r;
    }

    static function randomString(int $length, string $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'): string {
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $result;
    }

    static function getIp(): string {
        return empty($_SERVER['HTTP_X_REAL_IP']) ? $_SERVER['REMOTE_ADDR'] : $_SERVER['HTTP_X_REAL_IP'];
    }

    static function getRateLimitRemaining(Redis $r): int {
        if (empty(self::$config['rateLimiting'])) return PHP_INT_MAX;
        $ip = self::getIp();
        $ipKey = 'IBSjnuweb:RateLimiting:' . base64_encode(inet_pton($ip));

        $remain = $r->get($ipKey);
        if ($remain !== false) return (int)$remain;

        $rateLimitRule = empty(self::$config['rateLimiting']['extra'][$ip]) ?
            self::$config['rateLimiting'] :
            self::$config['rateLimiting']['extra'][$ip];
        $r->set($ipKey, $rateLimitRule['limit'], $rateLimitRule['window']);
        return $rateLimitRule['limit'];
    }

    static function getRateLimitResetTime(Redis $r): int {
        if (empty(self::$config['rateLimiting'])) return 0;
        $ip = self::getIp();
        $ipKey = 'IBSjnuweb:RateLimiting:' . base64_encode(inet_pton($ip));

        $expire = $r->ttl($ipKey);
        if ($expire !== false) return time() + (int)$expire;

        $r->set($ipKey, self::$config['rateLimiting']['limit'], self::$config['rateLimiting']['window']);
        return time() + self::$config['rateLimiting']['window'];
    }

    static function decrRateLimit(Redis $r) {
        if (empty(self::$config['rateLimiting'])) return;
        $r->decr('IBSjnuweb:RateLimiting:' . base64_encode(inet_pton(self::getIp())));
    }

    static function setRateLimitHeaders(Redis $r) {
        if (empty(self::$config['rateLimiting'])) return;
        $ip = self::getIp();
        $rateLimitRule = empty(self::$config['rateLimiting']['extra'][$ip]) ?
            self::$config['rateLimiting'] :
            self::$config['rateLimiting']['extra'][$ip];
        header('X-RateLimit-Limit:' . $rateLimitRule['limit']);
        header('X-RateLimit-Window:' . $rateLimitRule['window']);
        header('X-RateLimit-Remaining:' . self::getRateLimitRemaining($r));
        header('X-RateLimit-Reset:' . self::getRateLimitResetTime($r));
    }
}

Util::$aes = new AES('CBC');
Util::$aes->setKey('CetSoftEEMSysWeb');
Util::$aes->setIV("\x19\x34\x57\x72\x90\xAB\xCD\xEF\x12\x64\x14\x78\x90\xAC\xAE\x45");

Util::$config = json_decode(file_get_contents(PHAR_PATH . '/config.json'), true);
$rateLimitingExtra = [];
foreach (Util::$config['rateLimiting']['extra'] as $e) {
    $rateLimitingExtra[$e['ip']] = $e;
}
Util::$config['rateLimiting']['extra'] = $rateLimitingExtra;