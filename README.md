# Sistem Booking Konsultasi Dosen & Mahasiswa

Sebuah platform web komprehensif yang memfasilitasi penjadwalan bimbingan atau konsultasi antara Dosen dan Mahasiswa. Sistem ini dirancang untuk menyelesaikan masalah bentrok jadwal, mempermudah dokumentasi bimbingan, dan meningkatkan efisiensi komunikasi akademik melalui antarmuka yang modern, cepat, dan aman.

## 🚀 Teknologi yang Digunakan

Sistem ini dibangun dengan filosofi *lightweight* dan *blazing fast*, tanpa bergantung pada framework berat.
- **Backend:** PHP 8+ (Vanilla / Native)
- **Database:** MySQL / MariaDB (via koneksi PDO yang aman)
- **Frontend (UI/UX):** HTML5 Semantik, Pure CSS3 (Flexbox & CSS Grid) untuk desain *responsive* bergaya *modern glassmorphism* & *dark mode*, serta Vanilla JavaScript untuk interaktivitas ringan.
- **Arsitektur:** Pemisahan *logic* yang jelas antara antarmuka pengguna (`dashboard.php`, `riwayat.php`) dan pemrosesan data (`action_*.php`).

## 🛡️ Standar Keamanan (Security)

Sistem ini sangat memperhatikan aspek keamanan aplikasi web:
1. **Anti SQL Injection:** Semua *query* database menggunakan **PDO Prepared Statements**.
2. **Proteksi CSRF (Cross-Site Request Forgery):** Seluruh formulir yang memanipulasi data dilindungi oleh validasi Token CSRF yang digenerate per-sesi.
3. **Password Hashing Kuat:** Menyimpan kata sandi menggunakan algoritma `bcrypt` dengan parameter `cost => 12`.
4. **Proteksi Brute-Force (Rate Limiting):** Fitur *Rate Limiting* berbasis *database* di halaman *login* (maksimal 5 kali percobaan gagal per IP/Identifier dalam 15 menit).
5. **Role-Based Access Control (RBAC):** Middleware sederhana (`require_role()`) yang memastikan Mahasiswa tidak bisa mengakses halaman Dosen, begitu juga sebaliknya.
6. **Mencegah Timing Attacks:** Implementasi verifikasi *dummy password* untuk menetralkan potensi celah *Timing Attack* saat pengguna tidak ditemukan di *database*.

---

## ✨ Fitur Berdasarkan Role Pengguna

### 1. 👑 Administrator (Admin)
Bertanggung jawab atas pengawasan dan kelancaran operasional sistem secara umum.
- **Dashboard Analytics:** Visualisasi metrik total pengguna (Dosen/Mahasiswa) dan status konsultasi (Selesai/Pending).
- **Manajemen Pengguna:** 
  - Melihat seluruh pengguna yang terdaftar di dalam sistem.
  - Memiliki kemampuan **mengaktifkan** atau **menonaktifkan** (*suspend*) akun pengguna secara instan (*toggle status*).
- **Pendaftaran Pengguna Baru:** Fitur formulir lengkap untuk mendaftarkan akun Dosen maupun Mahasiswa baru langsung dari *dashboard*.

### 2. 👨‍🏫 Dosen
Memiliki kuasa penuh untuk mengatur waktu luangnya.
- **Manajemen Ketersediaan (Slot Jadwal):** Dosen dapat membuka slot waktu (contoh: 09:00 - 11:00) untuk tanggal tertentu beserta catatan tambahan.
- **Menyetujui / Menolak Booking:** Dosen meninjau topik yang diajukan mahasiswa, kemudian memutuskan untuk menerima atau menolaknya dengan menyertakan *Catatan Dosen*.
- **Penyelesaian Konsultasi:** Setelah sesi bimbingan dilakukan, dosen dapat menekan tombol **"Tandai Selesai"**.
- **Riwayat & Cetak:** Melihat histori seluruh mahasiswa yang pernah dibimbing, difilter berdasarkan rentang tanggal/status, lalu **Mencetak Bukti Konsultasi** sebagai dokumen fisik (PDF/Print).

### 3. 🎓 Mahasiswa
Pengguna utama sistem ini yang membutuhkan bimbingan.
- **Kalender Visual Mingguan:** Melihat jadwal luang dosen dalam tampilan Kalender Horizontal (*7-Day Outlook*) yang interaktif.
- **Booking Konsultasi:** Memilih slot tersedia milik dosen, mengisi *Topik* bahasan, lalu mengajukan jadwal.
- **Riwayat Khusus Mahasiswa:** Halaman terpisah (`/mahasiswa/riwayat.php`) untuk melihat status seluruh pengajuan (Pending, Disetujui, Ditolak, Dibatalkan, atau Selesai).
- **Rating Bintang Interaktif:** Mahasiswa diwajibkan (secara moral) memberikan penilaian **1-5 Bintang** untuk setiap sesi konsultasi yang statusnya sudah 'Selesai'. (Menggunakan UI *Pure CSS Star Rating*).

---

## ⚙️ Fitur Canggih Sistem (Under The Hood)

- **Auto-Cleanup Jadwal Kadaluarsa:** Terdapat *script* otomatis yang mendeteksi jika slot yang `tersedia` tidak kunjung di-booking hingga tanggalnya terlewat. Sistem otomatis akan mengubah statusnya menjadi `dibatalkan` agar *database* dan tampilan kalender tetap bersih.
- **Real-time Notifications:** Lonceng notifikasi pintar yang akan memberitahu user tentang perubahan status (misal: "Booking Anda disetujui dosen", "Mahasiswa mem-booking slot Anda"). Dilengkapi dengan fitur *Mark All as Read*.
- **Avatar & Inisial Otomatis:** Pembuatan logo profil berwarna secara prosedural (*procedural color mapping*) berdasarkan huruf pertama nama pengguna.

---

## 🔄 Alur Sistem (System Flow)

1. **Inisiasi Akun:** Admin mendaftarkan NIP Dosen dan NIM Mahasiswa (atau menggunakan *seed data* yang sudah ada).
2. **Pembuatan Jadwal:** Dosen *login* ke sistem, lalu menambahkan beberapa Slot Tersedia di tanggal esok hari.
3. **Proses Pengajuan:** Mahasiswa *login*, melihat kalender, dan mengajukan "Booking" pada slot dosen tersebut dengan menyertakan topik bahasan.
4. **Verifikasi:** Slot berubah status menjadi `booked`. Dosen mendapat notifikasi, lalu menyetujui pengajuan tersebut (*Status berubah jadi: Approved*).
5. **Pelaksanaan & Penyelesaian:** Mahasiswa dan Dosen bertemu pada waktu yang disepakati. Setelah selesai, Dosen menandai booking sebagai `Selesai`.
6. **Penilaian (Feedback):** Mahasiswa melihat status booking menjadi Selesai di Riwayat, dan langsung memberikan *Rating Bintang* terhadap sesi bimbingan tersebut.

---

**© 2026 Hak Cipta Terpelihara.** Dikembangkan khusus untuk memodernisasi ekosistem akademik.
