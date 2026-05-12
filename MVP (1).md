# MVP — Sistem Booking Konsultasi Mahasiswa & Dosen
## Ujikom Mandiri D3 Manajemen Informatika — TUK Manajemen Informatika

> **Skema:** Junior Web Programmer  
> **Asesi:** M. Hatta Siddiq — NIM 062330801650 — Kelas 6ID  
> **Stack:** Native PHP 8 + MySQL (InnoDB) + Vanilla CSS/JS  
> **Constraint:** Single RAR ≤ 10MB, tanpa framework eksternal

---

## 1. Latar Belakang & Problem Statement

Unit layanan akademik kampus menghadapi tiga permasalahan operasional:

| # | Masalah | Dampak |
|---|---------|--------|
| 1 | Proses konsultasi masih manual via chat pribadi | Tidak terdokumentasi, sulit dilacak |
| 2 | Tidak ada sistem pencatatan jadwal | Jadwal tersebar, tidak terpusat |
| 3 | Sering terjadi bentrok waktu antara mahasiswa dan dosen | Konsultasi gagal, waktu terbuang |

**Solusi:** Aplikasi web manajemen jadwal dan booking konsultasi berbasis multi-role dengan penyimpanan data persisten di MySQL.

---

## 2. Tujuan MVP

- Menyediakan sistem booking konsultasi terpusat dan terstruktur
- Mengeliminasi bentrok jadwal melalui mekanisme slot eksklusif
- Menghasilkan demo program yang dapat dipresentasikan kepada asesor
- Memenuhi seluruh unit kompetensi skema Junior Web Programmer

---

## 3. Unit Kompetensi yang Dipenuhi

### Kelompok Pekerjaan 1 — Merancang Aplikasi Perangkat Lunak

| Kode Unit | Judul Unit | Implementasi |
|-----------|-----------|--------------|
| J.620100.004.02 | Menggunakan struktur data | Array PHP, struktur tabel relasional MySQL |
| J.620100.005.02 | Mengimplementasikan user interface | Dashboard mahasiswa & dosen, form interaktif |

### Kelompok Pekerjaan 2 — Mengimplementasikan Perangkat Lunak

| Kode Unit | Judul Unit | Implementasi |
|-----------|-----------|--------------|
| J.620100.011.01 | Melakukan instalasi software tools pemrograman | XAMPP/Laragon setup |
| J.620100.016.01 | Menulis kode sesuai guidelines dan best practices | PSR standar, prepared statements, CSRF |
| J.620100.017.02 | Mengimplementasikan pemrograman terstruktur | Fungsi terpisah di `auth.php`, `functions.php` |
| J.620100.019.02 | Menggunakan library atau komponen pre-existing | PDO, DateInterval, password_hash |
| J.620100.023.02 | Membuat dokumen kode program | Komentar inline, file MVP.md ini |
| J.620100.025.02 | Melakukan debugging | error_log() kontekstual di semua action handler |

---

## 4. Arsitektur Sistem

### 4.1 Stack Teknologi

```
Backend  : PHP 8 (Native — tanpa framework)
Database : MySQL 8 (InnoDB engine)
Frontend : HTML5 + CSS3 (Flexbox/Grid) + Vanilla JavaScript
Auth     : PHP Session + bcrypt (password_hash / password_verify)
Server   : XAMPP / Laragon (localhost)
```

### 4.2 Struktur Folder

```
konsultasi_app/
├── index.php                      ← redirect ke login
├── login.php                      ← autentikasi multi-role
├── logout.php                     ← destroy session
│
├── includes/
│   ├── db.php                     ← koneksi PDO (InnoDB, utf8mb4)
│   └── auth.php                   ← session management & role guard
│
├── mahasiswa/
│   ├── dashboard.php              ← lihat slot + riwayat booking
│   └── action_booking.php         ← proses booking + CSRF + transaksi
│
├── dosen/
│   ├── dashboard.php              ← generate jadwal + kelola booking
│   ├── action_generate_jadwal.php ← batch insert slot (DateInterval)
│   └── action_konfirmasi.php      ← approve/reject + validasi kepemilikan
│
└── database/
    └── konsultasi_db.sql          ← skema + seed data
```

