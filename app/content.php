<?php
declare(strict_types=1);

function h($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function indonesian_date(?string $value): string
{
    if (!$value) {
        return '';
    }
    try {
        $date = new DateTimeImmutable($value);
    } catch (Throwable $error) {
        return '';
    }
    $months = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
        7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
    ];
    return $date->format('j') . ' ' . $months[(int) $date->format('n')] . ' ' . $date->format('Y');
}

function public_posts(int $limit = 50): array
{
    $limit = max(1, min(100, $limit));
    $statement = db()->query(
        "SELECT id, slug, title, category, excerpt, cover_image, published_at "
        . "FROM posts WHERE status = 'published' AND published_at <= NOW() "
        . 'ORDER BY published_at DESC, id DESC LIMIT ' . $limit
    );
    return $statement->fetchAll();
}

function public_post_by_slug(string $slug): ?array
{
    $statement = db()->prepare(
        "SELECT id, slug, title, category, excerpt, content, cover_image, published_at "
        . "FROM posts WHERE slug = :slug AND status = 'published' AND published_at <= NOW() LIMIT 1"
    );
    $statement->execute(['slug' => $slug]);
    $post = $statement->fetch();
    return $post ?: null;
}

function public_site_content(): array
{
    $empty = ['settings' => [], 'teachers' => [], 'posts' => [], 'gallery' => [], 'videos' => []];
    try {
        $settingRows = db()->query('SELECT setting_key, setting_value FROM settings WHERE is_public = 1')->fetchAll();
        $settings = [];
        foreach ($settingRows as $row) {
            $value = (string) $row['setting_value'];
            if ($row['setting_key'] === 'section_visibility') {
                $decoded = json_decode($value, true);
                $settings[$row['setting_key']] = is_array($decoded) ? $decoded : [];
            } else {
                $settings[$row['setting_key']] = $value;
            }
        }

        $teachers = db()->query(
            'SELECT name, `role`, department, specialty, bio, photo FROM teachers '
            . 'WHERE is_published = 1 ORDER BY sort_order ASC, id ASC LIMIT 24'
        )->fetchAll();

        $posts = public_posts(3);
        foreach ($posts as &$post) {
            $post['date_label'] = indonesian_date((string) $post['published_at']);
            $post['url'] = 'post.php?slug=' . rawurlencode((string) $post['slug']);
        }
        unset($post);

        $gallery = db()->query(
            'SELECT image, caption, alt_text, permalink FROM gallery '
            . 'WHERE is_published = 1 ORDER BY sort_order ASC, id DESC LIMIT 8'
        )->fetchAll();

        $videos = db()->query(
            'SELECT youtube_id, title, description, thumbnail, published_at FROM videos '
            . 'ORDER BY published_at DESC, id DESC LIMIT 5'
        )->fetchAll();

        return [
            'settings' => $settings,
            'teachers' => $teachers,
            'posts' => $posts,
            'gallery' => $gallery,
            'videos' => $videos,
        ];
    } catch (Throwable $error) {
        // Keep the public HTML usable during first-time setup or a temporary DB outage.
        return $empty;
    }
}

function site_url(string $path = ''): string
{
    $config = app_config();
    $configuredUrl = (string) ($config['site_url'] ?? 'https://smkasysyafiiyah.sch.id');
    try {
        $configuredUrl = get_setting('site_url', $configuredUrl);
    } catch (Throwable $error) {
        // The static fallback domain is also used before the CMS database is installed.
    }
    $base = rtrim($configuredUrl, '/');
    return $path === '' ? $base . '/' : $base . '/' . ltrim($path, '/');
}
