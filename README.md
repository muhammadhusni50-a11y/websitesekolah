# Website SMK Terpadu Ponpes Asy-Syafi'iyah Lohgung

Website sekolah berbahasa Indonesia dengan halaman beranda responsif, halaman PPDB terpisah berisi formulir minat calon siswa, berita/informasi, galeri foto, RSS, sitemap, dan paket CMS PHP/MySQL untuk dipasang pada hosting cPanel.

## Preview statis

```bash
python3 -m http.server 8000
```

Buka `http://localhost:8000` untuk beranda atau `/ppdb.html` untuk halaman penerimaan peserta didik baru. Preview statis menampilkan formulir, tetapi server Python tidak mengeksekusi PHP, jadi pengiriman dan penyimpanan hanya berfungsi setelah dipasang pada hosting PHP/MySQL.

## Fitur CMS

- Masuk admin melalui `/admin/login.php`; buat akun pertama satu kali melalui `/admin/setup.php` setelah database siap.
- Ubah logo, teks beranda, identitas dan kontak, tautan media sosial, warna tema, gambar hero (empat slide berganti otomatis tiap 5 detik dan dapat dipindahkan dengan tombol panah kiri/kanan), gambar jurusan/tentang, jumlah siswa/pengajar, serta visibilitas beberapa bagian.
- Tambah, ubah, terbitkan, simpan sebagai draf, atau hapus berita. Berita terbit otomatis tampil di beranda, halaman artikelnya, RSS, dan sitemap.
- Kelola profil guru dan foto galeri; unggahan gambar JPG/PNG/WebP divalidasi dan disimpan di `uploads/`.
- Sinkronkan media Instagram Graph API dan video terbaru YouTube Data API v3 di sisi server. Rahasia API disimpan pada entri privat database, tidak termasuk keluaran `api/home.php` dan tidak dikirim ke JavaScript.
- Sinkronisasi media sosial dijalankan melalui cron cPanel, bukan dari browser pengunjung.
- Kelola judul, pengantar, catatan resmi PPDB, dan kontak halaman PPDB melalui CMS. Pendaftar minat tersimpan di database dan dapat ditinjau, diperbarui statusnya, atau dihapus admin melalui tab **Pendaftar PPDB**. Data pribadi tidak ditampilkan kepada pengunjung publik.

Pengaturan panel dapat menyesuaikan konten dan beberapa aspek tampilan yang disediakan (warna, gambar, dan bagian yang ditampilkan). Perubahan tidak mengubah tata letak/komponen secara bebas seperti page builder.

## Instalasi pada cPanel

1. Pastikan hosting menyediakan PHP 8.1+ dengan PDO MySQL, MySQL/MariaDB, Apache yang mengizinkan `.htaccess`, ekstensi `fileinfo`, dan HTTPS. Untuk sinkronisasi sosial, aktifkan cURL; jika tidak tersedia, server memerlukan `allow_url_fopen` dan koneksi HTTPS keluar.
2. Buat database dan pengguna database di cPanel, lalu beri izin akses database kepada pengguna tersebut.
3. Impor `database/schema.sql` satu kali melalui phpMyAdmin. Database yang sudah memakai skema lama harus menjalankan `database/migrations/20261014_create_ppdb_applicants.sql` satu kali untuk menambahkan tabel pendaftar. Data awal guru dan foto galeri memakai label peran/visual contoh; tiga artikel awal adalah informasi umum, bukan laporan acara faktual. Ringkasan di bawah hero menampilkan DKV/TKR serta jumlah siswa/pengajar; isi angka setelah mendapat data resmi (nilai awal berupa tanda —).
4. Salin `app/config.example.php` menjadi `app/config.php`. Isi nama database, pengguna, kata sandi, dan domain final. Batasi izin baca berkas konfigurasi bila hosting mendukung. Folder `app/`, `database/`, dan `cron/` diblokir dari akses web oleh `.htaccess`.
5. Unggah isi repo ke document root situs. Pastikan `.htaccess` digunakan dan `DirectoryIndex` memprioritaskan `index.php`; halaman dinamis memakai `index.html` sebagai templat agar preview lokal tetap sederhana. Pastikan PHP dapat menulis ke `uploads/` sesuai izin hosting (hindari izin `0777`).
6. Buka `/admin/setup.php`, buat kata sandi admin yang unik (minimal 12 karakter), lalu masuk melalui `/admin/login.php`. Setup hanya dapat membuat akun admin pertama.
7. Pada menu **Tampilan & integrasi**, perbarui domain resmi, logo, email, tautan sosial, identitas, warna, gambar, jumlah siswa/pengajar, serta judul, pengantar, dan catatan PPDB. Isi jadwal, biaya, persyaratan, atau kuota hanya setelah mendapat konfirmasi resmi. Ganti foto contoh serta label peran guru dengan profil resmi sebelum publikasi.
8. Siapkan kredensial resmi, lalu masukkan Instagram Graph API User ID/token dan YouTube API key/Channel ID pada panel. Token tidak disertakan di repository. Instagram memerlukan akun profesional dan izin Meta yang sesuai; token dapat kedaluwarsa. Pastikan versi Graph API pada panel masih didukung Meta.
9. Tambahkan cron pada **cPanel → Cron Jobs**, misalnya setiap 30 menit:

   ```cron
   */30 * * * * /usr/local/bin/php /home/USERNAME/public_html/cron/sync-social.php
   ```

   Sesuaikan lokasi PHP dan document root akun hosting. Jalankan sekali secara manual melalui Terminal cPanel untuk memeriksa hasilnya. Cron hanya menggunakan endpoint API dari server; jangan menaruh token di browser.

