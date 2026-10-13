-- Import this file once from cPanel phpMyAdmin after creating an empty MySQL database.
-- The seeded teacher and gallery cards use role labels and illustrative site images,
-- not confirmed staff identities or official documentary photographs.

CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(100) NOT NULL,
  setting_value MEDIUMTEXT NOT NULL,
  is_public TINYINT(1) NOT NULL DEFAULT 1,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admins (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(80) NOT NULL,
  display_name VARCHAR(120) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admins_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS teachers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(160) NOT NULL,
  `role` VARCHAR(160) NOT NULL DEFAULT 'TENAGA PENDIDIK',
  department VARCHAR(80) NOT NULL DEFAULT 'GURU',
  specialty VARCHAR(220) NOT NULL DEFAULT '',
  bio VARCHAR(500) NOT NULL DEFAULT '',
  photo VARCHAR(512) NOT NULL DEFAULT '',
  sort_order INT NOT NULL DEFAULT 0,
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_teachers_public_order (is_published, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS posts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(190) NOT NULL,
  title VARCHAR(220) NOT NULL,
  category VARCHAR(100) NOT NULL DEFAULT 'Informasi Sekolah',
  excerpt VARCHAR(420) NOT NULL DEFAULT '',
  content MEDIUMTEXT NOT NULL,
  cover_image VARCHAR(512) NOT NULL DEFAULT '',
  status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
  published_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_posts_slug (slug),
  KEY idx_posts_published (status, published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gallery (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  image VARCHAR(512) NOT NULL,
  caption VARCHAR(240) NOT NULL DEFAULT '',
  alt_text VARCHAR(240) NOT NULL DEFAULT '',
  permalink VARCHAR(512) NOT NULL DEFAULT '',
  source ENUM('manual', 'instagram') NOT NULL DEFAULT 'manual',
  source_id VARCHAR(190) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_gallery_source_id (source_id),
  KEY idx_gallery_public_order (is_published, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS videos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  youtube_id VARCHAR(32) NOT NULL,
  title VARCHAR(240) NOT NULL,
  description VARCHAR(500) NOT NULL DEFAULT '',
  thumbnail VARCHAR(512) NOT NULL DEFAULT '',
  published_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_videos_youtube_id (youtube_id),
  KEY idx_videos_published (published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO settings (setting_key, setting_value, is_public) VALUES
('site_url', 'https://smkasysyafiiyah.sch.id', 1),
('site_title', 'SMK DKV & TKR Lamongan | SMK Terpadu Ponpes Asy-Syafi''iyah', 1),
('meta_description', 'SMK Terpadu Ponpes Asy-Syafi''iyah Lohgung, Lamongan. Kenali kompetensi keahlian Desain Komunikasi Visual (DKV), Teknik Kendaraan Ringan (TKR), dan nilai JUARA.', 1),
('logo_image', '', 1),
('brand_short', 'ASY-SYAFI''IYAH', 1),
('brand_tagline', 'SMK TERPADU PONPES · LOHGUNG', 1),
('school_name', 'SMK Terpadu Ponpes Asy-Syafi''iyah', 1),
('school_address', 'Jl. Masjid At-Taqwa, Desa Lohgung, Brondong, Lamongan', 1),
('contact_address', 'Jl. Masjid At-Taqwa, Desa Lohgung,\nKec. Brondong, Kab. Lamongan,\nJawa Timur 62263', 1),
('location_text', 'Lohgung · Brondong · Lamongan, Jawa Timur', 1),
('contact_email', 'smk.lohgung@gmail.com', 1),
('hero_kicker', 'SMK TERPADU PONPES ASY-SYAFI''IYAH · LOHGUNG', 1),
('hero_title', 'Terampil berkarya,', 1),
('hero_subtitle', 'berkarakter', 1),
('hero_highlight', 'JUARA.', 1),
('hero_description', 'Kembangkan kompetensi kejuruan dan karakter baik dalam lingkungan belajar yang memadukan keterampilan, nilai, dan kehidupan pesantren.', 1),
('hero_image', 'public/images/kampus-hero.jpg', 1),
('hero_image_2', 'public/images/kehidupan-sekolah.jpg', 1),
('hero_image_3', 'public/images/dkv-studio.jpg', 1),
('hero_image_4', 'public/images/tkr-workshop.jpg', 1),
('about_image', 'public/images/kehidupan-sekolah.jpg', 1),
('about_title', 'Pendidikan kejuruan dalam', 1),
('about_highlight', 'naungan nilai pesantren.', 1),
('about_lead', 'SMK Terpadu Ponpes Asy-Syafi''iyah mengembangkan keterampilan sekaligus membiasakan nilai baik dalam keseharian.', 1),
('about_description', 'Berlokasi di Desa Lohgung, Kecamatan Brondong, Kabupaten Lamongan, sekolah mempersiapkan peserta didik melalui dua kompetensi keahlian dan pembinaan karakter yang berlandaskan nilai pesantren.', 1),
('majors_title', 'Pilih bidang.', 1),
('majors_highlight', 'Bangun masa depan.', 1),
('majors_description', 'Kenali dua program keahlian yang menggabungkan pengetahuan, praktik, kreativitas, dan kesiapan kerja.', 1),
('dkv_title', 'Desain Komunikasi Visual', 1),
('dkv_description', 'Belajar mengolah ide menjadi pesan visual melalui desain grafis, fotografi, videografi, dan produksi konten.', 1),
('dkv_image', 'public/images/dkv-studio.jpg', 1),
('tkr_title', 'Teknik Kendaraan Ringan', 1),
('tkr_description', 'Mengenal sistem kendaraan dan proses perawatan melalui pembelajaran teknik, ketelitian, dan praktik.', 1),
('tkr_image', 'public/images/tkr-workshop.jpg', 1),
('fact_dkv_code', 'DKV', 1),
('fact_dkv_title', 'Program Keahlian', 1),
('fact_dkv_detail', 'Desain Komunikasi Visual', 1),
('fact_tkr_code', 'TKR', 1),
('fact_tkr_title', 'Program Keahlian', 1),
('fact_tkr_detail', 'Teknik Kendaraan Ringan', 1),
('fact_student_count', '—', 1),
('fact_student_title', 'Jumlah Siswa', 1),
('fact_student_detail', 'Data resmi belum diisi', 1),
('fact_teacher_count', '—', 1),
('fact_teacher_title', 'Jumlah Pengajar', 1),
('fact_teacher_detail', 'Data resmi belum diisi', 1),
('theme_primary', '#0e4a3b', 1),
('theme_dark', '#083c30', 1),
('theme_soft', '#e8f2ee', 1),
('theme_accent', '#f2b705', 1),
('instagram_url', 'https://www.instagram.com/skapsya/', 1),
('facebook_url', 'https://www.facebook.com/smklohgung', 1),
('youtube_url', 'https://www.youtube.com/results?search_query=smkasy-syafiiyahlohgung8619', 1),
('section_visibility', '{"majors":true,"about":true,"teachers":true,"news":true,"media":true,"impact":true,"ppdb":true}', 1),
('instagram_user_id', '', 0),
('instagram_access_token', '', 0),
('instagram_graph_version', 'v24.0', 0),
('youtube_api_key', '', 0),
('youtube_channel_id', '', 0);

INSERT INTO teachers (name, `role`, department, specialty, bio, photo, sort_order, is_published) VALUES
('Pengajar DKV', 'PENDIDIK KEJURUAN', 'DKV', 'Desain visual · Fotografi · Produksi media', '', 'public/images/lab-kolaborasi.jpg', 1, 1),
('Pengajar TKR', 'PENDIDIK KEJURUAN', 'TKR', 'Otomotif · Perawatan kendaraan · Praktik bengkel', '', 'public/images/tkr-workshop.jpg', 2, 1),
('Pembina Pesantren', 'PENDIDIKAN KARAKTER', 'JUARA', 'Adab · Kedisiplinan · Pembinaan akhlak', '', 'public/images/perpustakaan.jpg', 3, 1);

INSERT IGNORE INTO posts (slug, title, category, excerpt, content, cover_image, status, published_at) VALUES
('mengenal-kompetensi-dkv', 'Mengenal kompetensi Desain Komunikasi Visual', 'Informasi Jurusan', 'Informasi umum tentang desain visual, fotografi, dan produksi media dalam kompetensi DKV.', 'Dalam DKV, peserta didik mengolah gagasan menjadi pesan visual. Proses belajar dapat mencakup prinsip desain, tata letak, warna, tipografi, fotografi, videografi, dan produksi media. Pilihan perangkat serta ruang praktik mengikuti kurikulum dan fasilitas sekolah.', 'public/images/dkv-studio.jpg', 'published', '2026-10-13 00:00:00'),
('mengenal-kompetensi-tkr', 'Mengenal kompetensi Teknik Kendaraan Ringan', 'Informasi Jurusan', 'Informasi umum tentang pembelajaran teknik dan perawatan kendaraan ringan.', 'Bidang Teknik Kendaraan Ringan mempelajari sistem kendaraan dan tahapan perawatan dasar. Materi dapat mencakup mesin, kelistrikan, prosedur pemeriksaan, dan keselamatan kerja. Rincian praktik mengikuti kurikulum serta sarana sekolah.', 'public/images/tkr-workshop.jpg', 'published', '2026-10-13 00:00:00'),
('nilai-juara-dalam-keseharian-belajar', 'Nilai JUARA dalam keseharian belajar', 'Pendidikan Karakter', 'Mengenal Jujur, Unggul, Adaptif, Religius, dan Amanah sebagai nilai sekolah.', 'JUARA merangkum nilai Jujur, Unggul, Adaptif, Religius, dan Amanah. Nilai-nilai tersebut menjadi kerangka untuk mengembangkan kebiasaan belajar, tanggung jawab, dan adab. Redaksi serta penerapan program dapat disesuaikan dengan panduan resmi sekolah.', 'public/images/hero-siswa.jpg', 'published', '2026-10-13 00:00:00');

INSERT INTO gallery (image, caption, alt_text, permalink, source, sort_order, is_published) VALUES
('public/images/dkv-studio.jpg', 'Contoh visual kegiatan DKV', 'Visual contoh kegiatan DKV', '', 'manual', 1, 1),
('public/images/tkr-workshop.jpg', 'Contoh visual praktik TKR', 'Visual contoh praktik TKR', '', 'manual', 2, 1),
('public/images/kehidupan-sekolah.jpg', 'Contoh visual kebersamaan siswa', 'Visual contoh kebersamaan siswa', '', 'manual', 3, 1),
('public/images/perpustakaan.jpg', 'Contoh visual kegiatan literasi', 'Visual contoh kegiatan literasi', '', 'manual', 4, 1);
