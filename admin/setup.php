<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin.php';
admin_session_start();

$error = '';
$ready = false;
try {
    $adminCount = (int) db()->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    $ready = $adminCount === 0;
    if (!$ready) {
        header('Location: login.php');
        exit;
    }
} catch (Throwable $exception) {
    $error = 'Database belum siap. Lengkapi app/config.php dan impor database/schema.sql melalui phpMyAdmin.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ready) {
    try {
        admin_verify_csrf();
        $username = trim((string) ($_POST['username'] ?? ''));
        $displayName = admin_clip(trim((string) ($_POST['display_name'] ?? '')), 120);
        $password = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_.-]{4,80}$/', $username)) {
            throw new RuntimeException('Nama pengguna harus 4–80 karakter: huruf, angka, titik, garis bawah, atau tanda hubung.');
        }
        if ($displayName === '') {
            throw new RuntimeException('Nama admin harus diisi.');
        }
        if (strlen($password) < 12) {
            throw new RuntimeException('Kata sandi minimal 12 karakter.');
        }
        if ($password !== $confirmation) {
            throw new RuntimeException('Konfirmasi kata sandi belum sama.');
        }
        $statement = db()->prepare('INSERT INTO admins (username, display_name, password_hash) VALUES (:username, :display_name, :password_hash)');
        $statement->execute([
            'username' => $username,
            'display_name' => $displayName,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
        session_regenerate_id(true);
        header('Location: login.php?created=1');
        exit;
    } catch (Throwable $exception) {
        $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'Admin belum dapat dibuat. Periksa database atau pastikan langkah pengaturan awal hanya dijalankan sekali.';
    }
}
?><!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Pengaturan awal admin | CMS SMK Asy-Syafi'iyah</title>
  <style>
    *{box-sizing:border-box}body{margin:0;background:#edf3ef;color:#1d3329;font:15px/1.6 system-ui,-apple-system,"Segoe UI",sans-serif}.wrap{width:min(520px,calc(100% - 32px));margin:8vh auto}.brand{margin:0 0 7px;color:#0e4a3b;font-size:12px;font-weight:800;letter-spacing:.13em}.card{padding:clamp(22px,5vw,38px);border:1px solid #d8e3dd;border-radius:16px;background:#fff;box-shadow:0 14px 40px #163c2b12}h1{margin:0;color:#15271f;font-size:30px;line-height:1.2;letter-spacing:-.04em}p{color:#66756c}label{display:block;margin:15px 0 6px;color:#33483d;font-size:12px;font-weight:750}input{width:100%;min-height:46px;padding:10px 12px;border:1px solid #ccd8d1;border-radius:8px;font:inherit}input:focus{outline:3px solid #f2b70566;border-color:#0e4a3b}.button{width:100%;min-height:48px;margin-top:22px;border:0;border-radius:8px;background:#0e4a3b;color:#fff;font:inherit;font-weight:800;cursor:pointer}.alert{margin:15px 0;padding:12px 14px;border-radius:8px;background:#fff2e7;color:#8e4215;font-size:13px}.hint{font-size:12px;color:#738178}
  </style>
</head>
<body>
  <main class="wrap">
    <p class="brand">SMK TERPADU PONPES · CMS</p>
    <section class="card" aria-labelledby="title">
      <h1 id="title">Buat akun admin pertama</h1>
      <p>Akun ini mengelola berita, galeri, profil guru, tampilan beranda, dan pengaturan integrasi. Gunakan kata sandi unik.</p>
      <?php if ($error !== ''): ?><div class="alert" role="alert"><?= h($error) ?></div><?php endif; ?>
      <?php if ($ready): ?>
      <form method="post" action="setup.php" autocomplete="on">
        <?= admin_csrf_field() ?>
        <label for="display_name">Nama admin</label>
        <input id="display_name" name="display_name" maxlength="120" required autocomplete="name" value="<?= h($_POST['display_name'] ?? '') ?>">
        <label for="username">Nama pengguna</label>
        <input id="username" name="username" minlength="4" maxlength="80" required autocomplete="username" value="<?= h($_POST['username'] ?? '') ?>">
        <label for="password">Kata sandi (minimal 12 karakter)</label>
        <input id="password" type="password" name="password" minlength="12" required autocomplete="new-password">
        <label for="password_confirmation">Ulangi kata sandi</label>
        <input id="password_confirmation" type="password" name="password_confirmation" minlength="12" required autocomplete="new-password">
        <button class="button" type="submit">Buat akun admin</button>
      </form>
      <?php else: ?>
        <p class="hint">Setelah koneksi database siap, buka halaman ini kembali untuk membuat admin pertama.</p>
        <p><a href="login.php">Ke halaman masuk</a></p>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
