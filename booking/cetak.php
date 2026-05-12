<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

init_session();

// Any role can print their own booking
if (empty($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit();
}

$pdo      = get_pdo();
$user_id  = (int)$_SESSION['user_id'];
$role     = $_SESSION['role'];
$booking_id = (int)($_GET['id'] ?? 0);

if ($booking_id <= 0) {
    http_response_code(400);
    exit('Parameter tidak valid.');
}

// Fetch booking — pastikan user boleh akses
$stmt = $pdo->prepare("
    SELECT
        b.id,
        b.topik,
        b.status,
        b.created_at,
        b.catatan_dosen,
        b.approved_at,
        j.tanggal,
        j.jam_mulai,
        j.jam_selesai,
        j.catatan_slot,
        mhs.nama       AS nama_mahasiswa,
        mhs.identifier AS nim,
        dsn.nama       AS nama_dosen,
        dsn.identifier AS nip
    FROM booking b
    JOIN jadwal j   ON j.id  = b.jadwal_id
    JOIN users  mhs ON mhs.id = b.mahasiswa_id
    JOIN users  dsn ON dsn.id = j.dosen_id
    WHERE b.id = :booking_id
");
$stmt->execute(['booking_id' => $booking_id]);
$booking = $stmt->fetch();

if (!$booking) {
    http_response_code(404);
    exit('Booking tidak ditemukan.');
}

// Authorisation: mahasiswa hanya bisa cetak miliknya, dosen hanya jadwalnya
// (query sudah cukup ketat; tambahan validasi role)
$mhs_stmt = $pdo->prepare("SELECT mahasiswa_id FROM booking WHERE id = ?");
$mhs_stmt->execute([$booking_id]);
$row = $mhs_stmt->fetch();

if ($role === 'mahasiswa' && (int)$row['mahasiswa_id'] !== $user_id) {
    http_response_code(403); exit('403 Forbidden.');
}
if ($role === 'dosen') {
    $own = $pdo->prepare("SELECT j.dosen_id FROM booking b JOIN jadwal j ON j.id=b.jadwal_id WHERE b.id=?");
    $own->execute([$booking_id]);
    if ((int)$own->fetchColumn() !== $user_id) {
        http_response_code(403); exit('403 Forbidden.');
    }
}

$status_label = [
    'pending'  => 'Menunggu Konfirmasi',
    'approved' => 'Disetujui',
    'ditolak'  => 'Ditolak',
    'selesai'  => 'Selesai',
][$booking['status']] ?? $booking['status'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Bukti Booking #<?= $booking_id ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f8fafc;
            color: #1e293b;
            padding: 2rem;
        }
        .print-container {
            max-width: 680px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 2.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #0ea5e9;
            padding-bottom: 1.25rem;
            margin-bottom: 1.75rem;
        }
        .header h1 {
            font-size: 1.4rem;
            font-weight: 700;
            color: #0ea5e9;
            letter-spacing: -0.5px;
        }
        .header p {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 0.25rem;
        }
        .booking-id {
            display: inline-block;
            background: #f0f9ff;
            color: #0284c7;
            border: 1px solid #bae6fd;
            border-radius: 6px;
            padding: 0.25rem 0.75rem;
            font-size: 0.85rem;
            font-weight: 700;
            margin-top: 0.5rem;
        }
        .section {
            margin-bottom: 1.5rem;
        }
        .section-title {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #94a3b8;
            margin-bottom: 0.75rem;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem 1.5rem;
        }
        .info-item label {
            display: block;
            font-size: 0.72rem;
            color: #94a3b8;
            font-weight: 600;
            margin-bottom: 0.2rem;
        }
        .info-item span {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            color: #1e293b;
        }
        .topik-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.9rem 1rem;
            font-size: 0.9rem;
            color: #334155;
            line-height: 1.5;
        }
        .status-badge {
            display: inline-block;
            padding: 0.3rem 1rem;
            border-radius: 99px;
            font-size: 0.8rem;
            font-weight: 700;
        }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-pending  { background: #fef3c7; color: #92400e; }
        .status-ditolak  { background: #fee2e2; color: #991b1b; }
        .status-selesai  { background: #e0e7ff; color: #3730a3; }
        .catatan-box {
            background: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 0.9rem 1rem;
            font-size: 0.875rem;
            color: #78350f;
            line-height: 1.5;
        }
        .footer {
            margin-top: 2rem;
            padding-top: 1.25rem;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        .footer-note {
            font-size: 0.75rem;
            color: #94a3b8;
        }
        .btn-print {
            padding: 0.55rem 1.25rem;
            background: #0ea5e9;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
        }
        .btn-print:hover { background: #0284c7; }
        .btn-back {
            padding: 0.55rem 1.25rem;
            background: transparent;
            border: 1px solid #e2e8f0;
            color: #64748b;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            text-decoration: none;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .print-container { box-shadow: none; border: none; padding: 1rem; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
<div class="print-container">
    <div class="header">
        <h1>Bukti Booking Konsultasi</h1>
        <p>Sistem Konsultasi Akademik</p>
        <div class="booking-id">Booking #<?= $booking_id ?></div>
    </div>

    <div class="section">
        <div class="section-title">Status Booking</div>
        <span class="status-badge status-<?= htmlspecialchars($booking['status']) ?>">
            <?= htmlspecialchars($status_label) ?>
        </span>
    </div>

    <div class="section">
        <div class="section-title">Informasi Mahasiswa</div>
        <div class="info-grid">
            <div class="info-item">
                <label>Nama</label>
                <span><?= htmlspecialchars($booking['nama_mahasiswa']) ?></span>
            </div>
            <div class="info-item">
                <label>NIM</label>
                <span><?= htmlspecialchars($booking['nim']) ?></span>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Informasi Dosen</div>
        <div class="info-grid">
            <div class="info-item">
                <label>Nama</label>
                <span><?= htmlspecialchars($booking['nama_dosen']) ?></span>
            </div>
            <div class="info-item">
                <label>NIP</label>
                <span><?= htmlspecialchars($booking['nip']) ?></span>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Jadwal Konsultasi</div>
        <div class="info-grid">
            <div class="info-item">
                <label>Tanggal</label>
                <span><?= date('l, d F Y', strtotime($booking['tanggal'])) ?></span>
            </div>
            <div class="info-item">
                <label>Waktu</label>
                <span><?= htmlspecialchars($booking['jam_mulai']) ?> – <?= htmlspecialchars($booking['jam_selesai']) ?></span>
            </div>
        </div>
        <?php if (!empty($booking['catatan_slot'])): ?>
        <div style="margin-top:0.75rem">
            <div class="info-item">
                <label>Catatan Slot</label>
                <span style="font-weight:400"><?= htmlspecialchars($booking['catatan_slot']) ?></span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="section">
        <div class="section-title">Topik Konsultasi</div>
        <div class="topik-box"><?= nl2br(htmlspecialchars($booking['topik'])) ?></div>
    </div>

    <?php if (!empty($booking['catatan_dosen'])): ?>
    <div class="section">
        <div class="section-title">Catatan Dosen</div>
        <div class="catatan-box"><?= nl2br(htmlspecialchars($booking['catatan_dosen'])) ?></div>
    </div>
    <?php endif; ?>

    <div class="section">
        <div class="section-title">Tanggal Pengajuan</div>
        <div class="info-item">
            <span style="font-weight:400"><?= date('d F Y, H:i', strtotime($booking['created_at'])) ?> WIB</span>
        </div>
        <?php if ($booking['approved_at']): ?>
        <div class="info-item" style="margin-top:0.5rem">
            <label>Tanggal Disetujui</label>
            <span style="font-weight:400"><?= date('d F Y, H:i', strtotime($booking['approved_at'])) ?> WIB</span>
        </div>
        <?php endif; ?>
    </div>

    <div class="footer">
        <div class="footer-note">
            Dicetak pada: <?= date('d F Y, H:i') ?> WIB<br>
            Dokumen ini sah tanpa tanda tangan basah.
        </div>
        <div class="no-print" style="display:flex;gap:0.5rem">
            <a href="javascript:history.back()" class="btn-back">Kembali</a>
            <button class="btn-print" onclick="window.print()">Cetak Dokumen</button>
        </div>
    </div>
</div>
</body>
</html>
