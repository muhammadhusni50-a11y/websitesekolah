-- One-time migration for installations that already imported schema.sql before PPDB was added.
-- Safe to run after fresh installations too: CREATE TABLE IF NOT EXISTS leaves the table intact.
CREATE TABLE IF NOT EXISTS ppdb_applicants (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nama VARCHAR(120) NOT NULL,
  alamat TEXT NOT NULL,
  asal_sekolah VARCHAR(180) NOT NULL,
  email VARCHAR(180) NOT NULL,
  jurusan ENUM('DKV', 'TKR') NOT NULL,
  nomor_hp VARCHAR(32) NOT NULL,
  status ENUM('baru', 'dihubungi', 'proses', 'diterima', 'tidak_dilanjutkan') NOT NULL DEFAULT 'baru',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ppdb_status_created (status, created_at),
  KEY idx_ppdb_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO settings (setting_key, setting_value, is_public) VALUES
('ppdb_page_title', 'Mulai langkahmu bersama', 1),
('ppdb_page_highlight', 'JUARA.', 1),
('ppdb_page_intro', 'Kenali program keahlian dan sampaikan minat awal untuk bergabung di SMK Terpadu Ponpes Asy-Syafi''iyah Lohgung.', 1),
('ppdb_page_notice', 'Formulir ini menyampaikan minat awal, bukan pendaftaran final atau jaminan penerimaan. Jadwal, biaya, persyaratan, dan daya tampung belum dicantumkan karena perlu dikonfirmasi oleh sekolah.', 1);
