<?php
declare(strict_types=1);

function admin_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('skapsya_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function admin_require_login(): void
{
    admin_session_start();
    if (empty($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}

function admin_csrf_token(): string
{
    admin_session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf_token'];
}

function admin_csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(admin_csrf_token()) . '">';
}

function admin_verify_csrf(): void
{
    admin_session_start();
    $submitted = (string) ($_POST['csrf_token'] ?? '');
    $known = (string) ($_SESSION['csrf_token'] ?? '');
    if ($known === '' || !hash_equals($known, $submitted)) {
        throw new RuntimeException('Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.');
    }
}

function admin_flash(string $message = '', string $type = 'success'): ?array
{
    admin_session_start();
    if ($message !== '') {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
        return null;
    }
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}

function admin_slug(string $value): string
{
    $value = trim($value);
    if (function_exists('iconv')) {
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($transliterated !== false) {
            $value = $transliterated;
        }
    }
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-');
}

function admin_valid_image_reference(string $value): bool
{
    $value = trim($value);
    if ($value === '') {
        return true;
    }
    if (preg_match('~^(uploads|public)/[A-Za-z0-9._/-]+\.(jpg|jpeg|png|webp)$~i', $value)) {
        return true;
    }
    if (filter_var($value, FILTER_VALIDATE_URL)) {
        return strtolower((string) parse_url($value, PHP_URL_SCHEME)) === 'https';
    }
    return false;
}

function admin_upload_image(string $field): ?string
{
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
        return null;
    }
    $file = $_FILES[$field];
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Unggahan gambar gagal. Periksa batas unggahan pada cPanel.');
    }

    $config = app_config();
    $maximum = max(1024 * 1024, min(20 * 1024 * 1024, (int) ($config['upload_max_bytes'] ?? 5242880)));
    $temporaryPath = (string) ($file['tmp_name'] ?? '');
    if ($temporaryPath === '' || !is_uploaded_file($temporaryPath) || (int) ($file['size'] ?? 0) > $maximum) {
        throw new RuntimeException('Ukuran gambar terlalu besar atau berkas unggahan tidak valid. Batas unggah: ' . round($maximum / 1048576) . ' MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($temporaryPath);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($extensions[$mime]) || @getimagesize($temporaryPath) === false) {
        throw new RuntimeException('Format gambar harus JPG, PNG, atau WebP.');
    }

    $relativeDirectory = 'uploads/' . date('Y') . '/' . date('m');
    $absoluteDirectory = dirname(__DIR__) . '/' . $relativeDirectory;
    if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0755, true) && !is_dir($absoluteDirectory)) {
        throw new RuntimeException('Folder unggahan tidak dapat dibuat. Periksa izin folder uploads/.');
    }

    $filename = bin2hex(random_bytes(18)) . '.' . $extensions[$mime];
    $destination = $absoluteDirectory . '/' . $filename;
    if (!move_uploaded_file($temporaryPath, $destination)) {
        throw new RuntimeException('Gambar tidak berhasil disimpan. Periksa izin folder uploads/.');
    }
    return $relativeDirectory . '/' . $filename;
}

function admin_clip(string $value, int $maximum): string
{
    $value = trim($value);
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maximum, 'UTF-8');
    }
    return substr($value, 0, $maximum);
}