### 4.3 Entity Relationship Diagram

```
┌─────────────────────────────────────────────────────┐
│                       users                         │
│  id | nama | identifier | password | role | is_active│
└──────────────────┬──────────────────────────────────┘
                   │ dosen_id (FK)
                   ▼
┌─────────────────────────────────────────────────────┐
│                       jadwal                        │
│  id | dosen_id | tanggal | jam_mulai | jam_selesai  │
│  status                                             │
│  UNIQUE(dosen_id, tanggal, jam_mulai)               │
└──────────────────┬──────────────────────────────────┘
                   │ jadwal_id (FK)
                   ▼
┌─────────────────────────────────────────────────────┐
│                      booking                        │
│  id | mahasiswa_id | jadwal_id | topik | catatan    │
│  status | approved_at | created_at                  │
│  UNIQUE(jadwal_id)                                  │
└─────────────────────────────────────────────────────┘
         │ mahasiswa_id (FK)
         └──────────► users
```

---

## 5. Skema Database

### Tabel: `users`
| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | INT AUTO_INCREMENT | Primary key |
| nama | VARCHAR(100) | Nama lengkap |
| identifier | VARCHAR(50) | NIM (mahasiswa) / NIDN (dosen) |
| password | VARCHAR(255) | bcrypt hash ($2y$12$) |
| role | ENUM('mahasiswa','dosen') | Role pengguna |
| is_active | TINYINT(1) | Status akun aktif/nonaktif |
| created_at | TIMESTAMP | Waktu registrasi |

> Constraint: `UNIQUE(identifier, role)`

### Tabel: `jadwal`
| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | INT AUTO_INCREMENT | Primary key |
| dosen_id | INT (FK) | Referensi ke users |
| tanggal | DATE | Tanggal slot |
| jam_mulai | TIME | Jam mulai slot |
| jam_selesai | TIME | Jam selesai slot |
| status | ENUM('tersedia','booked','dibatalkan') | Status slot |
| created_at | TIMESTAMP | Waktu pembuatan |

> Constraint: `UNIQUE(dosen_id, tanggal, jam_mulai)` — mencegah duplikasi slot  
> Index: `idx_tanggal_status`, `idx_dosen_tanggal`

### Tabel: `booking`
| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | INT AUTO_INCREMENT | Primary key |
| mahasiswa_id | INT (FK) | Referensi ke users |
| jadwal_id | INT (FK) | Referensi ke jadwal |
| topik | VARCHAR(255) | Topik konsultasi |
| catatan | TEXT NULL | Catatan tambahan opsional |
| status | ENUM('pending','approved','ditolak','selesai') | Status booking |
| approved_at | TIMESTAMP NULL | Waktu konfirmasi dosen |
| created_at | TIMESTAMP | Waktu pengajuan |

> Constraint: `UNIQUE(jadwal_id)` — satu slot hanya bisa dipegang satu booking  
> Index: `idx_mahasiswa_status`

### Tabel: `login_attempts`
| Kolom | Tipe | Keterangan |
|-------|------|-----------|
| id | INT AUTO_INCREMENT | Primary key |
| ip_address | VARCHAR(45) | IP klien (support IPv6) |
| identifier | VARCHAR(50) | NIM/NIDN yang dicoba |
| attempted_at | TIMESTAMP | Waktu percobaan |

> Index: `idx_ip_time`, `idx_identifier_time`

---

## 6. Fitur & Alur Sistem

### 6.1 Autentikasi

```
Login
 ├── Cek rate limit (IP + identifier, window 15 menit, max 5 attempt)
 ├── Query user by identifier
 ├── password_verify() — cost factor $12$ konsisten (anti timing attack)
 ├── Cek is_active
 ├── session_regenerate_id(true) — anti session fixation
 └── Redirect ke dashboard sesuai role
```

### 6.2 Alur Mahasiswa

```
Dashboard Mahasiswa
 ├── Lihat slot tersedia (JOIN jadwal + users → nama dosen)
 ├── Ajukan booking
 │    ├── Validasi CSRF token
 │    ├── BEGIN TRANSACTION
 │    ├── SELECT ... FOR UPDATE (anti race condition)
 │    ├── Cek status slot masih 'tersedia'
 │    ├── INSERT booking (status: pending)
 │    ├── UPDATE jadwal status → 'booked'
 │    └── COMMIT / ROLLBACK + error_log
 └── Lihat riwayat booking (status: pending/approved/ditolak/selesai)
```

