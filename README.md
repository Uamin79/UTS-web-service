# Sistem Informasi Akademik dan Prestasi Siswa - TUGAS UTS - 24.01.53.7009 

Sistem manajemen sekolah terpadu yang memungkinkan admin, guru, dan orang tua untuk berinteraksi dan memantau perkembangan akademik serta non-akademik siswa secara digital.

## Fitur Utama

### Admin Dashboard
- CRUD untuk data master (siswa, guru, orang tua, kelas, mata pelajaran)
- Menetapkan wali kelas
- Mengelola relasi siswa dan orang tua

### Guru Dashboard
- Input absensi harian per mata pelajaran
- Input nilai (tugas, UTS, UAS)
- Menulis catatan rapor (wali kelas)
- Melihat balasan dari orang tua

### Orang Tua Dashboard
- Melihat data anak
- Melihat rekap absensi anak
- Melihat grafik perkembangan nilai
- Melihat rapor digital dan memberikan balasan

## Teknologi

- **Backend**: PHP Native (tanpa framework)
- **Database**: MySQL
- **Frontend**: HTML, CSS, Bootstrap 5, JavaScript
- **Server**: XAMPP (Apache + MySQL + PHP)

## Struktur Database

12 tabel utama:
- users (otentikasi)
- admins, teachers, parents (profil pengguna)
- students (data siswa)
- classes (data kelas)
- subjects (mata pelajaran)
- teacher_subjects (relasi guru-mata pelajaran-kelas)
- student_parent_relations (relasi siswa-orang tua)
- attendances (absensi)
- grades (nilai)
- report_cards (rapor)

## Instalasi dan Setup

### Persyaratan
- XAMPP (Apache, MySQL, PHP)
- Browser web modern

### Langkah Setup

1. **Download dan Install XAMPP**
   - Download XAMPP dari https://www.apachefriends.org/
   - Install XAMPP di komputer Anda

2. **Clone atau Download Project**
   - Letakkan folder project di `C:\xampp\htdocs\siap-siswa\`

3. **Setup Database**
   - Jalankan XAMPP Control Panel
   - Start Apache dan MySQL
   - Buka phpMyAdmin: http://localhost/phpmyadmin
   - Buat database baru: `db_siap_siswa`
   - Import file `db_siap_siswa.sql` ke database tersebut

4. **Konfigurasi Database**
   - Edit file `config.php` jika diperlukan (default: localhost, root, no password)

5. **Akses Aplikasi**
   - Buka browser dan akses: http://localhost/siap-siswa/

## Penggunaan

### Login Credentials (Sample)
- **Admin**: username: admin, password: admin123
- **Guru**: username: guru1, password: guru123
- **Orang Tua**: username: ortu1, password: ortu123

### Menambah Data Sample

1. Login sebagai admin
2. Tambahkan data master (kelas, mata pelajaran, guru, siswa, orang tua)
3. Hubungkan siswa dengan orang tua
4. Tetapkan guru sebagai wali kelas

## File Structure

```
siap-siswa/
├── index.php          # Halaman login
├── admin.php          # Dashboard admin
├── guru.php           # Dashboard guru
├── orangtua.php       # Dashboard orang tua
├── logout.php         # Logout handler
├── config.php         # Konfigurasi database
├── api.php            # API endpoint (php-crud-api)
├── db_siap_siswa.sql  # Schema database
├── backend/           # php-crud-api files
├── frontend/          # Vue.js project (opsional)
├── README.md          # Dokumentasi
└── TODO.md            # Task list
```

## API Endpoints

- GET/POST/PUT/DELETE /api.php/users
- GET/POST/PUT/DELETE /api.php/students
- GET/POST/PUT/DELETE /api.php/teachers
- GET/POST/PUT/DELETE /api.php/classes
- GET/POST/PUT/DELETE /api.php/subjects
- GET/POST/PUT/DELETE /api.php/attendances
- GET/POST/PUT/DELETE /api.php/grades
- GET/POST/PUT/DELETE /api.php/report_cards

## Troubleshooting

### Error Database Connection
- Pastikan MySQL service di XAMPP sudah running
- Periksa konfigurasi di `config.php`
- Pastikan database `db_siap_siswa` sudah dibuat dan di-import

### Error 404
- Pastikan file ditempatkan di folder `htdocs`
- Akses dengan URL yang benar: http://localhost/siap-siswa/

### Error Permission
- Pastikan XAMPP dijalankan sebagai Administrator
- Periksa permission folder htdocs

## Kontribusi

1. Fork repository
2. Buat branch fitur baru
3. Commit perubahan
4. Push ke branch
5. Buat Pull Request

## Lisensi

Proyek ini dibuat untuk tujuan TUGAS UTS - 24.01.53.7009
