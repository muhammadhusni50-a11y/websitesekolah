<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
$slug = trim((string) ($_GET['slug'] ?? ''));
$post = null;
$dbFailed = false;
try {
    if ($slug !== '') {
        $post = public_post_by_slug($slug);
    }
} catch (Throwable $error) {
    $dbFailed = true;
}
if (!$post) {
    http_response_code($dbFailed ? 503 : 404);
    $pageTitle = 'Informasi tidak ditemukan';
    $contentHtml = '<h1>Informasi belum tersedia.</h1><p>Berita mungkin sudah dipindahkan atau alamatnya tidak tepat.</p>';
    $imageHtml = '';
} else {
    $pageTitle = (string) $post['title'];
    $dateLabel = indonesian_date((string) $post['published_at']);
    $image = (string) ($post['cover_image'] ?? '');
    if (preg_match('~^(https://|/|public/|uploads/)~i', $image)) {
        $imageHtml = '<img class="post-cover" src="' . h($image) . '" alt="' . h($pageTitle) . '" loading="eager">';
    } else {
        $imageHtml = '';
    }
    $contentHtml = '<p class="post-excerpt">' . h($post['excerpt']) . '</p><div class="post-body">' . nl2br(h($post['content']), false) . '</div>';
}
$description = $post ? (string) $post['excerpt'] : 'Informasi sekolah SMK Terpadu Ponpes Asy-Syafi\'iyah Lohgung.';
try {
    $brandShort = get_setting('brand_short', 'SMK Asy-Syafi\'iyah');
} catch (Throwable $error) {
    $brandShort = 'SMK Asy-Syafi\'iyah';
}
$canonical = $post ? site_url('post.php?slug=' . rawurlencode((string) $post['slug'])) : site_url();
?><!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="<?= h($description) ?>">
  <link rel="canonical" href="<?= h($canonical) ?>">
  <link rel="alternate" type="application/rss+xml" title="RSS berita sekolah" href="<?= h(site_url('feed.xml')) ?>">
  <link rel="stylesheet" href="styles.css">
  <title><?= h($pageTitle) ?> | <?= h($brandShort) ?></title>
  <style>
    .post-page { min-height: 100vh; padding: 42px 0 80px; background: #f6f8f7; }
    .post-wrap { width: min(820px, calc(100% - 36px)); margin: 0 auto; }
    .post-back { display: inline-flex; align-items: center; gap: 8px; margin-bottom: 23px; color: var(--green); font-size: 13px; font-weight: 800; }
    .post-article { overflow: hidden; border: 1px solid var(--line); border-radius: 16px; background: #fff; box-shadow: var(--shadow); }
    .post-cover { width: 100%; max-height: 470px; object-fit: cover; background: var(--green-soft); }
    .post-content { padding: clamp(23px, 5vw, 50px); }
    .post-category { color: var(--green); font-size: 11px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
    .post-content h1 { margin: 8px 0 9px; color: var(--ink); font-family: var(--font-display); font-size: clamp(31px, 5vw, 48px); line-height: 1.12; letter-spacing: -.04em; }
    .post-date { margin: 0 0 24px; color: var(--muted); font-size: 12px; }
    .post-excerpt { padding-bottom: 17px; border-bottom: 1px solid var(--line); color: #344f42; font-size: 17px; font-weight: 650; line-height: 1.7; }
    .post-body { color: #35483e; font-size: 15px; line-height: 1.9; white-space: normal; }
    .post-body br + br { display: block; content: ""; margin-top: 14px; }
  </style>
</head>
<body>
  <main class="post-page">
    <div class="post-wrap">
      <a class="post-back" href="index.php#berita">← Kembali ke berita</a>
      <article class="post-article">
        <?= $imageHtml ?>
        <div class="post-content">
          <span class="post-category"><?= h($post['category'] ?? 'Informasi sekolah') ?></span>
          <h1><?= h($pageTitle) ?></h1>
          <?php if ($post): ?><p class="post-date"><time datetime="<?= h(date('Y-m-d', strtotime((string) $post['published_at']))) ?>"><?= h(indonesian_date((string) $post['published_at'])) ?></time></p><?php endif; ?>
          <?= $contentHtml ?>
        </div>
      </article>
    </div>
  </main>
</body>
</html>
