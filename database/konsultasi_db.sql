-- ============================================================
-- DATABASE: konsultasi_db
-- Sistem Konsultasi Dosen-Mahasiswa
-- ============================================================

-- TABEL 1: users
-- Users dengan role mahasiswa/dosen
-- ============================================================
CREATE TABLE users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    identifier  VARCHAR(50)  NOT NULL UNIQUE,   -- NIM untuk mahasiswa, NIP untuk dosen
    nama        VARCHAR(100) NOT NULL,
    password    VARCHAR(255) NOT NULL,           -- bcrypt hash
    role        ENUM('mahasiswa','dosen','admin') NOT NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_identifier (identifier),
    INDEX idx_role (role)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- TABEL 2: jadwal
-- Slot jadwal yang dibuat dosen
-- ============================================================
CREATE TABLE jadwal (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    dosen_id    INT          NOT NULL,
    tanggal     DATE         NOT NULL,
    jam_mulai   TIME         NOT NULL,
    jam_selesai TIME         NOT NULL,
    status      ENUM('tersedia','booked','dibatalkan') NOT NULL DEFAULT 'tersedia',
    catatan_slot VARCHAR(255) NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (dosen_id) REFERENCES users(id) ON DELETE CASCADE,

    INDEX idx_dosen_tanggal (dosen_id, tanggal),
    INDEX idx_tanggal      (tanggal),
    INDEX idx_status      (status),

    UNIQUE KEY uk_dosen_slot (dosen_id, tanggal, jam_mulai)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- TABEL 3: booking
-- Pemesanan slot oleh mahasiswa
-- ============================================================
CREATE TABLE booking (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    mahasiswa_id  INT          NOT NULL,
    jadwal_id     INT          NOT NULL,
    topik         TEXT         NOT NULL,
    status        ENUM('pending','approved','ditolak','selesai','dibatalkan') NOT NULL DEFAULT 'pending',
    catatan_dosen TEXT         NULL,
    rating        TINYINT      NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_at   TIMESTAMP    NULL,

    FOREIGN KEY (mahasiswa_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (jadwal_id)    REFERENCES jadwal(id) ON DELETE CASCADE,

    INDEX idx_mahasiswa (mahasiswa_id),
    INDEX idx_jadwal   (jadwal_id),
    INDEX idx_status   (status)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABEL 4: login_attempts
-- Rate limiting berbasis IP + identifier pada penyimpanan persisten
-- ============================================================
CREATE TABLE login_attempts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    ip_address   VARCHAR(45)  NOT NULL,   -- support IPv6
    identifier   VARCHAR(50)  NOT NULL,
    attempted_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_ip_time         (ip_address, attempted_at),
    INDEX idx_identifier_time (identifier, attempted_at)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABEL 5: notifikasi
-- Notifikasi in-app untuk user
-- ============================================================
CREATE TABLE notifikasi (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          NOT NULL,
    pesan       VARCHAR(255) NOT NULL,
    is_read     TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEED DATA: Contoh user untuk testing
-- Password: "password123" (bcrypt $2y$12$)
-- ============================================================
-- Admin: identifier admin, password: password123
INSERT INTO users (identifier, nama, password, role, is_active) VALUES
('admin', 'Administrator', '$2y$12$ov6248.6jCThig2ykmEf/OWAOcCaI/gPVHRKI02XR7okGm9fxxNr.', 'admin', 1);

-- Dosen: NIP 123456789, password: password123
INSERT INTO users (identifier, nama, password, role, is_active) VALUES
('123456789', 'Dr. Ahmad Fauzi', '$2y$12$ov6248.6jCThig2ykmEf/OWAOcCaI/gPVHRKI02XR7okGm9fxxNr.', 'dosen', 1),
('123456790', 'Prof. Siti Nurhaliza', '$2y$12$ov6248.6jCThig2ykmEf/OWAOcCaI/gPVHRKI02XR7okGm9fxxNr.', 'dosen', 1);

-- Mahasiswa: NIM 2021001, password: password123
INSERT INTO users (identifier, nama, password, role, is_active) VALUES
('2021001', 'Budi Santoso', '$2y$12$ov6248.6jCThig2ykmEf/OWAOcCaI/gPVHRKI02XR7okGm9fxxNr.', 'mahasiswa', 1),
('2021002', 'Ani Wijaya', '$2y$12$ov6248.6jCThig2ykmEf/OWAOcCaI/gPVHRKI02XR7okGm9fxxNr.', 'mahasiswa', 1);