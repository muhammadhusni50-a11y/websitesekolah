<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/admin.php';
admin_require_login();
$pdo = db();
$allowedTabs = ['dashboard', 'posts', 'teachers', 'gallery', 'ppdb', 'settings'];
$activeTab = (string) ($_GET['tab'] ?? 'dashboard');
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'dashboard';
}
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $activeTab = (string) ($_POST['tab'] ?? 'dashboard');
    if (!in_array($activeTab, $allowedTabs, true)) {
        $activeTab = 'dashboard';
    }
    try {
        admin_verify_csrf();
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'update_ppdb_status') {
            $id = max(0, (int) ($_POST['id'] ?? 0));
            $status = (string) ($_POST['status'] ?? '');
            $allowedPpdbStatuses = ['baru', 'dihubungi', 'proses', 'diterima', 'tidak_dilanjutkan'];
            if ($id < 1 || !in_array($status, $allowedPpdbStatuses, true)) {
                throw new RuntimeException('Status pendaftar tidak valid.');
            }
            $statement = $pdo->prepare('UPDATE ppdb_applicants SET status=:status WHERE id=:id');
            $statement->execute(['status' => $status, 'id' => $id]);
            $success = 'Status pendaftar berhasil diperbarui.';
            $activeTab = 'ppdb';

        } elseif ($action === 'delete_ppdb_applicant') {
            $id = max(0, (int) ($_POST['id'] ?? 0));
            if ($id < 1) {
                throw new RuntimeException('Pendaftar yang dipilih tidak valid.');
            }
            $statement = $pdo->prepare('DELETE FROM ppdb_applicants WHERE id=:id');
            $statement->execute(['id' => $id]);
            $success = 'Data pendaftar berhasil dihapus.';
            $activeTab = 'ppdb';

        } elseif ($action === 'save_settings') {
            $pdo->beginTransaction();
            $textLimits = [
                'site_title' => 180, 'meta_description' => 320, 'site_url' => 240,
                'brand_short' => 80, 'brand_tagline' => 160, 'school_name' => 180,
                'school_address' => 240, 'contact_address' => 400, 'contact_email' => 180,
                'location_text' => 180, 'hero_kicker' => 180, 'hero_title' => 120,
                'hero_subtitle' => 120, 'hero_highlight' => 100, 'hero_description' => 700,
                'about_title' => 160, 'about_highlight' => 160, 'about_lead' => 500,
                'about_description' => 700, 'majors_title' => 120, 'majors_highlight' => 160,
                'majors_description' => 700, 'dkv_title' => 160, 'dkv_description' => 700,
                'tkr_title' => 160, 'tkr_description' => 700,
                'fact_dkv_code' => 24, 'fact_dkv_title' => 80, 'fact_dkv_detail' => 120,
                'fact_tkr_code' => 24, 'fact_tkr_title' => 80, 'fact_tkr_detail' => 120,
                'fact_student_count' => 24, 'fact_student_title' => 80, 'fact_student_detail' => 120,
                'fact_teacher_count' => 24, 'fact_teacher_title' => 80, 'fact_teacher_detail' => 120,
                'instagram_url' => 240, 'facebook_url' => 240, 'youtube_url' => 240,
                'ppdb_page_title' => 120, 'ppdb_page_highlight' => 100, 'ppdb_page_intro' => 500, 'ppdb_page_notice' => 1000,
            ];
            foreach ($textLimits as $key => $maximum) {
                $value = admin_clip((string) ($_POST[$key] ?? ''), $maximum);
                if ($key === 'contact_email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Alamat email sekolah tidak valid.');
                }
                if ($key === 'site_url' && (!filter_var($value, FILTER_VALIDATE_URL) || strtolower((string) parse_url($value, PHP_URL_SCHEME)) !== 'https')) {
                    throw new RuntimeException('URL situs harus berupa alamat HTTPS resmi, misalnya https://sekolah.sch.id.');
                }
                if (in_array($key, ['instagram_url', 'facebook_url', 'youtube_url'], true) && $value !== '') {
                    if (!filter_var($value, FILTER_VALIDATE_URL) || strtolower((string) parse_url($value, PHP_URL_SCHEME)) !== 'https') {
                        throw new RuntimeException('Tautan media sosial harus memakai HTTPS.');
                    }
                    $host = strtolower((string) parse_url($value, PHP_URL_HOST));
                    $allowedHosts = [
                        'instagram_url' => ['instagram.com', 'www.instagram.com'],
                        'facebook_url' => ['facebook.com', 'www.facebook.com', 'm.facebook.com'],
                        'youtube_url' => ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be'],
                    ];
                    if (!in_array($host, $allowedHosts[$key], true)) {
                        throw new RuntimeException('Tautan ' . $key . ' harus berasal dari platform resminya.');
                    }
                }
                save_setting($key, $value, true);
            }

            foreach (['theme_primary', 'theme_dark', 'theme_soft', 'theme_accent'] as $key) {
                $value = trim((string) ($_POST[$key] ?? ''));
                if (!preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                    throw new RuntimeException('Warna tema harus menggunakan format enam digit HEX.');
                }
                save_setting($key, strtolower($value), true);
            }

            foreach (['logo_image', 'hero_image', 'hero_image_2', 'hero_image_3', 'hero_image_4', 'about_image', 'dkv_image', 'tkr_image'] as $key) {
                $reference = trim((string) ($_POST[$key] ?? ''));
                $uploaded = admin_upload_image($key . '_file');
                if ($uploaded !== null) {
                    $reference = $uploaded;
                }
                if (!admin_valid_image_reference($reference)) {
                    throw new RuntimeException('Gambar ' . $key . ' harus berupa JPG, PNG, WebP, atau URL HTTPS.');
                }
                save_setting($key, $reference, true);
            }

            $sections = [];
            foreach (['majors', 'about', 'teachers', 'news', 'media', 'impact', 'ppdb'] as $section) {
                $sections[$section] = isset($_POST['sections'][$section]);
            }
            save_setting('section_visibility', (string) json_encode($sections), true);

            $secretMap = [
                'secret_instagram_user_id' => 'instagram_user_id',
                'secret_instagram_access_token' => 'instagram_access_token',
                'secret_instagram_graph_version' => 'instagram_graph_version',
                'secret_youtube_api_key' => 'youtube_api_key',
                'secret_youtube_channel_id' => 'youtube_channel_id',
            ];
            foreach ($secretMap as $field => $key) {
                $value = trim((string) ($_POST[$field] ?? ''));
                if ($value === '') {
                    continue;
                }
                $value = admin_clip($value, $key === 'instagram_access_token' ? 4000 : 240);
                if ($key === 'instagram_user_id' && !preg_match('/^\d{5,30}$/', $value)) {
                    throw new RuntimeException('Instagram User ID harus berupa angka yang diberikan oleh Meta.');
                }
                if ($key === 'instagram_graph_version' && !preg_match('/^v\d+\.\d+$/', $value)) {
                    throw new RuntimeException('Versi Graph API harus mengikuti format vNN.N.');
                }
                if ($key === 'youtube_channel_id' && !preg_match('/^UC[A-Za-z0-9_-]{20,40}$/', $value)) {
                    throw new RuntimeException('YouTube Channel ID belum sesuai format kanal YouTube.');
                }
                save_setting($key, $value, false);
            }
            foreach ((array) ($_POST['clear_secrets'] ?? []) as $secretKey) {
                if (in_array($secretKey, ['instagram_user_id', 'instagram_access_token', 'youtube_api_key', 'youtube_channel_id'], true)) {
                    save_setting($secretKey, '', false);
                }
            }
            $pdo->commit();
            $success = 'Pengaturan situs berhasil disimpan. Rahasia API hanya disimpan di server dan tidak dikirim ke browser publik.';

        } elseif ($action === 'save_teacher') {
            $id = max(0, (int) ($_POST['id'] ?? 0));
            $name = admin_clip((string) ($_POST['name'] ?? ''), 160);
            $role = admin_clip((string) ($_POST['role'] ?? ''), 160);
            $department = admin_clip((string) ($_POST['department'] ?? ''), 80);
            $specialty = admin_clip((string) ($_POST['specialty'] ?? ''), 220);
            $bio = admin_clip((string) ($_POST['bio'] ?? ''), 500);
            $photo = trim((string) ($_POST['photo'] ?? ''));
            $uploaded = admin_upload_image('photo_file');
            if ($uploaded !== null) {
                $photo = $uploaded;
            }
            if ($name === '' || $role === '') {
                throw new RuntimeException('Nama profil dan peran tenaga pendidik wajib diisi.');
            }
            if (!admin_valid_image_reference($photo)) {
                throw new RuntimeException('Foto harus berupa JPG, PNG, WebP, atau URL HTTPS.');
            }
            $values = [
                'name' => $name,
                'role' => $role,
                'department' => $department !== '' ? $department : 'GURU',
                'specialty' => $specialty,
                'bio' => $bio,
                'photo' => $photo,
                'sort_order' => (int) ($_POST['sort_order'] ?? 0),
                'is_published' => isset($_POST['is_published']) ? 1 : 0,
            ];
            if ($id > 0) {
                $values['id'] = $id;
                $statement = $pdo->prepare('UPDATE teachers SET name=:name, `role`=:role, department=:department, specialty=:specialty, bio=:bio, photo=:photo, sort_order=:sort_order, is_published=:is_published WHERE id=:id');
                $statement->execute($values);
            } else {
                $statement = $pdo->prepare('INSERT INTO teachers (name, `role`, department, specialty, bio, photo, sort_order, is_published) VALUES (:name,:role,:department,:specialty,:bio,:photo,:sort_order,:is_published)');
                $statement->execute($values);
            }
            $success = 'Profil tenaga pendidik berhasil disimpan.';

        } elseif ($action === 'delete_teacher') {
            $statement = $pdo->prepare('DELETE FROM teachers WHERE id = :id');
            $statement->execute(['id' => (int) ($_POST['id'] ?? 0)]);
            $success = 'Profil guru dihapus.';
            $activeTab = 'teachers';

        } elseif ($action === 'save_post') {
            $id = max(0, (int) ($_POST['id'] ?? 0));
            $title = admin_clip((string) ($_POST['title'] ?? ''), 220);
            $slug = admin_slug((string) ($_POST['slug'] ?? ''));
            if ($slug === '') {
                $slug = admin_slug($title);
            }
            $category = admin_clip((string) ($_POST['category'] ?? ''), 100);
            $excerpt = admin_clip((string) ($_POST['excerpt'] ?? ''), 420);
            $content = trim((string) ($_POST['content'] ?? ''));
            $cover = trim((string) ($_POST['cover_image'] ?? ''));
            $uploaded = admin_upload_image('cover_file');
            if ($uploaded !== null) {
                $cover = $uploaded;
            }
            if ($title === '' || $slug === '' || $content === '') {
                throw new RuntimeException('Judul, slug yang valid, dan isi berita wajib diisi.');
            }
            if (!admin_valid_image_reference($cover)) {
                throw new RuntimeException('Sampul harus berupa JPG, PNG, WebP, atau URL HTTPS.');
            }
            $status = (($_POST['status'] ?? 'draft') === 'published') ? 'published' : 'draft';
            $publishedInput = trim((string) ($_POST['published_at'] ?? ''));
            $publishedAt = null;
            if ($publishedInput !== '') {
                $date = DateTime::createFromFormat('Y-m-d\\TH:i', $publishedInput);
                if (!$date) {
                    throw new RuntimeException('Tanggal terbit tidak valid.');
                }
                $publishedAt = $date->format('Y-m-d H:i:s');
            } elseif ($status === 'published') {
                $publishedAt = date('Y-m-d H:i:s');
            }
            $values = [
                'slug' => $slug,
                'title' => $title,
                'category' => $category !== '' ? $category : 'Informasi Sekolah',
                'excerpt' => $excerpt,
                'content' => $content,
                'cover_image' => $cover,
                'status' => $status,
                'published_at' => $publishedAt,
            ];
            if ($id > 0) {
                $values['id'] = $id;
                $statement = $pdo->prepare('UPDATE posts SET slug=:slug, title=:title, category=:category, excerpt=:excerpt, content=:content, cover_image=:cover_image, status=:status, published_at=:published_at WHERE id=:id');
                $statement->execute($values);
            } else {
                $statement = $pdo->prepare('INSERT INTO posts (slug,title,category,excerpt,content,cover_image,status,published_at) VALUES (:slug,:title,:category,:excerpt,:content,:cover_image,:status,:published_at)');
                $statement->execute($values);
            }
            $success = $status === 'published' ? 'Berita berhasil disimpan dan diterbitkan.' : 'Draf berita berhasil disimpan.';

        } elseif ($action === 'delete_post') {
            $statement = $pdo->prepare('DELETE FROM posts WHERE id = :id');
            $statement->execute(['id' => (int) ($_POST['id'] ?? 0)]);
            $success = 'Berita dihapus.';
            $activeTab = 'posts';

        } elseif ($action === 'save_gallery') {
            $id = max(0, (int) ($_POST['id'] ?? 0));
            $image = trim((string) ($_POST['image'] ?? ''));
            $uploaded = admin_upload_image('image_file');
            if ($uploaded !== null) {
                $image = $uploaded;
            }
            if ($image === '' || !admin_valid_image_reference($image)) {
                throw new RuntimeException('Pilih gambar JPG, PNG, WebP, atau isi URL gambar HTTPS.');
            }
            $caption = admin_clip((string) ($_POST['caption'] ?? ''), 240);
            $altText = admin_clip((string) ($_POST['alt_text'] ?? $caption), 240);
            $permalink = trim((string) ($_POST['permalink'] ?? ''));
            if ($permalink !== '' && (!filter_var($permalink, FILTER_VALIDATE_URL) || strtolower((string) parse_url($permalink, PHP_URL_SCHEME)) !== 'https')) {
                throw new RuntimeException('Tautan sumber galeri harus HTTPS atau dikosongkan.');
            }
            $values = [
                'image' => $image,
                'caption' => $caption,
                'alt_text' => $altText !== '' ? $altText : 'Foto kegiatan sekolah',
                'permalink' => $permalink,
                'sort_order' => (int) ($_POST['sort_order'] ?? 0),
                'is_published' => isset($_POST['is_published']) ? 1 : 0,
            ];
            if ($id > 0) {
                $values['id'] = $id;
                $statement = $pdo->prepare("UPDATE gallery SET image=:image, caption=:caption, alt_text=:alt_text, permalink=:permalink, source='manual', source_id=NULL, sort_order=:sort_order, is_published=:is_published WHERE id=:id");
                $statement->execute($values);
            } else {
                $statement = $pdo->prepare("INSERT INTO gallery (image,caption,alt_text,permalink,source,source_id,sort_order,is_published) VALUES (:image,:caption,:alt_text,:permalink,'manual',NULL,:sort_order,:is_published)");
                $statement->execute($values);
            }
            $success = 'Foto galeri berhasil disimpan.';

        } elseif ($action === 'delete_gallery') {
            $statement = $pdo->prepare('DELETE FROM gallery WHERE id = :id');
            $statement->execute(['id' => (int) ($_POST['id'] ?? 0)]);
            $success = 'Foto galeri dihapus.';
            $activeTab = 'gallery';

        } elseif ($action === 'change_password') {
            $oldPassword = (string) ($_POST['old_password'] ?? '');
            $newPassword = (string) ($_POST['new_password'] ?? '');
            $confirmation = (string) ($_POST['new_password_confirmation'] ?? '');
            $statement = $pdo->prepare('SELECT password_hash FROM admins WHERE id = :id LIMIT 1');
            $statement->execute(['id' => (int) $_SESSION['admin_id']]);
            $hash = (string) $statement->fetchColumn();
            if (!password_verify($oldPassword, $hash)) {
                throw new RuntimeException('Kata sandi saat ini tidak cocok.');
            }
            if (strlen($newPassword) < 12 || $newPassword !== $confirmation) {
                throw new RuntimeException('Kata sandi baru minimal 12 karakter dan konfirmasinya harus sama.');
            }
            $pdo->prepare('UPDATE admins SET password_hash = :password_hash WHERE id = :id')->execute([
                'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                'id' => (int) $_SESSION['admin_id'],
            ]);
            session_regenerate_id(true);
            $success = 'Kata sandi admin berhasil diganti.';
            $activeTab = 'settings';
        } else {
            throw new RuntimeException('Aksi panel tidak dikenali.');
        }
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'Perubahan belum dapat disimpan. Periksa data, batas unggahan, dan koneksi database.';
    }
}

