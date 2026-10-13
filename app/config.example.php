<?php
/**
 * Copy this file to app/config.php and enter the database details from cPanel.
 * app/config.php is blocked from direct web requests by .htaccess.
 */
return [
    'db_host' => 'localhost',
    'db_name' => 'CPANEL_DATABASE_NAME',
    'db_user' => 'CPANEL_DATABASE_USER',
    'db_pass' => 'CHANGE_ME',
    'db_charset' => 'utf8mb4',
    'site_url' => 'https://smkasysyafiiyah.sch.id',
    'timezone' => 'Asia/Jakarta',
    'upload_max_bytes' => 5242880,
];
