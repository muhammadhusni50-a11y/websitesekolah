<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

$accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
$wantsJson = str_contains($accept, 'application/json');

function ppdb_respond(int $statusCode, bool $ok, string $message, bool $wantsJson): never
{
    http_response_code($statusCode);
    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        $json = json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        echo $json !== false ? $json : '{"ok":false,"message":"Permintaan belum dapat diproses."}';
        exit;
    }

    header('Content-Type: text/html; charset=utf-8');
    $safeMessage = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $title = $ok ? 'Formulir terkirim' : 'Formulir belum dapat diproses';
    $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#0A3F2F"><title>' . $safeTitle . ' | SMK Asy-Syafi\'iyah</title><link rel="stylesheet" href="../styles.css"></head><body><main class="ppdb-page-main"><div class="container"><section class="ppdb-info-card"><p class="ppdb-eyebrow">PENERIMAAN PESERTA DIDIK BARU</p><h1>' . $safeTitle . '</h1><p>' . $safeMessage . '</p><p><a class="button button-primary" href="../ppdb.html">Kembali ke halaman PPDB</a></p></section></div></main></body></html>';
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    ppdb_respond(405, false, 'Metode permintaan tidak diizinkan.', $wantsJson);
}

$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 12000) {
    ppdb_respond(413, false, 'Ukuran formulir terlalu besar. Periksa kembali isian lalu coba lagi.', $wantsJson);
}

// Honeypot: quietly accept automated submissions without storing them.
if (trim((string) ($_POST['website'] ?? '')) !== '') {
    ppdb_respond(200, true, 'Terima kasih. Minat PPDB Anda sudah tercatat.', $wantsJson);
}

$clean = static function (string $key): string {
    $value = trim((string) ($_POST[$key] ?? ''));
    return str_replace("\0", '', $value);
};
$length = static function (string $value): int {
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
};

$nama = $clean('nama');
$alamat = $clean('alamat');
$asalSekolah = $clean('asal_sekolah');
$email = strtolower($clean('email'));
$jurusan = strtoupper($clean('jurusan'));
$nomorHp = preg_replace('/[\s().-]+/', '', $clean('nomor_hp')) ?? '';
$persetujuan = $clean('persetujuan');

if ($nama === '' || $alamat === '' || $asalSekolah === '' || $email === '' || $nomorHp === '' || !in_array($jurusan, ['DKV', 'TKR'], true)) {
    ppdb_respond(422, false, 'Lengkapi nama, alamat, asal sekolah, email, nomor HP, dan pilihan jurusan.', $wantsJson);
}
if (!preg_match('//u', $nama . $alamat . $asalSekolah)) {
    ppdb_respond(422, false, 'Pastikan isian teks menggunakan karakter yang valid.', $wantsJson);
}
if ($length($nama) > 120 || $length($alamat) > 1000 || $length($asalSekolah) > 180 || $length($email) > 180 || strlen($nomorHp) > 32) {
    ppdb_respond(422, false, 'Ada isian yang melebihi batas panjang. Periksa kembali data lalu coba lagi.', $wantsJson);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    ppdb_respond(422, false, 'Format email belum valid.', $wantsJson);
}
if (!preg_match('/^\+?[0-9]{8,15}$/', $nomorHp)) {
    ppdb_respond(422, false, 'Nomor HP harus berisi 8–15 angka, dengan tanda + di depan bila memakai kode negara.', $wantsJson);
}
if ($persetujuan !== 'ya') {
    ppdb_respond(422, false, 'Setujui penggunaan data untuk keperluan tindak lanjut PPDB sebelum mengirim formulir.', $wantsJson);
}

try {
    $statement = db()->prepare(
        'INSERT INTO ppdb_applicants (nama, alamat, asal_sekolah, email, jurusan, nomor_hp) '
        . 'VALUES (:nama, :alamat, :asal_sekolah, :email, :jurusan, :nomor_hp)'
    );
    $statement->execute([
        'nama' => $nama,
        'alamat' => $alamat,
        'asal_sekolah' => $asalSekolah,
        'email' => $email,
        'jurusan' => $jurusan,
        'nomor_hp' => $nomorHp,
    ]);
} catch (Throwable $exception) {
    error_log('PPDB submission could not be saved: ' . $exception->getMessage());
    ppdb_respond(503, false, 'Formulir belum dapat disimpan. Silakan coba kembali nanti atau hubungi sekolah melalui email.', $wantsJson);
}

ppdb_respond(201, true, 'Terima kasih. Minat PPDB Anda sudah tercatat. Tim sekolah dapat menghubungi Anda melalui kontak yang dicantumkan.', $wantsJson);
