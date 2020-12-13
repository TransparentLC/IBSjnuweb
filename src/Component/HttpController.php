<?php
declare(strict_types=1);

namespace App\Component;

class HttpController {
    protected array $args;

    function __construct(string $method, array $args) {
        $this->args = $args;
        try {
            $this->{$method}();
        } catch (\Throwable $th) {
            http_response_code(500);
            die("<h1>Internal Server Error</h1>\n{$th->getMessage()}\n<pre>{$th->getTraceAsString()}</pre>");
        }
    }

    protected function writeJson(int $status = 200, ?array $result = [], string $msg = '') {
        http_response_code($status);
        header('Content-Type:application/json');
        echo json_encode([
            'code' => $status,
            'msg' => $msg ?? '',
            'result' => (object)($result ?? []),
        ], JSON_UNESCAPED_UNICODE_SLASHES);
    }
}