<?php
declare(strict_types=1);

function social_clip(string $value, int $maximum): string
{
    $value = trim($value);
    return function_exists('mb_substr') ? mb_substr($value, 0, $maximum, 'UTF-8') : substr($value, 0, $maximum);
}

function social_http_json(string $url): array
{
    $body = false;
    $status = 0;
    if (function_exists('curl_init')) {
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'SMK-Asy-Syafiyah-CMS/1.0',
        ]);
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($handle);
        curl_close($handle);
        if ($body === false) {
            throw new RuntimeException('Koneksi API gagal' . ($curlError !== '' ? ': ' . $curlError : '.') . '.');
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 30,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\nUser-Agent: SMK-Asy-Syafiyah-CMS/1.0\r\n",
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $body = @file_get_contents($url, false, $context);
        foreach (($http_response_header ?? []) as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches)) {
                $status = (int) $matches[1];
            }
        }
        if ($body === false) {
            throw new RuntimeException('Koneksi API gagal. Periksa ekstensi cURL atau akses keluar server.');
        }
    }

    if ($status < 200 || $status >= 300) {
        throw new RuntimeException('API mengembalikan HTTP ' . $status . '. Periksa izin, ID akun, dan konfigurasi API.');
    }
    $decoded = json_decode((string) $body, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('API mengembalikan respons yang tidak dapat dibaca.');
    }
    if (isset($decoded['error'])) {
        $message = is_array($decoded['error']) ? (string) ($decoded['error']['message'] ?? '') : '';
        throw new RuntimeException($message !== '' ? social_clip($message, 220) : 'API melaporkan kesalahan konfigurasi.');
    }
    return $decoded;
}

function social_sync_instagram(): int
{
    $userId = trim(get_setting('instagram_user_id'));
    $token = trim(get_setting('instagram_access_token'));
    if ($userId === '' || $token === '') {
        return 0;
    }
    if (!preg_match('/^\d{5,30}$/', $userId)) {
        throw new RuntimeException('Instagram User ID tidak valid.');
    }
    $version = trim(get_setting('instagram_graph_version', 'v24.0'));
    if (!preg_match('/^v\d+\.\d+$/', $version)) {
        throw new RuntimeException('Versi Instagram Graph API tidak valid.');
    }

    $query = http_build_query([
        'fields' => 'id,caption,media_type,media_url,permalink,thumbnail_url,timestamp,children{media_type,media_url,thumbnail_url}',
        'limit' => 12,
        'access_token' => $token,
    ], '', '&', PHP_QUERY_RFC3986);
    $url = 'https://graph.facebook.com/' . rawurlencode($version) . '/' . rawurlencode($userId) . '/media?' . $query;
    $response = social_http_json($url);
    $items = $response['data'] ?? [];
    if (!is_array($items)) {
        throw new RuntimeException('Daftar media Instagram tidak tersedia.');
    }

    $pdo = db();
    $statement = $pdo->prepare(
        "INSERT INTO gallery (image, caption, alt_text, permalink, source, source_id, sort_order, is_published) "
        . "VALUES (:image, :caption, :alt_text, :permalink, 'instagram', :source_id, 0, 1) "
        . 'ON DUPLICATE KEY UPDATE image = VALUES(image), caption = VALUES(caption), '
        . 'alt_text = VALUES(alt_text), permalink = VALUES(permalink), is_published = 1'
    );
    $seen = [];
    $saved = 0;
    // Graph API returns newest-first; inserting in reverse lets id DESC preserve that order.
    foreach (array_reverse($items) as $item) {
        if (!is_array($item) || empty($item['id'])) {
            continue;
        }
        $image = (string) ($item['media_url'] ?? '');
        if (($item['media_type'] ?? '') === 'VIDEO') {
            $image = (string) ($item['thumbnail_url'] ?? $image);
        }
        if ($image === '' && !empty($item['children']['data'][0]['media_url'])) {
            $image = (string) $item['children']['data'][0]['media_url'];
        }
        if (!preg_match('~^https://~i', $image)) {
            continue;
        }
        $caption = trim((string) ($item['caption'] ?? 'Foto kegiatan sekolah'));
        $caption = social_clip($caption !== '' ? $caption : 'Foto kegiatan sekolah', 230);
        $permalink = (string) ($item['permalink'] ?? 'https://www.instagram.com/skapsya/');
        if (!preg_match('~^https://~i', $permalink)) {
            $permalink = 'https://www.instagram.com/skapsya/';
        }
        $sourceId = 'ig:' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) $item['id']);
        $statement->execute([
            'image' => $image,
            'caption' => $caption,
            'alt_text' => 'Foto dari Instagram sekolah: ' . social_clip($caption, 180),
            'permalink' => $permalink,
            'source_id' => $sourceId,
        ]);
        $seen[] = $sourceId;
        $saved++;
    }

    if ($seen !== []) {
        $placeholders = implode(',', array_fill(0, count($seen), '?'));
        $delete = $pdo->prepare("DELETE FROM gallery WHERE source = 'instagram' AND source_id NOT IN ($placeholders)");
        $delete->execute($seen);
    }
    return $saved;
}

