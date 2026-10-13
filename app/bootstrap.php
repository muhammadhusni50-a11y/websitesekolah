<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/content.php';

$config = app_config();
date_default_timezone_set((string) ($config['timezone'] ?? 'Asia/Jakarta'));
