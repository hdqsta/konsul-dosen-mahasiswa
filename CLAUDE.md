# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Native PHP 8 + MySQL consultation booking system (Junior Web Programmer ujikom). Multi-role: mahasiswa (student) and dosen (lecturer).

## Running the Application

```bash
# Setup database
# 1. Import database/konsultasi_db.sql via phpMyAdmin
# 2. Database name: konsultasi_db

# Run (via Laragon or PHP built-in)
php -S localhost:8080

# Access: http://localhost:8080 (or /LSP via Laragon)
```

## Test Accounts

| Role | Identifier | Password |
|------|------------|----------|
| Dosen | 123456789 | password123 |
| Dosen | 123456790 | password123 |
| Mahasiswa | 2021001 | password123 |
| Mahasiswa | 2021002 | password123 |

## Architecture

```
├── index.php          # Redirect to login
├── login.php          # Auth with rate limiting + timing attack protection
├── logout.php         # Session destroy
├── includes/
│   ├── db.php         # PDO singleton, timezone set +07:00
│   └── auth.php       # session_init, require_role, login_user, logout_user
├── mahasiswa/
│   ├── dashboard.php      # View slots, book, view history
│   └── action_booking.php # CSRF + FOR UPDATE transaction
├── dosen/
│   ├── dashboard.php              # Generate slots + manage bookings
│   ├── action_generate_jadwal.php # Batch insert with duplicate filter
│   └── action_konfirmasi.php     # Approve/reject with ownership validation
└── database/
    └── konsultasi_db.sql
```

## Security Implementation

- **SQL Injection**: PDO prepared statements, `ATTR_EMULATE_PREPARES = false`
- **XSS**: `htmlspecialchars($var, ENT_QUOTES, 'UTF-8')` at render points (not in helpers)
- **CSRF**: `bin2hex(random_bytes(32))` on all POST forms, validated with `hash_equals()`
- **Session Fixation**: `session_regenerate_id(true)` after login
- **Rate Limiting**: Database table `login_attempts` (IP + identifier), 15-min window, max 5 attempts
- **Timing Attack**: Dummy bcrypt hash with same cost factor ($12$)
- **Race Condition**: `SELECT ... FOR UPDATE` in InnoDB transaction
- **Authorization**: `require_role()` on every protected endpoint, ownership validation via JOIN

## Key Code Patterns

- `get_pdo()` returns singleton PDO instance
- `require_role('mahasiswa'|'dosen')` returns user array or redirects
- All action files begin with CSRF validation, end with `header()` redirect
- Flash messages via `$_SESSION['flash']` / `$_SESSION['flash_error']`
- Timestamps use MySQL `CURDATE()` / `CURTIME()` after `SET time_zone = '+07:00'`

## Database Tables

- `users`: id, identifier (NIM/NIP), nama, password (bcrypt), role, is_active
- `jadwal`: id, dosen_id, tanggal, jam_mulai, jam_selesai, status (tersedia/booked/dibatalkan)
- `booking`: id, mahasiswa_id, jadwal_id, topik, status (pending/approved/ditolak/selesai)
- `login_attempts`: id, ip_address, identifier, attempted_at

## Status Workflow

```
pending → approved (dosen approves)
       → ditolak (dosen rejects, jadwal returns to 'tersedia')
       → selesai (manual update after consultation)
```