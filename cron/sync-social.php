<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/social.php';

foreach (social_sync_all() as $result) {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $result . PHP_EOL;
}
