# Website SMK Terpadu Ponpes Asy-Syafi'iyah Lohgung

Website sekolah berbahasa Indonesia dengan halaman beranda responsif, galeri tenaga pendidik, berita/informasi, galeri foto, RSS, sitemap, dan paket CMS PHP/MySQL untuk dipasang pada hosting cPanel.

## Preview statis

```bash
python3 -m http.server 8000
```

Buka `http://localhost:8000`. Preview statis menyajikan `index.html`; server Python tidak mengeksekusi PHP. Data contoh tetap tampil bila API PHP tidak tersedia.

## Fitur CMS

- Masuk admin melalui `/admin/login.php`; buat akun pertama satu kali melalui `/admin/setup.php` setelah database siap.
- Ubah logo, teks beranda, identitas dan kontak, tautan media sosial, warna tema, gambar hero (empat slide dengan tombol panah kiri/kanan), gambar jurusan/tentang, jumlah siswa/pengajar, serta visibilitas beberapa bagian.
- Tambah, ubah, terbitkan, simpan sebagai draf, atau hapus berita. Berita terbit otomatis tampil di beranda, halaman artikelnya, RSS, dan sitemap.
- Kelola profil guru dan foto galeri; unggahan gambar JPG/PNG/WebP divalidasi dan disimpan di `uploads/`.
- Sinkronkan media Instagram Graph API dan video terbaru YouTube Data API v3 di sisi server. Rahasia API disimpan pada entri privat database, tidak termasuk keluaran `api/home.php` dan tidak dikirim ke JavaScript.
- Sinkronisasi media sosial dijalankan melalui cron cPanel, bukan dari browser pengunjung.

Pengaturan panel dapat menyesuaikan konten dan beberapa aspek tampilan yang disediakan (warna, gambar, dan bagian yang ditampilkan). Perubahan tidak mengubah tata letak/komponen secara bebas seperti page builder.

## Instalasi pada cPanel

1. Pastikan hosting menyediakan PHP 8.1+ dengan PDO MySQL, MySQL/MariaDB, Apache yang mengizinkan `.htaccess`, ekstensi `fileinfo`, dan HTTPS. Untuk sinkronisasi sosial, aktifkan cURL; jika tidak tersedia, server memerlukan `allow_url_fopen` dan koneksi HTTPS keluar.
2. Buat database dan pengguna database di cPanel, lalu beri izin akses database kepada pengguna tersebut.
3. Impor `database/schema.sql` satu kali melalui phpMyAdmin. Data awal guru dan foto galeri memakai label peran/visual contoh; tiga artikel awal adalah informasi umum, bukan laporan acara faktual. Ringkasan di bawah hero menampilkan DKV/TKR serta jumlah siswa/pengajar; isi angka setelah mendapat data resmi (nilai awal berupa tanda —).
4. Salin `app/config.example.php` menjadi `app/config.php`. Isi nama database, pengguna, kata sandi, dan domain final. Batasi izin baca berkas konfigurasi bila hosting mendukung. Folder `app/`, `database/`, dan `cron/` diblokir dari akses web oleh `.htaccess`.
5. Unggah isi repo ke document root situs. Pastikan `.htaccess` digunakan dan `DirectoryIndex` memprioritaskan `index.php`; halaman dinamis memakai `index.html` sebagai templat agar preview lokal tetap sederhana. Pastikan PHP dapat menulis ke `uploads/` sesuai izin hosting (hindari izin `0777`).
6. Buka `/admin/setup.php`, buat kata sandi admin yang unik (minimal 12 karakter), lalu masuk melalui `/admin/login.php`. Setup hanya dapat membuat akun admin pertama.
7. Pada menu **Tampilan & integrasi**, perbarui domain resmi, logo, email, tautan sosial, identitas, warna, gambar, jumlah siswa/pengajar, dan konten yang perlu dikonfirmasi. Ganti foto contoh serta label peran guru dengan profil resmi sebelum publikasi.
8. Siapkan kredensial resmi, lalu masukkan Instagram Graph API User ID/token dan YouTube API key/Channel ID pada panel. Token tidak disertakan di repository. Instagram memerlukan akun profesional dan izin Meta yang sesuai; token dapat kedaluwarsa. Pastikan versi Graph API pada panel masih didukung Meta.
9. Tambahkan cron pada **cPanel → Cron Jobs**, misalnya setiap 30 menit:

   ```cron
   */30 * * * * /usr/local/bin/php /home/USERNAME/public_html/cron/sync-social.php
   ```

   Sesuaikan lokasi PHP dan document root akun hosting. Jalankan sekali secara manual melalui Terminal cPanel untuk memeriksa hasilnya. Cron hanya menggunakan endpoint API dari server; jangan menaruh token di browser.

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

PHP dan MySQL tidak terpasang pada lingkungan preview ini, sehingga alur CMS, database, unggah, serta koneksi API belum dapat dijalankan di sini. File PHP ditujukan untuk hosting cPanel dan perlu diuji setelah kredensial hosting/domain/API resmi disediakan. JavaScript dapat diperiksa dengan `node --check script.js`.
