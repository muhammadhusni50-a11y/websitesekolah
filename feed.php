<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
header('Content-Type: application/rss+xml; charset=utf-8');
header('X-Content-Type-Options: nosniff');

try {
    $posts = public_posts(30);
} catch (Throwable $error) {
    $fallback = @file_get_contents(__DIR__ . '/feed.xml');
    if ($fallback !== false) {
        echo $fallback;
        exit;
    }
    $posts = [];
}

$xml = static function ($value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
};
$items = '';
foreach ($posts as $post) {
    $path = 'post.php?slug=' . rawurlencode((string) $post['slug']);
    $published = strtotime((string) $post['published_at']) ?: time();
    $items .= "\n    <item>\n"
        . '      <title>' . $xml($post['title']) . "</title>\n"
        . '      <link>' . $xml(site_url($path)) . "</link>\n"
        . '      <guid isPermaLink="true">' . $xml(site_url($path)) . "</guid>\n"
        . '      <pubDate>' . gmdate(DATE_RSS, $published) . "</pubDate>\n"
        . '      <description>' . $xml($post['excerpt']) . "</description>\n"
        . '      <category>' . $xml($post['category']) . "</category>\n"
        . "    </item>";
}
$base = site_url();
$self = site_url('feed.xml');
$lastBuild = gmdate(DATE_RSS);
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
echo "  <channel>\n";
echo '    <title>' . $xml(get_setting('brand_short', 'SMK Asy-Syafi\'iyah') . ' — Berita & Informasi') . "</title>\n";
echo '    <link>' . $xml($base) . "</link>\n";
echo '    <description>' . $xml(get_setting('meta_description', 'Berita dan informasi sekolah.')) . "</description>\n";
echo "    <language>id-ID</language>\n";
echo '    <lastBuildDate>' . $lastBuild . "</lastBuildDate>\n";
echo '    <atom:link href="' . $xml($self) . '" rel="self" type="application/rss+xml" />' . "\n";
echo $items . "\n  </channel>\n</rss>\n";
