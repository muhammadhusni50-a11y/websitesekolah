<?php
declare(strict_types=1);

function app_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $configPath = __DIR__ . '/config.php';
    $config = is_file($configPath) ? require $configPath : [];
    return is_array($config) ? $config : [];
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = app_config();
    foreach (['db_host', 'db_name', 'db_user', 'db_pass'] as $key) {
        if (!array_key_exists($key, $config) || ($key !== 'db_pass' && trim((string) $config[$key]) === '')) {
            throw new RuntimeException('Konfigurasi database belum lengkap. Salin app/config.example.php menjadi app/config.php.');
        }
    }

    $charset = preg_replace('/[^a-z0-9_]/i', '', (string) ($config['db_charset'] ?? 'utf8mb4')) ?: 'utf8mb4';
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $config['db_host'], $config['db_name'], $charset);
    $pdo = new PDO($dsn, (string) $config['db_user'], (string) $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET time_zone = '+07:00'");
    return $pdo;
}

function get_setting(string $key, string $default = ''): string
{
    $statement = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = :setting_key LIMIT 1');
    $statement->execute(['setting_key' => $key]);
    $value = $statement->fetchColumn();
    return $value === false ? $default : (string) $value;
}

function save_setting(string $key, string $value, bool $isPublic = true): void
{
    $statement = db()->prepare(
        'INSERT INTO settings (setting_key, setting_value, is_public) VALUES (:setting_key, :setting_value, :is_public) '
        . 'ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), is_public = VALUES(is_public)'
    );
    $statement->execute([
        'setting_key' => $key,
        'setting_value' => $value,
        'is_public' => $isPublic ? 1 : 0,
    ]);
}