## Halaman dan penyimpanan PPDB

- Halaman calon siswa: `/ppdb.html`; formulir dikirim ke `api/ppdb.php` dan disimpan ke tabel `ppdb_applicants`.
- Admin dapat membaca data, memperbarui status (baru, sudah dihubungi, proses PPDB, diterima, atau tidak melanjutkan), dan menghapus data melalui **Admin → Pendaftar PPDB**.
- Halaman menyebut DKV/TKR yang sudah ada pada situs. Jadwal, biaya, persyaratan, kuota, dan keputusan penerimaan tidak diisi otomatis; pastikan pihak sekolah memberi informasi resmi.
- Formulir meminta persetujuan penggunaan data untuk tindak lanjut PPDB. Batasi akses admin, gunakan HTTPS, dan kelola data sesuai kebijakan privasi sekolah.

## Font Maiah Serif

CSS mengutamakan keluarga **Maiah Serif** dan menyiapkan jalur `public/fonts/maiah-serif-latin.woff2`. Berkas font belum disertakan karena sekolah perlu menyediakan webfont dengan lisensi yang mengizinkan pemakaian web. Sampai berkas berlisensi tersebut dipasang, browser menggunakan fallback serif; jangan menganggap tampilan sudah menggunakan Maiah Serif yang sebenarnya.

## RSS, sitemap, dan domain

- `feed.xml` dan `sitemap.xml` menyediakan fallback statis untuk preview/host statis.
- Pada Apache cPanel, `.htaccess` mengarahkan URL tersebut ke `feed.php` dan `sitemap.php`, sehingga artikel terbit dapat ikut terbarui otomatis.
- `robots.txt`, canonical, RSS, dan sitemap memakai domain `https://smkasysyafiiyah.sch.id` sebagai nilai awal. **Konfirmasi domain resmi sekolah sebelum rilis** dan perbarui di panel serta `robots.txt`/berkas fallback statis.

## Data yang harus diverifikasi sebelum publikasi

- Logo/identitas grafis dan warna resmi, domain, serta alamat/kontak publik.
- Nama, jabatan, gelar, bidang, dan foto resmi guru/pembina.
- Foto dokumentasi sekolah, fasilitas, kelas, karya, dan kegiatan.
- URL kanal/ID YouTube, kredensial Instagram Graph API, serta izin dan masa berlaku token.
- Redaksi resmi sekolah, data prestasi/agenda, dan informasi PPDB yang ingin diterbitkan.

Email, alamat, NPSN, dan identitas awal mengikuti draf konsep yang diberikan. Aset foto di `public/images/` merupakan visual contoh, bukan dokumentasi sekolah. Jangan menambahkan statistik, kontak, atau identitas dari sekolah lain.

## Batas pengujian lokal

PHP dan MySQL tidak terpasang pada lingkungan preview ini, sehingga alur CMS, formulir PPDB, database, unggah, serta koneksi API belum dapat dijalankan di sini. File PHP ditujukan untuk hosting cPanel dan perlu diuji setelah kredensial hosting/domain/API resmi disediakan. JavaScript dapat diperiksa dengan `node --check script.js` dan `node --check ppdb.js`.
