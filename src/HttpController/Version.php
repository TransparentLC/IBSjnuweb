<?php
declare(strict_types=1);

namespace App\HttpController;

class Version extends \App\Component\HttpController {
    function index() {
        $this->writeJson(200, [
            'commit' => GIT_COMMIT_HASH,
            'commitShort' => GIT_COMMIT_HASH_SHORT,
            'commitTime' => GIT_COMMIT_TIMESTAMP,
        ]);
    }
}