function social_sync_youtube(): int
{
    $channelId = trim(get_setting('youtube_channel_id'));
    $apiKey = trim(get_setting('youtube_api_key'));
    if ($channelId === '' || $apiKey === '') {
        return 0;
    }
    if (!preg_match('/^UC[A-Za-z0-9_-]{20,40}$/', $channelId)) {
        throw new RuntimeException('YouTube Channel ID tidak valid.');
    }

    $query = http_build_query([
        'part' => 'snippet',
        'type' => 'video',
        'order' => 'date',
        'maxResults' => 8,
        'channelId' => $channelId,
        'key' => $apiKey,
    ], '', '&', PHP_QUERY_RFC3986);
    $response = social_http_json('https://www.googleapis.com/youtube/v3/search?' . $query);
    $items = $response['items'] ?? [];
    if (!is_array($items)) {
        throw new RuntimeException('Daftar video YouTube tidak tersedia.');
    }

    $pdo = db();
    $statement = $pdo->prepare(
        'INSERT INTO videos (youtube_id, title, description, thumbnail, published_at) '
        . 'VALUES (:youtube_id, :title, :description, :thumbnail, :published_at) '
        . 'ON DUPLICATE KEY UPDATE title = VALUES(title), description = VALUES(description), '
        . 'thumbnail = VALUES(thumbnail), published_at = VALUES(published_at)'
    );
    $seen = [];
    $saved = 0;
    foreach ($items as $item) {
        $videoId = (string) ($item['id']['videoId'] ?? '');
        $snippet = $item['snippet'] ?? [];
        if (!preg_match('/^[A-Za-z0-9_-]{11}$/', $videoId) || !is_array($snippet)) {
            continue;
        }
        $thumbnail = (string) ($snippet['thumbnails']['high']['url'] ?? $snippet['thumbnails']['medium']['url'] ?? $snippet['thumbnails']['default']['url'] ?? '');
        $published = (string) ($snippet['publishedAt'] ?? '');
        try {
            $publishedAt = (new DateTimeImmutable($published))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('Y-m-d H:i:s');
        } catch (Throwable $error) {
            $publishedAt = date('Y-m-d H:i:s');
        }
        $statement->execute([
            'youtube_id' => $videoId,
            'title' => social_clip((string) ($snippet['title'] ?? 'Video sekolah'), 240),
            'description' => social_clip((string) ($snippet['description'] ?? ''), 490),
            'thumbnail' => preg_match('~^https://~i', $thumbnail) ? $thumbnail : '',
            'published_at' => $publishedAt,
        ]);
        $seen[] = $videoId;
        $saved++;
    }

    if ($seen !== []) {
        $placeholders = implode(',', array_fill(0, count($seen), '?'));
        $delete = $pdo->prepare("DELETE FROM videos WHERE youtube_id NOT IN ($placeholders)");
        $delete->execute($seen);
    }
    return $saved;
}

function social_sync_all(): array
{
    $results = [];
    try {
        $count = social_sync_instagram();
        $results[] = $count > 0 ? 'Instagram: ' . $count . ' foto disinkronkan.' : 'Instagram: belum dikonfigurasi atau tidak ada media baru.';
    } catch (Throwable $error) {
        $results[] = 'Instagram: ' . social_clip($error->getMessage(), 220);
    }
    try {
        $count = social_sync_youtube();
        $results[] = $count > 0 ? 'YouTube: ' . $count . ' video disinkronkan.' : 'YouTube: belum dikonfigurasi atau tidak ada video.';
    } catch (Throwable $error) {
        $results[] = 'YouTube: ' . social_clip($error->getMessage(), 220);
    }
    return $results;
}