### 6.3 Alur Dosen

```
Dashboard Dosen
 ├── Generate slot jadwal
 │    ├── Validasi CSRF token
 │    ├── Input: tanggal, jam_mulai, jam_selesai, durasi (menit)
 │    ├── DateInterval loop → array slots
 │    ├── True batch INSERT multi-value query
 │    ├── UNIQUE constraint menolak duplikasi (PDOException 23000)
 │    └── Flash: "N slot berhasil dibuat"
 ├── Lihat jadwal 7 hari ke depan
 └── Kelola booking pending
      ├── Validasi CSRF token
      ├── Validasi kepemilikan: JOIN booking→jadwal, cek dosen_id === session user_id
      ├── Approve → UPDATE booking status = 'approved', catat approved_at
      └── Tolak   → UPDATE booking status = 'ditolak' + kembalikan jadwal ke 'tersedia'
```

---

## 7. Keputusan Keamanan

| Vektor Ancaman | Mitigasi yang Diterapkan |
|----------------|--------------------------|
| **SQL Injection** | PDO Prepared Statements di seluruh query; `ATTR_EMULATE_PREPARES = false` |
| **XSS** | `htmlspecialchars($var, ENT_QUOTES, 'UTF-8')` pada semua output ke DOM |
| **CSRF** | Token `bin2hex(random_bytes(32))` pada semua form POST; validasi `hash_equals()` |
| **Session Fixation** | `session_regenerate_id(true)` mutlak setelah login berhasil |
| **Bypass Otorisasi** | `require_role()` di baris pertama setiap file; HTTP 403 jika gagal |
| **Race Condition** | `SELECT ... FOR UPDATE` dalam transaksi InnoDB |
| **Brute Force Login** | Tabel `login_attempts` berbasis IP + identifier (bukan sesi — bypass-proof) |
| **Timing Attack** | Dummy hash cost `$12$` identik — `password_verify()` tetap dieksekusi saat user tidak ditemukan |
| **Duplikasi Data** | `UNIQUE CONSTRAINT` di level database; tangkap `PDOException` kode `23000` |
| **Double Submit** | `button.disabled = true` via JavaScript setelah form submit |
| **Error Exposure** | Pesan error dilempar ke `error_log()`, bukan ke output klien |
| **Session Notice** | `session_status() === PHP_SESSION_NONE` sebelum `session_start()` |

---

## 8. Keputusan Desain Teknis

### Mengapa Native PHP + MySQL (bukan Pure HTML/JS)?

| Aspek | Pure HTML/JS | Native PHP + MySQL | Keputusan |
|-------|-------------|-------------------|-----------|
| Data persistence | localStorage (volatile) | MySQL (permanen) | ✅ PHP |
| Kesan profesional ke asesor | Sedang | Tinggi | ✅ PHP |
| Relevansi skema Junior Web Programmer | Cukup | Lebih relevan | ✅ PHP |
| Perlu server | Tidak | XAMPP/Laragon | Tersedia di lab |
| Ukuran RAR | ~200KB | ~800KB | Keduanya << 10MB |

### Mengapa InnoDB (bukan MyISAM)?

`SELECT ... FOR UPDATE` dan transaksi (`BEGIN/COMMIT/ROLLBACK`) **hanya** berfungsi pada InnoDB. MyISAM tidak mendukung row-level locking, sehingga race condition tidak dapat dieliminasi.

### Mengapa True Batch INSERT?

Loop `$stmt->execute()` dalam transaksi menghasilkan N round-trips ke database. Batch INSERT multi-value `INSERT INTO t VALUES (...),(...),...` menghasilkan **1 round-trip** — lebih presisi secara arsitektur dan lebih efisien pada slot berjumlah besar.

### Mengapa Rate Limiting Berbasis Database (bukan Sesi)?

