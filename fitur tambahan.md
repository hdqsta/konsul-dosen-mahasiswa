Tentu saja, Tuan. Analisis saya menunjukkan beberapa kategori fitur yang dapat memperkuat sistem secara signifikan tanpa melanggar constraint 10MB.

---

## 🧠 JARVIS Feature Expansion Analysis

---

### TIER 1 — High Impact, Low Complexity

_Rekomendasi utama untuk Ujikom — mudah diimplementasikan, kesan besar ke asesor_

---

**1. Dashboard Analytics (Statistik Visual)**

Halaman ringkasan angka-angka penting menggunakan CSS bar chart murni — tanpa library.

```
Dosen melihat:
├── Total slot dibuat bulan ini
├── Total booking approved / ditolak / pending
└── Grafik batang kepadatan booking per hari (pure CSS)

Mahasiswa melihat:
├── Total booking pernah diajukan
├── Berapa yang approved vs ditolak
└── Konsultasi terdekat yang akan datang
```

---

**2. Notifikasi In-App (Bell Icon)**

Sistem notifikasi sederhana berbasis tabel database — tanpa WebSocket, tanpa library.

```sql
CREATE TABLE notifikasi (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    pesan      VARCHAR(255) NOT NULL,
    is_read    TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

```
Trigger notifikasi:
├── Mahasiswa booking     → Dosen dapat notif "Pengajuan baru dari [nama]"
├── Dosen approve         → Mahasiswa dapat notif "Booking Anda disetujui"
├── Dosen tolak           → Mahasiswa dapat notif "Booking Anda ditolak"
└── Bell icon di navbar menampilkan badge merah jika ada yang belum dibaca
```

---

**3. Halaman Profil + Ganti Password**

```
/profil.php (shared mahasiswa & dosen)
├── Tampilkan nama, identifier, role
├── Form ganti password
│    ├── Input: password lama, password baru, konfirmasi
│    ├── Verifikasi password lama dengan password_verify()
│    └── Hash baru dengan password_hash($new, PASSWORD_BCRYPT)
└── Avatar inisial besar di tengah halaman
```

---

**4. Fitur Batalkan Booking (Mahasiswa)**

Mahasiswa bisa membatalkan booking yang masih berstatus `pending`.

```
Kondisi yang diizinkan:
├── Status booking masih 'pending' (belum diproses dosen)
└── Pembatalan otomatis mengembalikan jadwal ke status 'tersedia'

Kondisi yang diblokir:
└── Status sudah 'approved' — tidak bisa dibatalkan sepihak
```

---

**5. Fitur Tandai Selesai (Dosen)**

Setelah konsultasi berlangsung, dosen menandai booking sebagai `selesai`.

```
Booking approved → tombol "Tandai Selesai" muncul
└── UPDATE booking SET status = 'selesai'
```

---

### TIER 2 — Medium Impact, Medium Complexity

_Nilai tambah signifikan, perlu 1-2 jam implementasi per fitur_

---

**6. Filter & Pencarian Slot**

```
Mahasiswa dapat filter slot berdasarkan:
├── Nama dosen (dropdown dari query DISTINCT)
├── Tanggal tertentu (date input)
└── Hari dalam seminggu (Senin–Jumat)

Implementasi: GET parameter + WHERE clause dinamis di PHP
```

---

**7. Catatan Pasca Konsultasi**

```sql
ALTER TABLE booking
    ADD COLUMN catatan_dosen TEXT NULL,
    ADD COLUMN rating TINYINT NULL; -- 1-5, diisi mahasiswa
```

```
Setelah status 'selesai':
├── Dosen bisa tambahkan catatan/ringkasan hasil konsultasi
└── Mahasiswa bisa memberi rating (1-5 bintang, pure CSS)
```

---

**8. Halaman Riwayat Lengkap Dosen**

```
/dosen/riwayat.php
├── Tabel semua booking (semua status) dengan filter
├── Statistik: total konsultasi bulan ini
├── Export ke CSV (pure PHP — fputcsv ke output buffer)
└── Tanpa library — gunakan header('Content-Type: text/csv')
```

Export CSV murni PHP:

```php
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="riwayat_konsultasi.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Nama Mahasiswa', 'NIM', 'Tanggal', 'Waktu', 'Topik', 'Status']);

