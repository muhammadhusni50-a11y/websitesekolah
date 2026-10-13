<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
header('Content-Type: application/xml; charset=utf-8');
header('X-Content-Type-Options: nosniff');

try {
    $posts = public_posts(100);
} catch (Throwable $error) {
    $fallback = @file_get_contents(__DIR__ . '/sitemap.xml');
    if ($fallback !== false) {
        echo $fallback;
        exit;
    }
    $posts = [];
}
$xml = static function ($value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
};
$lastmod = date('Y-m-d');
$urls = '  <url><loc>' . $xml(site_url()) . '</loc><lastmod>' . $lastmod . '</lastmod><changefreq>weekly</changefreq><priority>1.0</priority></url>';
foreach ($posts as $post) {
    $published = strtotime((string) ($post['published_at'] ?? ''));
    $modified = strtotime((string) ($post['updated_at'] ?? ''));
    $date = date('Y-m-d', $modified ?: ($published ?: time()));
    $path = 'post.php?slug=' . rawurlencode((string) $post['slug']);
    $urls .= "\n  <url><loc>" . $xml(site_url($path)) . '</loc><lastmod>' . $date . '</lastmod><changefreq>monthly</changefreq><priority>0.7</priority></url>';
}
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n" . $urls . "\n</urlset>\n";
