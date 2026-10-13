<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

$html = @file_get_contents(__DIR__ . '/index.html');
if ($html === false) {
    http_response_code(500);
    exit('Halaman utama tidak dapat dimuat.');
}

$content = public_site_content();
$settings = $content['settings'] ?? [];
if (!empty($settings['site_title'])) {
    $html = preg_replace_callback('/<title>.*?<\/title>/is', static function () use ($settings): string {
        return '<title>' . h($settings['site_title']) . '</title>';
    }, $html, 1) ?? $html;
}
if (!empty($settings['meta_description'])) {
    $html = preg_replace_callback('/<meta\s+name="description"\s+content="[^"]*"[^>]*>/i', static function () use ($settings): string {
        return '<meta name="description" content="' . h($settings['meta_description']) . '" data-cms-meta="description" />';
    }, $html, 1) ?? $html;
}
$canonical = site_url();
$html = preg_replace_callback('/<link\s+rel="canonical"\s+href="[^"]*"\s*\/?\s*>/i', static function () use ($canonical): string {
    return '<link rel="canonical" href="' . h($canonical) . '" />';
}, $html, 1) ?? $html;

$json = json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$bootstrapScript = '<script>window.SCHOOL_CMS_DATA = ' . ($json !== false ? $json : '{}') . ';</script>' . "\n  ";
$html = str_replace('</head>', $bootstrapScript . '</head>', $html);

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
echo $html;