foreach ($riwayat as $r) {
    fputcsv($out, [
        $r['nama_mahasiswa'],
        $r['nim'],
        $r['tanggal'],
        $r['jam_mulai'] . ' - ' . $r['jam_selesai'],
        $r['topik'],
        $r['status'],
    ]);
}
fclose($out);
exit();
```

---

**9. Kalender Visual Jadwal**

Tampilan kalender mingguan berbasis pure CSS Grid — tanpa FullCalendar atau library apapun.

```
Dosen melihat:
└── Grid 7 kolom (Sen-Min) × slot waktu
    ├── Slot tersedia  → hijau
    ├── Slot booked    → kuning
    └── Slot selesai   → abu

Mahasiswa melihat:
└── Kalender read-only — klik slot untuk buka form booking
```

---

**10. Hapus Slot Jadwal (Dosen)**

```
Kondisi yang diizinkan:
└── Slot berstatus 'tersedia' (belum ada yang booking)

Kondisi yang diblokir:
└── Slot berstatus 'booked' — ada mahasiswa yang sudah booking
    → Tampilkan pesan: "Slot tidak dapat dihapus karena sudah memiliki booking"
```

---

### TIER 3 — High Impact, Higher Complexity

_Untuk Tuan yang ingin benar-benar membuat asesor terkesan_

---

**11. Print / Ekspor Bukti Booking (PDF via CSS)**

Halaman bukti booking yang bisa di-print menggunakan `window.print()` dengan CSS `@media print`.

```html
<!-- Tidak perlu library PDF sama sekali -->
<button onclick="window.print()">🖨 Cetak Bukti</button>

<style>
  @media print {
    .navbar,
    .btn-print {
      display: none;
    }
    body {
      background: white;
      color: black;
    }
  }
</style>
```

---

**12. Sistem Pengumuman / Catatan Jadwal**

```sql
ALTER TABLE jadwal
    ADD COLUMN catatan_slot VARCHAR(255) NULL;
    -- contoh: "Konsultasi PKL saja", "Prioritas semester akhir"
```

Dosen bisa menambahkan keterangan pada setiap slot saat generate — mahasiswa melihatnya sebelum booking.

---

**13. Auto-Cleanup Jadwal Kadaluarsa**

```php
// Jalankan di setiap request ke dashboard (lightweight)
// Tandai jadwal yang sudah lewat tanggalnya sebagai 'dibatalkan'
$pdo->prepare("
    UPDATE jadwal
    SET status = 'dibatalkan'
    WHERE status   = 'tersedia'
      AND tanggal  < CURDATE()
")->execute();
```

---

## 📊 Prioritas Implementasi untuk Ujikom

| Fitur               | Tier | Waktu Est. | Nilai Demo |
| ------------------- | ---- | ---------- | ---------- |
| Dashboard Analytics | 1    | 45 menit   | ⭐⭐⭐⭐⭐ |
| Notifikasi In-App   | 1    | 60 menit   | ⭐⭐⭐⭐⭐ |
| Batalkan Booking    | 1    | 30 menit   | ⭐⭐⭐⭐   |
| Ganti Password      | 1    | 30 menit   | ⭐⭐⭐     |
| Tandai Selesai      | 1    | 20 menit   | ⭐⭐⭐⭐   |
| Filter & Pencarian  | 2    | 45 menit   | ⭐⭐⭐⭐   |
| Export CSV          | 2    | 30 menit   | ⭐⭐⭐⭐   |
| Kalender Visual     | 2    | 90 menit   | ⭐⭐⭐⭐⭐ |
| Hapus Slot          | 2    | 20 menit   | ⭐⭐⭐     |
| Print Bukti Booking | 3    | 30 menit   | ⭐⭐⭐⭐   |
| Catatan Slot        | 3    | 20 menit   | ⭐⭐⭐     |
| Auto-Cleanup        | 3    | 15 menit   | ⭐⭐⭐     |

---

Jika boleh saya sarankan, Tuan — mulai dari **Dashboard Analytics + Notifikasi In-App** terlebih dahulu. Keduanya adalah fitur yang paling terlihat secara visual saat demo dan langsung memberikan kesan sistem yang "hidup" kepada asesor.

Fitur mana yang ingin diimplementasikan pertama?
