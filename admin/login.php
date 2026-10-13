<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin.php';
admin_session_start();
if (!empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$setupAvailable = false;
try {
    $setupAvailable = (int) db()->query('SELECT COUNT(*) FROM admins')->fetchColumn() === 0;
} catch (Throwable $exception) {
    $error = 'Panel belum terhubung. Import database/schema.sql dan isi pengaturan database di app/config.php.';
}
if (isset($_GET['created'])) {
    $error = '';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        admin_verify_csrf();
        $lockUntil = (int) ($_SESSION['login_lock_until'] ?? 0);
        if ($lockUntil > time()) {
            throw new RuntimeException('Terlalu banyak percobaan. Coba kembali dalam beberapa menit.');
        }
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $statement = db()->prepare('SELECT id, username, display_name, password_hash FROM admins WHERE username = :username LIMIT 1');
        $statement->execute(['username' => $username]);
        $admin = $statement->fetch();
        if (!$admin || !password_verify($password, (string) $admin['password_hash'])) {
            $failures = (int) ($_SESSION['login_failures'] ?? 0) + 1;
            $_SESSION['login_failures'] = $failures;
            if ($failures >= 5) {
                $_SESSION['login_failures'] = 0;
                $_SESSION['login_lock_until'] = time() + 300;
            }
            throw new RuntimeException('Nama pengguna atau kata sandi tidak cocok.');
        }
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['admin_name'] = (string) $admin['display_name'];
        unset($_SESSION['login_failures'], $_SESSION['login_lock_until']);
        db()->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = :id')->execute(['id' => $admin['id']]);
        header('Location: index.php');
        exit;
    } catch (Throwable $exception) {
        if ($error === '') {
            $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'Tidak dapat masuk. Periksa koneksi database.';
        }
    }
}
?><!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Masuk admin | CMS SMK Asy-Syafi'iyah</title>
  <style>
    *{box-sizing:border-box}body{margin:0;background:#edf3ef;color:#1d3329;font:15px/1.6 system-ui,-apple-system,"Segoe UI",sans-serif}.wrap{width:min(460px,calc(100% - 32px));margin:10vh auto}.brand{margin:0 0 7px;color:#0e4a3b;font-size:12px;font-weight:800;letter-spacing:.13em}.card{padding:clamp(22px,5vw,38px);border:1px solid #d8e3dd;border-radius:16px;background:#fff;box-shadow:0 14px 40px #163c2b12}h1{margin:0;color:#15271f;font-size:30px;line-height:1.2;letter-spacing:-.04em}p{color:#66756c}label{display:block;margin:15px 0 6px;color:#33483d;font-size:12px;font-weight:750}input{width:100%;min-height:46px;padding:10px 12px;border:1px solid #ccd8d1;border-radius:8px;font:inherit}input:focus{outline:3px solid #f2b70566;border-color:#0e4a3b}.button{width:100%;min-height:48px;margin-top:22px;border:0;border-radius:8px;background:#0e4a3b;color:#fff;font:inherit;font-weight:800;cursor:pointer}.alert{margin:15px 0;padding:12px 14px;border-radius:8px;background:#fff2e7;color:#8e4215;font-size:13px}.help{margin-top:20px;font-size:12px}.help a{color:#0e4a3b;font-weight:800}
  </style>
</head>
<body>
  <main class="wrap">
    <p class="brand">SMK TERPADU PONPES · CMS</p>
    <section class="card" aria-labelledby="title">
      <h1 id="title">Masuk panel admin</h1>
      <p>Kelola konten website sekolah dengan akun admin.</p>
      <?php if (isset($_GET['created'])): ?><div class="alert" role="status" style="background:#e8f2ee;color:#0e4a3b">Akun admin berhasil dibuat. Silakan masuk.</div><?php endif; ?>
      <?php if ($error !== ''): ?><div class="alert" role="alert"><?= h($error) ?></div><?php endif; ?>
      <?php if (!$setupAvailable): ?>
      <form method="post" action="login.php" autocomplete="on">
        <?= admin_csrf_field() ?>
        <label for="username">Nama pengguna</label>
        <input id="username" name="username" required autocomplete="username" value="<?= h($_POST['username'] ?? '') ?>">
        <label for="password">Kata sandi</label>
        <input id="password" type="password" name="password" required autocomplete="current-password">
        <button class="button" type="submit">Masuk</button>
      </form>
      <?php else: ?>
        <p>Belum ada akun admin. Buat akun pertama untuk mulai mengelola situs.</p>
        <p class="help"><a href="setup.php">Buka pengaturan awal admin →</a></p>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