Rate limiting sesi (`$_SESSION['login_attempts']`) dapat di-bypass dengan menghapus cookie `PHPSESSID` pada setiap request. Tabel `login_attempts` melacak berdasarkan **IP address dan identifier** pada penyimpanan persisten — tidak dapat di-bypass dengan manipulasi cookie.

### Mengapa Tidak Ada Upload Foto Profil?

Fitur upload file berpotensi menggelembungkan ukuran RAR secara signifikan dan memperkenalkan vektor serangan file upload. Avatar dihasilkan dari **inisial nama** menggunakan PHP + CSS — zero dependency, zero storage, tetap informatif.

---

## 9. Lifecycle Status Booking

```
[Mahasiswa ajukan]
       │
       ▼
   [ pending ]
       │
  Dosen review
  ┌────┴────┐
  ▼         ▼
[approved] [ditolak]
              │
              └──► jadwal dikembalikan ke 'tersedia'
[approved]
  │
  ▼
[selesai]  ← update manual pasca konsultasi berlangsung
```

---

## 10. Cara Setup & Menjalankan

### Prasyarat
- XAMPP / Laragon terinstall
- PHP >= 8.0, MySQL >= 8.0

### Langkah Setup

```bash
# 1. Ekstrak RAR ke folder htdocs
C:\xampp\htdocs\konsultasi_app\

# 2. Buka phpMyAdmin
http://localhost/phpmyadmin

# 3. Buat database baru
Nama: konsultasi_db

# 4. Import skema
Import file: database/konsultasi_db.sql

# 5. Jalankan aplikasi
http://localhost/konsultasi_app/
```

### Akun Default untuk Testing

| Role | Identifier | Password |
|------|-----------|---------|
| Mahasiswa | 062330801650 | password123 |
| Mahasiswa | 062330801651 | password123 |
| Dosen | 0012345601 | password123 |
| Dosen | 0098765402 | password123 |

> Semua password di-hash dengan `password_hash('password123', PASSWORD_BCRYPT)` — cost factor `$12$`.

---

## 11. Estimasi Ukuran Bundle

| Komponen | Estimasi |
|----------|---------|
| File PHP (9 file) | ~120 KB |
| CSS (inline per file) | ~40 KB |
| JavaScript (vanilla, inline) | ~10 KB |
| SQL schema + seed | ~8 KB |
| Dokumentasi (MVP.md) | ~15 KB |
| **Total RAR** | **~193 KB << 10 MB ✅** |

---

## 12. Checklist Presentasi Asesor

### Persiapan Sebelum Hari H
- [ ] Import `konsultasi_db.sql` ke phpMyAdmin lokal
- [ ] Verifikasi semua akun seed dapat login
- [ ] Test alur booking end-to-end (mahasiswa → dosen → approve)
- [ ] Test penolakan booking (slot kembali tersedia)
- [ ] Test generate jadwal batch (berbagai durasi)
- [ ] Siapkan bahan tayangan (PPT) sesuai format terlampir dari asesor
- [ ] Isi MUK sesuai arahan asesor

### Poin Teknis yang Siap Dijelaskan ke Asesor
- [ ] Mengapa menggunakan PDO Prepared Statements
- [ ] Bagaimana `FOR UPDATE` mencegah race condition
- [ ] Bagaimana CSRF token bekerja
- [ ] Mengapa `session_regenerate_id()` dipanggil setelah login
- [ ] Bagaimana batch INSERT dengan `DateInterval` bekerja
- [ ] Mengapa validasi kepemilikan jadwal diperlukan di `action_konfirmasi.php`

---

## 13. Catatan Pengembangan Lanjutan (Post-Ujikom)

Fitur-fitur berikut tidak diimplementasikan pada MVP ini namun relevan untuk pengembangan produksi:

- Notifikasi email saat booking disetujui/ditolak
- Halaman admin untuk manajemen user
- Export rekap booking ke PDF/Excel
- Fitur reschedule booking
- Pembatasan jumlah booking aktif per mahasiswa
- HTTPS enforcement (production)
- Database connection pooling

---

*Dokumen ini di-generate dari sesi perencanaan teknis komprehensif.*  
*Seluruh keputusan arsitektur, patch keamanan, dan implementasi kode*  
*telah divalidasi dan terintegrasi pada source code yang disertakan.*