$settings = [];
foreach ($pdo->query('SELECT setting_key, setting_value FROM settings')->fetchAll() as $row) {
    $settings[(string) $row['setting_key']] = (string) $row['setting_value'];
}
if (strtolower(trim((string) ($settings['theme_primary'] ?? ''))) === '#0e4a3b') {
    $settings['theme_primary'] = '#0A3F2F';
}
$visibility = json_decode((string) ($settings['section_visibility'] ?? '{}'), true);
if (!is_array($visibility)) {
    $visibility = [];
}
$secretKeys = ['instagram_user_id', 'instagram_access_token', 'youtube_api_key', 'youtube_channel_id'];
$secretConfigured = [];
foreach ($secretKeys as $key) {
    $secretConfigured[$key] = !empty($settings[$key]);
}

$counts = [
    'posts' => (int) $pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn(),
    'published_posts' => (int) $pdo->query("SELECT COUNT(*) FROM posts WHERE status='published' AND published_at <= NOW()")->fetchColumn(),
    'teachers' => (int) $pdo->query('SELECT COUNT(*) FROM teachers WHERE is_published=1')->fetchColumn(),
    'gallery' => (int) $pdo->query('SELECT COUNT(*) FROM gallery WHERE is_published=1')->fetchColumn(),
    'videos' => (int) $pdo->query('SELECT COUNT(*) FROM videos')->fetchColumn(),
];
$posts = $pdo->query('SELECT id,slug,title,category,status,published_at FROM posts ORDER BY updated_at DESC,id DESC LIMIT 50')->fetchAll();
$teachers = $pdo->query('SELECT id,name,`role`,department,photo,sort_order,is_published FROM teachers ORDER BY sort_order,id')->fetchAll();
$galleryItems = $pdo->query('SELECT id,image,caption,source,sort_order,is_published FROM gallery ORDER BY sort_order,id DESC LIMIT 100')->fetchAll();
$ppdbApplicants = [];
$ppdbTableReady = true;
try {
    $ppdbApplicants = $pdo->query('SELECT id,nama,alamat,asal_sekolah,email,jurusan,nomor_hp,status,created_at FROM ppdb_applicants ORDER BY created_at DESC,id DESC LIMIT 200')->fetchAll();
} catch (Throwable $exception) {
    $ppdbTableReady = false;
}
$ppdbStatusLabels = [
    'baru' => 'Baru',
    'dihubungi' => 'Sudah dihubungi',
    'proses' => 'Proses PPDB',
    'diterima' => 'Diterima',
    'tidak_dilanjutkan' => 'Tidak melanjutkan',
];

