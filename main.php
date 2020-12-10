<?php
define('IS_PHAR', (bool)Phar::running());
define('PHAR_PATH', IS_PHAR ? dirname(Phar::running(false)) : __DIR__);
define('APP_PATH', IS_PHAR ? Phar::running() : __DIR__);
define('JSON_UNESCAPED_UNICODE_SLASHES', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

require_once APP_PATH . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli') {
    $parsed = parse_url(urldecode($_SERVER['QUERY_STRING']));
    $path = $parsed['path'];
    $query = isset($parsed['query']) ? $parsed['query'] : '';
    parse_str($query, $_GET);

    $dispatcher = \FastRoute\simpleDispatcher(function (\FastRoute\RouteCollector $r) {
        $r->addGroup('/api', function (\FastRoute\RouteCollector $r) {
            $r->addRoute('GET', '/billing/{room}', 'Billing/index');
        });
    });

    // if (
    //     is_file($pathStatic = APP_PATH . "/public{$path}") ||
    //     (preg_match_all('/\/$/', $path) && is_file($pathStatic = APP_PATH . "/public{$path}index.html"))
    // ) {
    //     header('Content-Type:' . mime_content_type($pathStatic));
    //     readfile($pathStatic);
    //     die;
    // }

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
