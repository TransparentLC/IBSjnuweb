<?php
declare(strict_types=1);

define('IS_PHAR', (bool)Phar::running());
define('PHAR_PATH', IS_PHAR ? dirname(Phar::running(false)) : __DIR__);
define('APP_PATH', IS_PHAR ? Phar::running() : __DIR__);
define('JSON_UNESCAPED_UNICODE_SLASHES', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!defined('GIT_COMMIT_HASH')) define('GIT_COMMIT_HASH', null);
if (!defined('GIT_COMMIT_HASH_SHORT')) define('GIT_COMMIT_HASH_SHORT', null);
if (!defined('GIT_COMMIT_TIMESTAMP')) define('GIT_COMMIT_TIMESTAMP', null);

require_once APP_PATH . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    $parsed = parse_url(PHP_SAPI === 'cli-server' ? $_SERVER['REQUEST_URI'] : urldecode($_SERVER['QUERY_STRING']));
    $path = $parsed['path'];
    $query = isset($parsed['query']) ? $parsed['query'] : '';
    parse_str($query, $_GET);

    $dispatcher = \FastRoute\simpleDispatcher(function (\FastRoute\RouteCollector $r) {
        $r->addGroup('/api', function (\FastRoute\RouteCollector $r) {
            $r->addRoute('GET', '/version', 'Version/index');
            $r->addRoute('GET', '/billing/{room}', 'Billing/index');
            $r->addRoute('GET', '/payment-record/{room}', 'Record/index');
            $r->addRoute('GET', '/metrical-data/{room}/{year:(?:19|20)\d{2}}[-{month:(?:0?[1-9]|1[012])}]', 'Metrical/index');
        });
    });

    if (
        PHP_SAPI === 'cli-server' &&
        (
            is_file($pathStatic = APP_PATH . "/public{$path}") ||
            (preg_match_all('/\/$/', $path) && is_file($pathStatic = APP_PATH . "/public{$path}index.html"))
        )
    ) {
        header('Content-Type:' . mime_content_type($pathStatic));
        readfile($pathStatic);
        die;
    }

    $routeInfo = $dispatcher->dispatch($_SERVER['REQUEST_METHOD'], $path);
    switch ($routeInfo[0]) {
        case \FastRoute\Dispatcher::NOT_FOUND:
            http_response_code(404);
            break;
        case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
            http_response_code(405);
            break;
        case \FastRoute\Dispatcher::FOUND:
            $handler = $routeInfo[1];
            $args = $routeInfo[2];
            list($class, $method) = explode('/', $handler, 2);
            $class = '\\App\\HttpController\\' . $class;
            (new $class($method, $args));
            break;
    }
}