$editPost = null;
if (isset($_GET['edit_post'])) {
    $statement = $pdo->prepare('SELECT * FROM posts WHERE id=:id LIMIT 1');
    $statement->execute(['id' => (int) $_GET['edit_post']]);
    $editPost = $statement->fetch() ?: null;
    $activeTab = 'posts';
}
$editTeacher = null;
if (isset($_GET['edit_teacher'])) {
    $statement = $pdo->prepare('SELECT * FROM teachers WHERE id=:id LIMIT 1');
    $statement->execute(['id' => (int) $_GET['edit_teacher']]);
    $editTeacher = $statement->fetch() ?: null;
    $activeTab = 'teachers';
}
$editGallery = null;
if (isset($_GET['edit_gallery'])) {
    $statement = $pdo->prepare('SELECT * FROM gallery WHERE id=:id LIMIT 1');
    $statement->execute(['id' => (int) $_GET['edit_gallery']]);
    $editGallery = $statement->fetch() ?: null;
    $activeTab = 'gallery';
}

$tabs = [
    'dashboard' => 'Ringkasan',
    'posts' => 'Berita & informasi',
    'teachers' => 'Galeri guru',
    'gallery' => 'Galeri foto',
    'ppdb' => 'Pendaftar PPDB',
    'settings' => 'Tampilan & integrasi',
];
?><!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Panel admin | CMS SMK Asy-Syafi'iyah</title>
  <style>
    :root{--green:#0A3F2F;--green-dark:#083c30;--soft:#e8f2ee;--gold:#f2b705;--ink:#15271f;--muted:#6b7b71;--line:#dce6e0;--page:#f4f7f5;--white:#fff;--radius:13px;--shadow:0 8px 26px #1d402c0a}*{box-sizing:border-box}body{margin:0;background:var(--page);color:var(--ink);font:14px/1.6 system-ui,-apple-system,"Segoe UI",sans-serif}a{color:var(--green);text-decoration:none}button,input,select,textarea{font:inherit}button{cursor:pointer}.top{position:sticky;z-index:10;top:0;min-height:66px;display:flex;align-items:center;justify-content:space-between;gap:15px;padding:10px max(18px,calc((100vw - 1200px)/2));border-bottom:1px solid var(--line);background:#fff}.brand{display:flex;align-items:center;gap:11px;color:var(--green);font-weight:850}.brand-mark{width:36px;height:36px;display:grid;place-items:center;border-radius:10px;background:var(--green);color:var(--gold);font-size:18px}.brand small{display:block;color:var(--muted);font-size:9px;letter-spacing:.12em}.top-actions{display:flex;align-items:center;gap:14px}.top-actions span{color:var(--muted);font-size:12px}.logout{padding:8px 12px;border:1px solid var(--line);border-radius:8px;background:#fff;color:var(--green);font-weight:750}.layout{width:min(1200px,calc(100% - 36px));display:grid;grid-template-columns:210px minmax(0,1fr);gap:26px;margin:25px auto 60px}.side{position:sticky;top:88px;align-self:start;padding:10px;border:1px solid var(--line);border-radius:var(--radius);background:#fff;box-shadow:var(--shadow)}.side a{display:flex;align-items:center;gap:10px;min-height:43px;padding:9px 11px;border-radius:8px;color:#506158;font-size:12px;font-weight:750}.side a.active,.side a:hover{background:var(--soft);color:var(--green)}.main{min-width:0}.page-head{display:flex;align-items:end;justify-content:space-between;gap:20px;margin:0 0 18px}.page-head h1{margin:0;font-size:clamp(25px,3vw,34px);line-height:1.15;letter-spacing:-.04em}.page-head p{margin:5px 0 0;color:var(--muted);font-size:12px}.open-site{padding:9px 13px;border-radius:8px;background:var(--green);color:#fff;font-size:11px;font-weight:800}.notice{margin:0 0 17px;padding:12px 15px;border-radius:9px;font-size:13px}.notice.success{background:#e5f5eb;color:#155a37}.notice.error{background:#fff0e9;color:#91421b}.card{margin-bottom:17px;padding:20px;border:1px solid var(--line);border-radius:var(--radius);background:#fff;box-shadow:var(--shadow)}.card h2{margin:0 0 4px;font-size:18px;letter-spacing:-.02em}.card h3{margin:0 0 13px;font-size:14px}.muted{color:var(--muted);font-size:12px}.metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:13px;margin-bottom:17px}.metric{padding:16px;border:1px solid var(--line);border-radius:11px;background:#fff}.metric strong{display:block;color:var(--green);font-size:27px;line-height:1.1}.metric span{color:var(--muted);font-size:11px}.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.field{min-width:0}.field.full{grid-column:1/-1}label{display:block;margin:0 0 6px;color:#35483e;font-size:11px;font-weight:800}input[type=text],input[type=email],input[type=password],input[type=url],input[type=datetime-local],input[type=number],input[type=color],select,textarea{width:100%;min-height:42px;padding:9px 11px;border:1px solid #cfdbd4;border-radius:8px;background:#fff;color:var(--ink)}input[type=color]{height:44px;padding:5px}textarea{min-height:100px;resize:vertical}textarea.large{min-height:200px}input:focus,select:focus,textarea:focus{outline:3px solid #f2b70555;border-color:var(--green)}.help{display:block;margin-top:5px;color:#7a8980;font-size:10px;line-height:1.5}.checks{display:flex;flex-wrap:wrap;gap:8px 15px}.check{display:inline-flex;align-items:center;gap:7px;color:#40544a;font-size:12px}.check input{accent-color:var(--green)}.button{min-height:42px;display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:9px 15px;border:0;border-radius:8px;background:var(--green);color:#fff;font-size:12px;font-weight:800}.button:hover{background:var(--green-dark)}.button.secondary{border:1px solid var(--line);background:#fff;color:var(--green)}.button.danger{background:#fff2ed;color:#98401e}.form-actions{display:flex;flex-wrap:wrap;align-items:center;gap:9px;margin-top:17px}.table-scroll{overflow-x:auto}table{width:100%;border-collapse:collapse;font-size:12px}th,td{padding:10px 9px;border-bottom:1px solid #e9efeb;text-align:left;vertical-align:middle}th{color:#76857c;font-size:10px;text-transform:uppercase;letter-spacing:.06em}td strong{display:block;color:#2b3d33;font-size:12px}td small{color:var(--muted)}.actions{display:flex;align-items:center;gap:7px;white-space:nowrap}.actions a,.actions button{padding:6px 9px;border:1px solid var(--line);border-radius:6px;background:#fff;color:var(--green);font-size:10px;font-weight:750}.actions button{color:#91421b}.status{display:inline-block;padding:3px 8px;border-radius:20px;background:var(--soft);color:var(--green);font-size:10px;font-weight:800}.status.draft{background:#f0f1f0;color:#6b756e}.preview{width:64px;height:46px;object-fit:cover;border-radius:6px;background:var(--soft)}.subhead{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:12px}.subhead h2{margin:0}.secret-row{padding:14px 0;border-bottom:1px solid #e8eeea}.secret-row:last-child{border-bottom:0}.secret-status{display:inline-flex;align-items:center;gap:6px;margin:0 0 8px;color:#597064;font-size:10px}.secret-status::before{width:7px;height:7px;border-radius:50%;background:#9ba9a0;content:""}.secret-status.on::before{background:#31a66b}.secret-status.on{color:#22734c}.danger-box{border-color:#f0d6ca;background:#fffdfb}.fine-print{font-size:11px;line-height:1.65;color:#6c7b72}.empty{padding:20px;border:1px dashed #ccd9d1;border-radius:9px;color:var(--muted);font-size:12px;text-align:center}.setup-note{padding:14px;border-radius:8px;background:#fff8e3;color:#66501a;font-size:12px}.ppdb-status-form{display:flex;align-items:center;gap:6px}.ppdb-status-form select{width:auto;min-height:31px;padding:5px 7px;font-size:10px}
    @media(max-width:900px){.layout{grid-template-columns:1fr;gap:16px}.side{position:static;display:flex;overflow-x:auto;gap:3px;padding:6px}.side a{flex:0 0 auto}.metrics{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:620px){.top{padding-inline:14px}.top-actions span{display:none}.layout{width:calc(100% - 24px);margin-top:14px}.page-head{align-items:flex-start;flex-direction:column}.form-grid{grid-template-columns:1fr}.field.full{grid-column:auto}.card{padding:15px}.metrics{gap:8px}.metric{padding:12px}.metric strong{font-size:23px}.side a{font-size:10px;padding:7px}.open-site{width:100%;text-align:center}}
  </style>
</head>
<body>
  <header class="top">
    <a class="brand" href="index.php"><span class="brand-mark" aria-hidden="true">✦</span><span>CMS SEKOLAH<small>ASY-SYAFI'IYAH · LOHGUNG</small></span></a>
    <div class="top-actions"><span>Halo, <?= h($_SESSION['admin_name'] ?? 'Admin') ?></span><a class="open-site" href="../index.php" target="_blank" rel="noopener noreferrer">Lihat situs ↗</a><form method="post" action="logout.php"><button class="logout" type="submit" name="csrf_token" value="<?= h(admin_csrf_token()) ?>">Keluar</button></form></div>
  </header>
  <div class="layout">
    <nav class="side" aria-label="Menu admin">
      <?php foreach ($tabs as $key => $label): ?><a class="<?= $activeTab === $key ? 'active' : '' ?>" href="index.php?tab=<?= h($key) ?>"><?= h($label) ?></a><?php endforeach; ?>
    </nav>
    <main class="main">
      <div class="page-head"><div><h1><?= h($tabs[$activeTab]) ?></h1><p>Kelola konten publik SMK Terpadu Ponpes Asy-Syafi'iyah Lohgung.</p></div></div>
      <?php if ($success !== ''): ?><div class="notice success" role="status"><?= h($success) ?></div><?php endif; ?>
      <?php if ($error !== ''): ?><div class="notice error" role="alert"><?= h($error) ?></div><?php endif; ?>

      <?php if ($activeTab === 'dashboard'): ?>
        <div class="metrics">
          <div class="metric"><strong><?= $counts['published_posts'] ?></strong><span>Berita terbit</span></div>
          <div class="metric"><strong><?= $counts['teachers'] ?></strong><span>Profil guru tampil</span></div>
          <div class="metric"><strong><?= $counts['gallery'] ?></strong><span>Foto galeri tampil</span></div>
          <div class="metric"><strong><?= $counts['videos'] ?></strong><span>Video YouTube tersinkron</span></div>
        </div>
        <section class="card"><h2>Mulai kelola situs</h2><p class="muted">Atur teks, warna, dan gambar beranda; tambahkan profil guru serta berita; unggah dokumentasi; dan hubungkan akun media sosial melalui kredensial API server.</p><div class="form-actions"><a class="button" href="index.php?tab=settings">Atur tampilan &amp; integrasi</a><a class="button secondary" href="index.php?tab=posts">Kelola berita</a><a class="button secondary" href="index.php?tab=teachers">Kelola guru</a><a class="button secondary" href="index.php?tab=ppdb">Pendaftar PPDB</a></div></section>
        <section class="card"><h2>Sebelum rilis</h2><p class="fine-print">Ganti foto dan profil contoh dengan materi resmi. Pastikan domain pada pengaturan situs benar, verifikasi akses admin dengan kata sandi kuat, lalu jadwalkan cron sinkronisasi jika API sosial sudah disiapkan. Jangan memasukkan token API ke tautan publik atau berkas JavaScript.</p><div class="setup-note">Foto peran guru yang sudah ada hanya contoh. Nama guru, gelar, foto, dan kualifikasi resmi perlu dikonfirmasi sekolah.</div></section>
      <?php elseif ($activeTab === 'posts'): ?>
        <?php $p = $editPost ?: []; ?>
        <section class="card">
          <div class="subhead"><div><h2><?= $editPost ? 'Ubah berita' : 'Tulis berita / informasi' ?></h2><p class="muted">Isi ditampilkan di beranda, halaman artikel, sitemap, dan RSS saat berstatus terbit.</p></div><?php if ($editPost): ?><a class="button secondary" href="index.php?tab=posts">Berita baru</a><?php endif; ?></div>
          <form method="post" enctype="multipart/form-data">
            <?= admin_csrf_field() ?><input type="hidden" name="action" value="save_post"><input type="hidden" name="tab" value="posts"><input type="hidden" name="id" value="<?= (int) ($p['id'] ?? 0) ?>">
            <div class="form-grid">
              <div class="field full"><label for="post-title">Judul</label><input id="post-title" type="text" name="title" maxlength="220" required value="<?= h($p['title'] ?? '') ?>"></div>
              <div class="field"><label for="post-slug">Slug URL (boleh dikosongkan untuk dibuat otomatis)</label><input id="post-slug" type="text" name="slug" maxlength="190" value="<?= h($p['slug'] ?? '') ?>"></div>
              <div class="field"><label for="post-category">Kategori</label><input id="post-category" type="text" name="category" maxlength="100" value="<?= h($p['category'] ?? 'Informasi Sekolah') ?>"></div>
              <div class="field full"><label for="post-excerpt">Ringkasan</label><textarea id="post-excerpt" name="excerpt" maxlength="420" rows="3"><?= h($p['excerpt'] ?? '') ?></textarea></div>
              <div class="field full"><label for="post-content">Isi berita</label><textarea class="large" id="post-content" name="content" required><?= h($p['content'] ?? '') ?></textarea><span class="help">Teks biasa; baris kosong menjadi paragraf. HTML dan skrip tidak dijalankan.</span></div>
              <div class="field"><label for="cover-image">Sampul: path/URL gambar sekarang</label><input id="cover-image" type="text" name="cover_image" value="<?= h($p['cover_image'] ?? '') ?>"><span class="help">Contoh: public/images/dkv-studio.jpg atau uploads/2026/10/foto.jpg</span></div>
              <div class="field"><label for="cover-file">Unggah sampul JPG, PNG, WebP (maks. 5 MB)</label><input id="cover-file" type="file" name="cover_file" accept="image/jpeg,image/png,image/webp"></div>
              <div class="field"><label for="post-status">Status</label><select id="post-status" name="status"><option value="draft" <?= ($p['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draf</option><option value="published" <?= ($p['status'] ?? '') === 'published' ? 'selected' : '' ?>>Terbit</option></select></div>
              <div class="field"><label for="post-date">Tanggal terbit (opsional)</label><input id="post-date" type="datetime-local" name="published_at" value="<?= !empty($p['published_at']) ? h(date('Y-m-d\\TH:i', strtotime((string) $p['published_at']))) : '' ?>"></div>
            </div>
            <div class="form-actions"><button class="button" type="submit">Simpan berita</button><?php if ($editPost): ?><a class="button secondary" href="index.php?tab=posts">Batal mengubah</a><?php endif; ?></div>
          </form>
        </section>
        <section class="card"><h2>Daftar berita</h2><p class="muted"><?= $counts['posts'] ?> item tersimpan.</p>
          <?php if ($posts): ?><div class="table-scroll"><table><thead><tr><th>Judul</th><th>Status</th><th>Tanggal</th><th>Aksi</th></tr></thead><tbody>
          <?php foreach ($posts as $post): ?><tr><td><strong><?= h($post['title']) ?></strong><small><?= h($post['category']) ?> · /berita/<?= h($post['slug']) ?></small></td><td><span class="status <?= $post['status'] === 'draft' ? 'draft' : '' ?>"><?= $post['status'] === 'published' ? 'Terbit' : 'Draf' ?></span></td><td><?= h(indonesian_date((string) $post['published_at'])) ?></td><td><div class="actions"><a href="index.php?tab=posts&amp;edit_post=<?= (int) $post['id'] ?>">Ubah</a><form method="post" onsubmit="return confirm('Hapus berita ini?')"><?= admin_csrf_field() ?><input type="hidden" name="action" value="delete_post"><input type="hidden" name="tab" value="posts"><input type="hidden" name="id" value="<?= (int) $post['id'] ?>"><button type="submit">Hapus</button></form></div></td></tr><?php endforeach; ?>
          </tbody></table></div><?php else: ?><div class="empty">Belum ada berita tersimpan.</div><?php endif; ?>
        </section>
      <?php elseif ($activeTab === 'teachers'): ?>
        <?php $t = $editTeacher ?: []; ?>
        <section class="card">
          <div class="subhead"><div><h2><?= $editTeacher ? 'Ubah profil guru' : 'Tambah profil guru' ?></h2><p class="muted">Gunakan identitas dan foto yang telah disetujui sekolah.</p></div><?php if ($editTeacher): ?><a class="button secondary" href="index.php?tab=teachers">Profil baru</a><?php endif; ?></div>
          <form method="post" enctype="multipart/form-data">
            <?= admin_csrf_field() ?><input type="hidden" name="action" value="save_teacher"><input type="hidden" name="tab" value="teachers"><input type="hidden" name="id" value="<?= (int) ($t['id'] ?? 0) ?>">
            <div class="form-grid">
              <div class="field"><label for="teacher-name">Nama / label profil</label><input id="teacher-name" type="text" name="name" maxlength="160" required value="<?= h($t['name'] ?? '') ?>"></div>
              <div class="field"><label for="teacher-role">Peran / posisi</label><input id="teacher-role" type="text" name="role" maxlength="160" required value="<?= h($t['role'] ?? 'TENAGA PENDIDIK') ?>"></div>
              <div class="field"><label for="teacher-department">Badge / jurusan</label><input id="teacher-department" type="text" name="department" maxlength="80" value="<?= h($t['department'] ?? '') ?>"></div>
              <div class="field"><label for="teacher-specialty">Bidang keahlian</label><input id="teacher-specialty" type="text" name="specialty" maxlength="220" value="<?= h($t['specialty'] ?? '') ?>"></div>
              <div class="field full"><label for="teacher-bio">Keterangan</label><textarea id="teacher-bio" name="bio" maxlength="500" rows="3"><?= h($t['bio'] ?? '') ?></textarea></div>
              <div class="field"><label for="teacher-photo">Path/URL foto</label><input id="teacher-photo" type="text" name="photo" value="<?= h($t['photo'] ?? '') ?>"></div>
              <div class="field"><label for="teacher-photo-file">Unggah foto (JPG, PNG, WebP)</label><input id="teacher-photo-file" type="file" name="photo_file" accept="image/jpeg,image/png,image/webp"></div>
              <div class="field"><label for="teacher-sort">Urutan tampil</label><input id="teacher-sort" type="number" name="sort_order" value="<?= (int) ($t['sort_order'] ?? 0) ?>"></div>
              <div class="field"><label class="check"><input type="checkbox" name="is_published" value="1" <?= !array_key_exists('is_published', $t) || (int) $t['is_published'] === 1 ? 'checked' : '' ?>> Tampilkan profil pada website</label><span class="help">Hapus centang untuk menyimpan profil sebagai tidak tampil.</span></div>
            </div>
            <div class="form-actions"><button class="button" type="submit">Simpan profil</button><?php if ($editTeacher): ?><a class="button secondary" href="index.php?tab=teachers">Batal mengubah</a><?php endif; ?></div>
          </form>
        </section>
        <section class="card"><h2>Profil tenaga pendidik</h2><p class="muted">Kartu peran contoh tetap perlu diperbarui menjadi profil resmi.</p>
          <?php if ($teachers): ?><div class="table-scroll"><table><thead><tr><th>Foto</th><th>Profil</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
          <?php foreach ($teachers as $teacher): ?><tr><td><?php if ($teacher['photo']): ?><img class="preview" src="<?= h($teacher['photo']) ?>" alt=""><?php endif; ?></td><td><strong><?= h($teacher['name']) ?></strong><small><?= h($teacher['role']) ?> · <?= h($teacher['department']) ?></small></td><td><span class="status <?= (int) $teacher['is_published'] ? '' : 'draft' ?>"><?= (int) $teacher['is_published'] ? 'Tampil' : 'Disimpan' ?></span></td><td><div class="actions"><a href="index.php?tab=teachers&amp;edit_teacher=<?= (int) $teacher['id'] ?>">Ubah</a><form method="post" onsubmit="return confirm('Hapus profil ini?')"><?= admin_csrf_field() ?><input type="hidden" name="action" value="delete_teacher"><input type="hidden" name="tab" value="teachers"><input type="hidden" name="id" value="<?= (int) $teacher['id'] ?>"><button type="submit">Hapus</button></form></div></td></tr><?php endforeach; ?>
          </tbody></table></div><?php else: ?><div class="empty">Belum ada profil guru.</div><?php endif; ?>
        </section>
      <?php elseif ($activeTab === 'gallery'): ?>
        <?php $g = $editGallery ?: []; ?>
        <section class="card">
          <div class="subhead"><div><h2><?= $editGallery ? 'Ubah foto galeri' : 'Tambah foto galeri' ?></h2><p class="muted">Unggah dokumentasi resmi atau gunakan URL gambar HTTPS.</p></div><?php if ($editGallery): ?><a class="button secondary" href="index.php?tab=gallery">Foto baru</a><?php endif; ?></div>
          <form method="post" enctype="multipart/form-data">
            <?= admin_csrf_field() ?><input type="hidden" name="action" value="save_gallery"><input type="hidden" name="tab" value="gallery"><input type="hidden" name="id" value="<?= (int) ($g['id'] ?? 0) ?>">
            <div class="form-grid">
              <div class="field"><label for="gallery-image">Path/URL gambar</label><input id="gallery-image" type="text" name="image" value="<?= h($g['image'] ?? '') ?>"></div>
              <div class="field"><label for="gallery-file">Unggah foto (JPG, PNG, WebP)</label><input id="gallery-file" type="file" name="image_file" accept="image/jpeg,image/png,image/webp"></div>
              <div class="field"><label for="gallery-caption">Keterangan</label><input id="gallery-caption" type="text" name="caption" maxlength="240" value="<?= h($g['caption'] ?? '') ?>"></div>
              <div class="field"><label for="gallery-alt">Teks alternatif foto</label><input id="gallery-alt" type="text" name="alt_text" maxlength="240" value="<?= h($g['alt_text'] ?? '') ?>"></div>
              <div class="field"><label for="gallery-permalink">Tautan sumber (opsional)</label><input id="gallery-permalink" type="url" name="permalink" value="<?= h($g['permalink'] ?? '') ?>"></div>
              <div class="field"><label for="gallery-sort">Urutan tampil</label><input id="gallery-sort" type="number" name="sort_order" value="<?= (int) ($g['sort_order'] ?? 0) ?>"></div>
              <div class="field"><label class="check"><input type="checkbox" name="is_published" value="1" <?= !array_key_exists('is_published', $g) || (int) $g['is_published'] === 1 ? 'checked' : '' ?>> Tampilkan di website</label></div>
            </div>
            <div class="form-actions"><button class="button" type="submit">Simpan foto</button><?php if ($editGallery): ?><a class="button secondary" href="index.php?tab=gallery">Batal mengubah</a><?php endif; ?></div>
          </form>
        </section>
        <section class="card"><h2>Daftar galeri</h2><div class="table-scroll"><table><thead><tr><th>Pratinjau</th><th>Foto</th><th>Sumber</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
          <?php foreach ($galleryItems as $item): ?><tr><td><img class="preview" src="<?= h($item['image']) ?>" alt=""></td><td><strong><?= h($item['caption']) ?></strong><small><?= h($item['image']) ?></small></td><td><?= $item['source'] === 'instagram' ? 'Instagram' : 'Manual' ?></td><td><span class="status <?= (int) $item['is_published'] ? '' : 'draft' ?>"><?= (int) $item['is_published'] ? 'Tampil' : 'Disimpan' ?></span></td><td><div class="actions"><a href="index.php?tab=gallery&amp;edit_gallery=<?= (int) $item['id'] ?>">Ubah</a><form method="post" onsubmit="return confirm('Hapus foto ini?')"><?= admin_csrf_field() ?><input type="hidden" name="action" value="delete_gallery"><input type="hidden" name="tab" value="gallery"><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><button type="submit">Hapus</button></form></div></td></tr><?php endforeach; ?>
        </tbody></table></div><?php if (!$galleryItems): ?><div class="empty">Belum ada foto galeri.</div><?php endif; ?></section>
      <?php elseif ($activeTab === 'ppdb'): ?>
        <section class="card">
          <h2>Data minat calon siswa</h2>
          <p class="muted">Maksimal 200 data terbaru ditampilkan. Informasi ini bersifat pribadi dan hanya digunakan untuk tindak lanjut PPDB oleh admin sekolah.</p>
          <div class="setup-note">Jadwal, persyaratan, biaya, serta keputusan penerimaan tetap perlu dikonfirmasi oleh pihak sekolah. Jangan membagikan data pendaftar melalui kanal publik.</div>
        </section>
        <?php if (!$ppdbTableReady): ?>
          <section class="card"><div class="empty">Tabel pendaftar belum tersedia. Impor <code>database/migrations/20261014_create_ppdb_applicants.sql</code> melalui phpMyAdmin, lalu muat ulang panel ini.</div></section>
        <?php elseif ($ppdbApplicants): ?>
          <section class="card">
            <div class="table-scroll"><table><thead><tr><th>Calon siswa</th><th>Jurusan</th><th>Kontak</th><th>Alamat</th><th>Dikirim</th><th>Status &amp; aksi</th></tr></thead><tbody>
              <?php foreach ($ppdbApplicants as $applicant): ?>
                <?php $applicantStatus = (string) $applicant['status']; ?>
                <tr>
                  <td><strong><?= h($applicant['nama']) ?></strong><small>Asal sekolah: <?= h($applicant['asal_sekolah']) ?></small></td>
                  <td><span class="status"><?= h($applicant['jurusan']) ?></span></td>
                  <td><strong><a href="mailto:<?= h($applicant['email']) ?>"><?= h($applicant['email']) ?></a></strong><small><a href="tel:<?= h($applicant['nomor_hp']) ?>"><?= h($applicant['nomor_hp']) ?></a></small></td>
                  <td><details><summary>Lihat alamat</summary><div class="fine-print"><?= nl2br(h($applicant['alamat'])) ?></div></details></td>
                  <td><?= h(indonesian_date((string) $applicant['created_at'])) ?></td>
                  <td>
                    <span class="status"><?= h($ppdbStatusLabels[$applicantStatus] ?? 'Baru') ?></span>
                    <div class="actions" style="margin-top:7px">
                      <form class="ppdb-status-form" method="post" aria-label="Perbarui status <?= h($applicant['nama']) ?>">
                        <?= admin_csrf_field() ?><input type="hidden" name="action" value="update_ppdb_status"><input type="hidden" name="tab" value="ppdb"><input type="hidden" name="id" value="<?= (int) $applicant['id'] ?>">
                        <select name="status" aria-label="Pilih status pendaftar">
                          <?php foreach ($ppdbStatusLabels as $statusValue => $statusLabel): ?><option value="<?= h($statusValue) ?>" <?= $applicantStatus === $statusValue ? 'selected' : '' ?>><?= h($statusLabel) ?></option><?php endforeach; ?>
                        </select>
                        <button type="submit">Simpan</button>
                      </form>
                      <form method="post" onsubmit="return confirm('Hapus data calon siswa ini secara permanen?')">
                        <?= admin_csrf_field() ?><input type="hidden" name="action" value="delete_ppdb_applicant"><input type="hidden" name="tab" value="ppdb"><input type="hidden" name="id" value="<?= (int) $applicant['id'] ?>"><button type="submit">Hapus</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody></table></div>
          </section>
        <?php else: ?>
          <section class="card"><div class="empty">Belum ada data minat PPDB yang masuk.</div></section>
        <?php endif; ?>
      <?php elseif ($activeTab === 'settings'): ?>
        <section class="card"><h2>Teks, identitas, dan tampilan beranda</h2><p class="muted">Edit konten dan warna website. Isi sekolah yang belum dikonfirmasi sebaiknya tidak dipublikasikan.</p>
          <form method="post" enctype="multipart/form-data">
            <?= admin_csrf_field() ?><input type="hidden" name="action" value="save_settings"><input type="hidden" name="tab" value="settings">
            <div class="form-grid">
              <div class="field"><label for="site-title">Judul situs / SEO</label><input id="site-title" type="text" name="site_title" maxlength="180" value="<?= h($settings['site_title'] ?? '') ?>"></div>
              <div class="field"><label for="site-url">Domain resmi (HTTPS)</label><input id="site-url" type="url" name="site_url" required value="<?= h($settings['site_url'] ?? 'https://smkasysyafiiyah.sch.id') ?>"><span class="help">Konfirmasi domain sebelum rilis. Dipakai pada canonical, RSS, dan sitemap.</span></div>
              <div class="field full"><label for="meta-description">Deskripsi mesin pencari</label><textarea id="meta-description" name="meta_description" maxlength="320" rows="2"><?= h($settings['meta_description'] ?? '') ?></textarea></div>
              <div class="field"><label for="brand-short">Nama singkat merek</label><input id="brand-short" type="text" name="brand_short" value="<?= h($settings['brand_short'] ?? '') ?>"></div>
              <div class="field"><label for="brand-tagline">Subjudul merek</label><input id="brand-tagline" type="text" name="brand_tagline" value="<?= h($settings['brand_tagline'] ?? '') ?>"></div>
              <div class="field"><label for="school-name">Nama sekolah</label><input id="school-name" type="text" name="school_name" value="<?= h($settings['school_name'] ?? '') ?>"></div>
              <div class="field"><label for="contact-email">Email kontak resmi</label><input id="contact-email" type="email" name="contact_email" value="<?= h($settings['contact_email'] ?? '') ?>"></div>
              <div class="field"><label for="school-address">Alamat ringkas</label><input id="school-address" type="text" name="school_address" value="<?= h($settings['school_address'] ?? '') ?>"></div>
              <div class="field"><label for="location-text">Lokasi pada hero</label><input id="location-text" type="text" name="location_text" value="<?= h($settings['location_text'] ?? '') ?>"></div>
              <div class="field full"><label for="contact-address">Alamat footer (baris baru diperbolehkan)</label><textarea id="contact-address" name="contact_address" rows="3"><?= h($settings['contact_address'] ?? '') ?></textarea></div>
              <div class="field"><label for="hero-kicker">Label kecil hero</label><input id="hero-kicker" type="text" name="hero_kicker" value="<?= h($settings['hero_kicker'] ?? '') ?>"></div>
              <div class="field"><label for="hero-title">Judul hero, baris 1</label><input id="hero-title" type="text" name="hero_title" value="<?= h($settings['hero_title'] ?? '') ?>"></div>
              <div class="field"><label for="hero-subtitle">Judul hero, baris 2</label><input id="hero-subtitle" type="text" name="hero_subtitle" value="<?= h($settings['hero_subtitle'] ?? '') ?>"></div>
              <div class="field"><label for="hero-highlight">Sorotan hero</label><input id="hero-highlight" type="text" name="hero_highlight" value="<?= h($settings['hero_highlight'] ?? '') ?>"></div>
              <div class="field full"><label for="hero-description">Deskripsi hero</label><textarea id="hero-description" name="hero_description" rows="3"><?= h($settings['hero_description'] ?? '') ?></textarea></div>
              <?php foreach ([
                'logo_image' => ['label' => 'Logo sekolah', 'default' => ''],
                'hero_image' => ['label' => 'Gambar hero — slide 1', 'default' => 'public/images/kampus-hero.jpg'],
                'hero_image_2' => ['label' => 'Gambar hero — slide 2', 'default' => 'public/images/kehidupan-sekolah.jpg'],
                'hero_image_3' => ['label' => 'Gambar hero — slide 3', 'default' => 'public/images/dkv-studio.jpg'],
                'hero_image_4' => ['label' => 'Gambar hero — slide 4', 'default' => 'public/images/tkr-workshop.jpg'],
                'about_image' => ['label' => 'Gambar tentang sekolah', 'default' => 'public/images/kehidupan-sekolah.jpg'],
                'dkv_image' => ['label' => 'Gambar DKV', 'default' => 'public/images/dkv-studio.jpg'],
                'tkr_image' => ['label' => 'Gambar TKR', 'default' => 'public/images/tkr-workshop.jpg'],
              ] as $key => $image): ?>
                <div class="field"><label for="<?= h($key) ?>"><?= h($image['label']) ?> — path/URL</label><input id="<?= h($key) ?>" type="text" name="<?= h($key) ?>" value="<?= h(($settings[$key] ?? '') !== '' ? $settings[$key] : $image['default']) ?>"><span class="help"><?php if ($key === 'logo_image'): ?>Biarkan kosong untuk memakai logo bawaan. PNG transparan direkomendasikan.<?php elseif (str_starts_with($key, 'hero_image')): ?>Path lokal atau URL HTTPS; unggah untuk mengganti gambar slide ini.<?php else: ?>Path lokal atau URL HTTPS; gambar yang diunggah disimpan di uploads/.<?php endif; ?></span></div>
                <div class="field"><label for="<?= h($key) ?>-file">Unggah gambar</label><input id="<?= h($key) ?>-file" type="file" name="<?= h($key) ?>_file" accept="image/jpeg,image/png,image/webp"></div>
              <?php endforeach; ?>
              <div class="field"><label for="about-title">Judul bagian tentang</label><input id="about-title" type="text" name="about_title" value="<?= h($settings['about_title'] ?? '') ?>"></div>
              <div class="field"><label for="about-highlight">Sorotan judul tentang</label><input id="about-highlight" type="text" name="about_highlight" value="<?= h($settings['about_highlight'] ?? '') ?>"></div>
              <div class="field full"><label for="about-lead">Ringkasan bagian tentang</label><textarea id="about-lead" name="about_lead" rows="2"><?= h($settings['about_lead'] ?? '') ?></textarea></div>
              <div class="field full"><label for="about-description">Deskripsi bagian tentang</label><textarea id="about-description" name="about_description" rows="3"><?= h($settings['about_description'] ?? '') ?></textarea></div>
              <div class="field"><label for="majors-title">Judul bagian jurusan</label><input id="majors-title" type="text" name="majors_title" value="<?= h($settings['majors_title'] ?? '') ?>"></div>
              <div class="field"><label for="majors-highlight">Sorotan judul jurusan</label><input id="majors-highlight" type="text" name="majors_highlight" value="<?= h($settings['majors_highlight'] ?? '') ?>"></div>
              <div class="field full"><label for="majors-description">Deskripsi bagian jurusan</label><textarea id="majors-description" name="majors_description" rows="2"><?= h($settings['majors_description'] ?? '') ?></textarea></div>
              <div class="field"><label for="dkv-title">Nama jurusan DKV</label><input id="dkv-title" type="text" name="dkv_title" value="<?= h($settings['dkv_title'] ?? '') ?>"></div>
              <div class="field"><label for="tkr-title">Nama jurusan TKR</label><input id="tkr-title" type="text" name="tkr_title" value="<?= h($settings['tkr_title'] ?? '') ?>"></div>
              <div class="field"><label for="dkv-description">Deskripsi DKV</label><textarea id="dkv-description" name="dkv_description" rows="3"><?= h($settings['dkv_description'] ?? '') ?></textarea></div>
              <div class="field"><label for="tkr-description">Deskripsi TKR</label><textarea id="tkr-description" name="tkr_description" rows="3"><?= h($settings['tkr_description'] ?? '') ?></textarea></div>
              <div class="field full"><label>Konten halaman PPDB</label><div class="form-grid">
                <div class="field"><label for="ppdb-page-title">Judul halaman</label><input id="ppdb-page-title" type="text" name="ppdb_page_title" maxlength="120" value="<?= h($settings['ppdb_page_title'] ?? 'Mulai langkahmu bersama') ?>"></div>
                <div class="field"><label for="ppdb-page-highlight">Teks sorotan</label><input id="ppdb-page-highlight" type="text" name="ppdb_page_highlight" maxlength="100" value="<?= h($settings['ppdb_page_highlight'] ?? 'JUARA.') ?>"></div>
                <div class="field full"><label for="ppdb-page-intro">Pengantar PPDB</label><textarea id="ppdb-page-intro" name="ppdb_page_intro" maxlength="500" rows="3"><?= h($settings['ppdb_page_intro'] ?? 'Kenali program keahlian dan sampaikan minat awal untuk bergabung di SMK Terpadu Ponpes Asy-Syafi\'iyah Lohgung.') ?></textarea></div>
                <div class="field full"><label for="ppdb-page-notice">Catatan / informasi resmi PPDB</label><textarea id="ppdb-page-notice" name="ppdb_page_notice" maxlength="1000" rows="4"><?= h($settings['ppdb_page_notice'] ?? 'Formulir ini menyampaikan minat awal, bukan pendaftaran final atau jaminan penerimaan. Jadwal, biaya, persyaratan, dan daya tampung belum dicantumkan karena perlu dikonfirmasi oleh sekolah.') ?></textarea><span class="help">Gunakan hanya informasi yang telah dikonfirmasi sekolah—misalnya jadwal, biaya, persyaratan, atau kuota.</span></div>
              </div></div>
              <div class="field full"><label>Ringkasan di bawah banner hero</label><div class="form-grid">
                <div class="field"><label for="fact-dkv-code">Kode jurusan pertama</label><input id="fact-dkv-code" type="text" name="fact_dkv_code" maxlength="24" value="<?= h($settings['fact_dkv_code'] ?? 'DKV') ?>"></div>
                <div class="field"><label for="fact-dkv-title">Label jurusan pertama</label><input id="fact-dkv-title" type="text" name="fact_dkv_title" maxlength="80" value="<?= h($settings['fact_dkv_title'] ?? 'Program Keahlian') ?>"></div>
                <div class="field full"><label for="fact-dkv-detail">Nama lengkap jurusan pertama</label><input id="fact-dkv-detail" type="text" name="fact_dkv_detail" maxlength="120" value="<?= h($settings['fact_dkv_detail'] ?? 'Desain Komunikasi Visual') ?>"></div>
                <div class="field"><label for="fact-tkr-code">Kode jurusan kedua</label><input id="fact-tkr-code" type="text" name="fact_tkr_code" maxlength="24" value="<?= h($settings['fact_tkr_code'] ?? 'TKR') ?>"></div>
                <div class="field"><label for="fact-tkr-title">Label jurusan kedua</label><input id="fact-tkr-title" type="text" name="fact_tkr_title" maxlength="80" value="<?= h($settings['fact_tkr_title'] ?? 'Program Keahlian') ?>"></div>
                <div class="field full"><label for="fact-tkr-detail">Nama lengkap jurusan kedua</label><input id="fact-tkr-detail" type="text" name="fact_tkr_detail" maxlength="120" value="<?= h($settings['fact_tkr_detail'] ?? 'Teknik Kendaraan Ringan') ?>"></div>
                <div class="field"><label for="fact-student-count">Jumlah siswa</label><input id="fact-student-count" type="text" name="fact_student_count" maxlength="24" value="<?= h($settings['fact_student_count'] ?? '—') ?>"><span class="help">Masukkan angka yang sudah diverifikasi sekolah.</span></div>
                <div class="field"><label for="fact-student-title">Label jumlah siswa</label><input id="fact-student-title" type="text" name="fact_student_title" maxlength="80" value="<?= h($settings['fact_student_title'] ?? 'Jumlah Siswa') ?>"></div>
                <div class="field full"><label for="fact-student-detail">Keterangan jumlah siswa</label><input id="fact-student-detail" type="text" name="fact_student_detail" maxlength="120" value="<?= h($settings['fact_student_detail'] ?? 'Data resmi belum diisi') ?>"></div>
                <div class="field"><label for="fact-teacher-count">Jumlah pengajar</label><input id="fact-teacher-count" type="text" name="fact_teacher_count" maxlength="24" value="<?= h($settings['fact_teacher_count'] ?? '—') ?>"><span class="help">Masukkan angka yang sudah diverifikasi sekolah.</span></div>
                <div class="field"><label for="fact-teacher-title">Label jumlah pengajar</label><input id="fact-teacher-title" type="text" name="fact_teacher_title" maxlength="80" value="<?= h($settings['fact_teacher_title'] ?? 'Jumlah Pengajar') ?>"></div>
                <div class="field full"><label for="fact-teacher-detail">Keterangan jumlah pengajar</label><input id="fact-teacher-detail" type="text" name="fact_teacher_detail" maxlength="120" value="<?= h($settings['fact_teacher_detail'] ?? 'Data resmi belum diisi') ?>"></div>
              </div><span class="help">Nilai dan keterangan dapat diubah kapan saja. Jumlah siswa/pengajar tidak diisi dengan angka perkiraan.</span></div>
              <div class="field"><label for="instagram-url">Tautan Instagram publik</label><input id="instagram-url" type="url" name="instagram_url" value="<?= h($settings['instagram_url'] ?? '') ?>"></div>
              <div class="field"><label for="facebook-url">Tautan Facebook publik</label><input id="facebook-url" type="url" name="facebook_url" value="<?= h($settings['facebook_url'] ?? '') ?>"></div>
              <div class="field full"><label for="youtube-url">Tautan kanal YouTube publik</label><input id="youtube-url" type="url" name="youtube_url" value="<?= h($settings['youtube_url'] ?? '') ?>"></div>
              <div class="field full"><label>Warna tema</label><div class="form-grid">
                <?php foreach (['theme_primary' => 'Warna utama', 'theme_dark' => 'Warna gelap', 'theme_soft' => 'Warna lembut', 'theme_accent' => 'Warna aksen'] as $key => $label): ?><div class="field"><label for="<?= h($key) ?>"><?= h($label) ?></label><input id="<?= h($key) ?>" type="color" name="<?= h($key) ?>" value="<?= h($settings[$key] ?? '#0A3F2F') ?>"></div><?php endforeach; ?>
              </div></div>
              <div class="field full"><label>Bagian yang ditampilkan di beranda</label><div class="checks">
                <?php foreach (['majors' => 'Jurusan', 'about' => 'Tentang sekolah', 'teachers' => 'Guru', 'news' => 'Berita', 'media' => 'Galeri & media', 'impact' => 'Dampak/prestasi', 'ppdb' => 'PPDB'] as $key => $label): ?><label class="check"><input type="checkbox" name="sections[<?= h($key) ?>]" value="1" <?= !array_key_exists($key, $visibility) || !empty($visibility[$key]) ? 'checked' : '' ?>> <?= h($label) ?></label><?php endforeach; ?>
              </div><span class="help">Hero dan identitas dasar tetap tampil agar informasi utama sekolah mudah ditemukan.</span></div>
            </div>
            <div class="form-actions"><button class="button" type="submit">Simpan pengaturan</button></div>
          </form>
        </section>
        <section class="card"><h2>Integrasi API (disimpan privat)</h2><p class="muted">Token dan kunci API hanya dipakai proses sinkronisasi di server; kolom sengaja kosong dan rahasia tidak pernah dikirim ke JavaScript publik.</p>
          <form method="post">
            <?= admin_csrf_field() ?><input type="hidden" name="action" value="save_settings"><input type="hidden" name="tab" value="settings">
            <?php
              // The credentials are intentionally handled in a dedicated form. Public fields use the values above;
              // these hidden fields preserve their current values when API credentials are saved.
              foreach (['site_title','meta_description','site_url','brand_short','brand_tagline','school_name','school_address','contact_address','contact_email','location_text','hero_kicker','hero_title','hero_subtitle','hero_highlight','hero_description','about_title','about_highlight','about_lead','about_description','majors_title','majors_highlight','majors_description','dkv_title','dkv_description','tkr_title','tkr_description','ppdb_page_title','ppdb_page_highlight','ppdb_page_intro','ppdb_page_notice','fact_dkv_code','fact_dkv_title','fact_dkv_detail','fact_tkr_code','fact_tkr_title','fact_tkr_detail','fact_student_count','fact_student_title','fact_student_detail','fact_teacher_count','fact_teacher_title','fact_teacher_detail','instagram_url','facebook_url','youtube_url','theme_primary','theme_dark','theme_soft','theme_accent','logo_image','hero_image','hero_image_2','hero_image_3','hero_image_4','about_image','dkv_image','tkr_image'] as $keepKey):
                $keepValue = (string) ($settings[$keepKey] ?? '');
                echo '<input type="hidden" name="' . h($keepKey) . '" value="' . h($keepValue) . '">';
              endforeach;
              foreach (['majors','about','teachers','news','media','impact','ppdb'] as $sectionKey) {
                if (!empty($visibility[$sectionKey])) {
                    echo '<input type="hidden" name="sections[' . h($sectionKey) . ']" value="1">';
                }
              }
            ?>
            <div class="secret-row"><span class="secret-status <?= $secretConfigured['instagram_user_id'] && $secretConfigured['instagram_access_token'] ? 'on' : '' ?>">Instagram <?= $secretConfigured['instagram_user_id'] && $secretConfigured['instagram_access_token'] ? 'terkonfigurasi' : 'belum lengkap' ?></span><div class="form-grid">
              <div class="field"><label for="instagram-id">Instagram Graph API User ID</label><input id="instagram-id" type="text" name="secret_instagram_user_id" autocomplete="off" inputmode="numeric" placeholder="Kosong berarti tidak mengubah data tersimpan"><span class="help">ID akun profesional yang diberikan melalui Meta Graph API.</span></div>
              <div class="field"><label for="instagram-token">Instagram Graph API access token</label><input id="instagram-token" type="password" name="secret_instagram_access_token" autocomplete="new-password" placeholder="Kosong berarti tidak mengubah token tersimpan"><span class="help">Token jangka panjang disimpan di database privat. Jangan tempel token di chat atau kode publik.</span></div>
              <div class="field"><label for="instagram-version">Versi Graph API</label><input id="instagram-version" type="text" name="secret_instagram_graph_version" value="<?= h($settings['instagram_graph_version'] ?? 'v24.0') ?>"><span class="help">Pastikan versi ini masih didukung Meta ketika akan dipakai.</span></div>
              <div class="field"><label class="check"><input type="checkbox" name="clear_secrets[]" value="instagram_access_token"> Hapus token tersimpan</label></div>
            </div></div>
            <div class="secret-row"><span class="secret-status <?= $secretConfigured['youtube_api_key'] && $secretConfigured['youtube_channel_id'] ? 'on' : '' ?>">YouTube <?= $secretConfigured['youtube_api_key'] && $secretConfigured['youtube_channel_id'] ? 'terkonfigurasi' : 'belum lengkap' ?></span><div class="form-grid">
              <div class="field"><label for="youtube-id">YouTube Channel ID</label><input id="youtube-id" type="text" name="secret_youtube_channel_id" autocomplete="off" placeholder="UC…"><span class="help">ID kanal YouTube (bukan nama handle atau URL).</span></div>
              <div class="field"><label for="youtube-key">YouTube Data API key</label><input id="youtube-key" type="password" name="secret_youtube_api_key" autocomplete="new-password" placeholder="Kosong berarti tidak mengubah kunci tersimpan"></div>
              <div class="field"><label class="check"><input type="checkbox" name="clear_secrets[]" value="youtube_api_key"> Hapus API key tersimpan</label></div>
            </div></div>
            <p class="fine-print">Instagram memerlukan akun profesional, izin Graph API, serta token yang belum kedaluwarsa. YouTube memakai Data API v3 dan kuota proyek API. Sinkronisasi dijalankan oleh cron di server—bukan dari browser pengunjung.</p>
            <div class="form-actions"><button class="button" type="submit">Simpan pengaturan API</button></div>
          </form>
          <div class="setup-note" style="margin-top:15px"><strong>Jadwal cron cPanel:</strong> jalankan <code>php /home/USERNAME/public_html/cron/sync-social.php</code> setiap 30–60 menit (sesuaikan lokasi PHP dan document root pada akun hosting). File cron diblokir dari akses web.</div>
        </section>
        <section class="card"><h2>Ganti kata sandi admin</h2><form method="post"><input type="hidden" name="action" value="change_password"><input type="hidden" name="tab" value="settings"><?= admin_csrf_field() ?><div class="form-grid"><div class="field"><label for="old-password">Kata sandi saat ini</label><input id="old-password" type="password" name="old_password" required autocomplete="current-password"></div><div class="field"><label for="new-password">Kata sandi baru (minimal 12 karakter)</label><input id="new-password" type="password" name="new_password" minlength="12" required autocomplete="new-password"></div><div class="field"><label for="new-password-confirmation">Ulangi kata sandi baru</label><input id="new-password-confirmation" type="password" name="new_password_confirmation" minlength="12" required autocomplete="new-password"></div></div><div class="form-actions"><button class="button secondary" type="submit">Ganti kata sandi</button></div></form></section>
        <section class="card"><h2>RSS, sitemap, dan tautan publik</h2><p class="fine-print">RSS dinamis tersedia di <a href="../feed.xml" target="_blank" rel="noopener noreferrer">feed.xml</a>. Sitemap tersedia di <a href="../sitemap.xml" target="_blank" rel="noopener noreferrer">sitemap.xml</a>. Daftar artikel terbit diperbarui otomatis oleh backend. Periksa domain dan konten contoh sebelum situs diumumkan.</p></section>
      <?php endif; ?>
    </main>
  </div>
</body>
</html>
 </div>
</body>
</html>
d" name="old_password" required autocomplete="current-password"></div><div class="field"><label for="new-password">Kata sandi baru (minimal 12 karakter)</label><input id="new-password" type="password" name="new_password" minlength="12" required autocomplete="new-password"></div><div class="field"><label for="new-password-confirmation">Ulangi kata sandi baru</label><input id="new-password-confirmation" type="password" name="new_password_confirmation" minlength="12" required autocomplete="new-password"></div></div><div class="form-actions"><button class="button secondary" type="submit">Ganti kata sandi</button></div></form></section>
        <section class="card"><h2>RSS, sitemap, dan tautan publik</h2><p class="fine-print">RSS dinamis tersedia di <a href="../feed.xml" target="_blank" rel="noopener noreferrer">feed.xml</a>. Sitemap tersedia di <a href="../sitemap.xml" target="_blank" rel="noopener noreferrer">sitemap.xml</a>. Daftar artikel terbit diperbarui otomatis oleh backend. Periksa domain dan konten contoh sebelum situs diumumkan.</p></section>
      <?php endif; ?>
    </main>
  </div>
</body>
</html>
