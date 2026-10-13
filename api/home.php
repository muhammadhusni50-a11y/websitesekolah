<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

$json = json_encode(public_site_content(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
echo $json !== false ? $json : '{"settings":{},"teachers":[],"posts":[],"gallery":[],"videos":[]}';
