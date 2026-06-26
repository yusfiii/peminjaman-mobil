<?php
session_start();
include "../config/database.php";

// Cek apakah pengguna sudah login
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$user = $_SESSION['user'];
$user_id = (int)$user['id'];
$user_name = $user['nama'] ?? 'User';
$user_role = $user['role'] ?? 'pegawai';

// Set timezone secara eksplisit
date_default_timezone_set('Asia/Makassar'); // WITA

// 1. AMBIL DATA PEMINJAMAN AKTIF SAJA
$peminjaman_saya = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT p.*, m.nama_mobil, m.nomor_plat, m.foto
    FROM peminjaman p
    JOIN mobil m ON p.mobil_id = m.id
    WHERE p.user_id = '{$user_id}'
      AND p.tanggal_kembali IS NULL
      AND p.status NOT IN ('Dikembalikan', 'Ditolak', 'Dibatalkan')
    ORDER BY p.id DESC
    LIMIT 1
"));

$status = null;
$isLewatBatas = false;
$batas_toleransi = null;
$autoReset = false;
$db_status = null;
$waktu_selesai = null;
$is_auto_ditolak = false;
$peminjaman_aktif = false; // Flag untuk cek apakah ada peminjaman aktif

$now = time(); // Waktu server saat ini dalam timestamp UNIX

if ($peminjaman_saya) {
    $peminjaman_aktif = true;

    // FORMAT KONVERSI WAKTU DENGAN BENAR
    $jam_mulai_db = $peminjaman_saya['jam_mulai'];
    $jam_selesai_db = $peminjaman_saya['jam_selesai'];

    // Debug info
    error_log("=== DEBUG INFO ===");
    error_log("Tanggal: " . $peminjaman_saya['tanggal_pinjam']);
    error_log("Jam Mulai DB: " . $jam_mulai_db);
    error_log("Jam Selesai DB: " . $jam_selesai_db);
    error_log("Timezone PHP: " . date_default_timezone_get());

    // KONVERSI KE 24 JAM JIKA PERLU (pastikan format konsisten)
    $jam_mulai_24 = date("H:i:s", strtotime($jam_mulai_db));
    $jam_selesai_24 = date("H:i:s", strtotime($jam_selesai_db));

    error_log("Jam Mulai 24h: " . $jam_mulai_24);
    error_log("Jam Selesai 24h: " . $jam_selesai_24);

    // Buat timestamp dengan format yang benar
    $date_format = $peminjaman_saya['tanggal_pinjam'];

    // Parse tanggal dan waktu
    $mulai_str = $date_format . ' ' . $jam_mulai_24;
    $selesai_str = $date_format . ' ' . $jam_selesai_24;

    $mulai = strtotime($mulai_str);
    $selesai = strtotime($selesai_str);
    $waktu_selesai = $selesai; // Simpan untuk pengecekan otomatis ditolak

    error_log("Timestamp Mulai: " . date('Y-m-d H:i:s', $mulai));
    error_log("Timestamp Selesai: " . date('Y-m-d H:i:s', $selesai));
    error_log("Timestamp Now: " . date('Y-m-d H:i:s', $now));

    // HITUNG DURASI SEBENARNYA
    $durasi_detik = $selesai - $mulai;
    $durasi_jam = floor($durasi_detik / 3600);
    $durasi_menit = floor(($durasi_detik % 3600) / 60);

    error_log("Durasi Peminjaman: " . $durasi_jam . " jam " . $durasi_menit . " menit (" . $durasi_detik . " detik)");

    // PERINGATAN JIKA DURASI TIDAK WAJAR
    if ($durasi_jam > 8) { // Lebih dari 8 jam
        error_log("PERINGATAN: Durasi peminjaman tidak wajar! " . $durasi_jam . " jam");
    }

    // Batas toleransi: 15 menit setelah jam selesai
    $batas_toleransi = $selesai + (15 * 60);

    error_log("Batas Toleransi: " . date('Y-m-d H:i:s', $batas_toleransi));

    // 2. CEK APAKAH SUDAH LEWAT BATAS TOLERANSI (hanya untuk status Dipinjam)
    if ($peminjaman_saya['status'] === 'Dipinjam' && $now > $batas_toleransi) {
        // Jika sudah lewat batas toleransi, otomatis mengembalikan mobil
        $status = 'Auto Dikembalikan';
        $autoReset = true;

        // PROSES RESET OTOMATIS
        mysqli_query($conn, "
            UPDATE peminjaman 
            SET status = 'Dikembalikan', 
                tanggal_kembali = NOW()
            WHERE id = '{$peminjaman_saya['id']}'
        ");

        mysqli_query($conn, "
            UPDATE mobil 
            SET status = 'Tersedia', 
                dipakai_oleh = NULL 
            WHERE id = '{$peminjaman_saya['mobil_id']}'
        ");

        // Setelah update, redirect untuk refresh data
        header("Location: struk.php?auto_reset=1");
        exit;
    }

    // 3. LOGIKA STATUS (DI LUAR kondisi lewat batas)
    // Tentukan status berdasarkan nilai di database
    $db_status = $peminjaman_saya['status'];

    // LOGIKA OTOMATIS DITOLAK: Jika status Menunggu ACC dan waktu sudah lewat
    if ($db_status === 'Menunggu ACC') {
        // Jika waktu selesai sudah lewat, otomatis jadi Ditolak
        if ($now > $waktu_selesai) {
            $db_status = 'Ditolak';
            $status = 'Ditolak';
            $is_auto_ditolak = true;

            // Update database karena sudah lewat waktu
            mysqli_query($conn, "
                UPDATE peminjaman 
                SET status = 'Ditolak'
                WHERE id = '{$peminjaman_saya['id']}'
            ");

            // Set flag bahwa ini sudah selesai (Ditolak)
            $peminjaman_aktif = false;
        }
    }

    // Handle status yang kosong/null
    if (empty($db_status) || $db_status === '') {
        $db_status = 'Menunggu ACC'; // Default
    }

    if ($db_status === 'Ditolak') {
        $status = 'Ditolak';
        $peminjaman_aktif = false; // Status Ditolak bukan peminjaman aktif
        // Cek apakah ini auto-ditolak
        if ($peminjaman_saya['status'] === 'Menunggu ACC' && $now > $waktu_selesai) {
            $is_auto_ditolak = true;
        }
    } elseif ($db_status === 'Dibatalkan') {
        $status = 'Dibatalkan';
        $peminjaman_aktif = false; // Status Dibatalkan bukan peminjaman aktif
    } elseif ($db_status === 'Menunggu ACC') {
        $status = 'Menunggu ACC';
    } elseif ($db_status === 'Dikembalikan') {
        $status = 'Dikembalikan';
        $peminjaman_aktif = false; // Status Dikembalikan bukan peminjaman aktif
    }
    // Hanya status 'Dipinjam' yang dicek waktu
    elseif ($db_status === 'Dipinjam') {
        if ($now > $selesai) {
            $status = 'Lewat Batas';
            $isLewatBatas = true;
        } elseif ($now >= $mulai) {
            $status = 'Dipinjam';
        } else {
            $status = 'Menunggu Jadwal';
        }
    } else {
        // Jika status tidak dikenali, default ke Menunggu ACC
        $status = 'Menunggu ACC';
        error_log("WARNING: Status tidak dikenali: " . $db_status);
    }
}

// Ambil riwayat peminjaman terbaru (5 terakhir)
$riwayat = mysqli_query($conn, "
    SELECT 
        p.*,
        m.nama_mobil,
        m.nomor_plat,
        -- TENTUKAN STATUS AKHIR (OTOMATIS TOLAK JIKA LEWAT)
        CASE 
            -- Jika status Menunggu ACC dan waktu selesai sudah lewat, otomatis Ditolak
            WHEN p.status = 'Menunggu ACC' 
                 AND CONCAT(p.tanggal_pinjam, ' ', p.jam_selesai) < NOW()
            THEN 'Ditolak'
            -- Status lainnya tetap
            ELSE p.status
        END as status_akhir
    FROM peminjaman p
    JOIN mobil m ON p.mobil_id = m.id
    WHERE p.user_id = '{$user_id}'
    ORDER BY p.id DESC
    LIMIT 5
");

$updated = date('d/m/Y H:i:s');
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Saya - Peminjaman Mobil Dinas</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fb;
        }

        .sidebar {
            width: 250px;
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: linear-gradient(180deg, #2c3e50 0%, #1a252f 100%);
            color: white;
            padding-top: 20px;
            z-index: 1000;
        }

        .sidebar .user-info {
            text-align: center;
            padding: 20px 15px;
            margin-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar .user-avatar {
            width: 70px;
            height: 70px;
            background-color: #3498db;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 2rem;
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 12px 20px;
            margin: 5px 15px;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .sidebar .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            text-decoration: none;
        }

        .sidebar .nav-link.active {
            background-color: #3498db;
            color: white;
        }

        .sidebar .logout-btn {
            display: block;
            margin: 30px 15px 20px;
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            transition: all 0.3s;
        }

        .sidebar .logout-btn:hover {
            background-color: #c0392b;
            text-decoration: none;
            color: white;
        }

        .sidebar .copyright {
            text-align: center;
            padding: 15px;
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.5);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            margin-top: auto;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
        }

        .page-header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .struk-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 15px;
            border: 2px solid #e0e0e0;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .struk-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            text-align: center;
        }

        .struk-body {
            padding: 30px;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px dashed #e0e0e0;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #555;
        }

        .info-value {
            color: #333;
            font-weight: 500;
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .countdown-box {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin: 25px 0;
            box-shadow: 0 5px 15px rgba(245, 87, 108, 0.3);
        }

        .countdown-timer {
            font-size: 2.5rem;
            font-weight: 700;
            font-family: 'Courier New', monospace;
            margin: 10px 0;
        }

        .action-buttons .btn {
            margin: 5px;
            min-width: 200px;
            padding: 12px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .riwayat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-top: 30px;
        }

        .riwayat-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
            transition: background-color 0.3s;
        }

        .riwayat-item:hover {
            background-color: #f8f9fa;
        }

        .mobil-photo {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 3px solid #e0e0e0;
        }

        .no-photo {
            width: 100%;
            height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 3rem;
        }

        .update-time {
            font-size: 0.8rem;
            color: #95a5a6;
        }

        .badge-menunggu {
            background-color: #6c757d;
            color: white;
        }

        .badge-dipinjam {
            background-color: #ffc107;
            color: #000;
        }

        .badge-lewat {
            background-color: #dc3545;
            color: white;
        }

        .badge-dikembalikan {
            background-color: #198754;
            color: white;
        }

        .badge-batal {
            background-color: #6c757d;
            color: white;
        }

        .badge-ditolak {
            background-color: #dc3545;
            color: white;
        }

        .auto-ditolak-alert {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                opacity: 1;
            }

            50% {
                opacity: 0.8;
            }

            100% {
                opacity: 1;
            }
        }

        .no-active-booking {
            background: linear-gradient(135deg, #f5f7fb 0%, #e3e7ed 100%);
            border-radius: 15px;
            border: 2px dashed #c0c0c0;
            padding: 60px 20px;
            text-align: center;
            margin-bottom: 30px;
        }

        .durasi-info {
            background-color: #e7f3ff;
            border-left: 4px solid #2196f3;
            padding: 10px 15px;
            margin: 15px 0;
            border-radius: 5px;
            font-size: 0.9rem;
        }

        .debug-info {
            background-color: #f8f9fa;
            border: 1px dashed #ccc;
            padding: 10px;
            margin: 10px 0;
            font-family: monospace;
            font-size: 0.8rem;
            color: #666;
            display: none;
            /* Sembunyikan di produksi */
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                min-height: auto;
            }

            .main-content {
                margin-left: 0;
            }

            .action-buttons .btn {
                width: 100%;
                margin-bottom: 10px;
            }
        }
    </style>
</head>

<body>
    <?php include "../includes/sidebar.php"; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-2">
                        <i class="fas fa-receipt me-2"></i> Struk Peminjaman Saya
                    </h1>
                    <p class="text-muted mb-0">Detail peminjaman aktif dan riwayat peminjaman (Format 24 Jam)</p>
                </div>
                <div class="update-time">
                    <i class="fas fa-clock me-1"></i> Waktu Server: <?= date('H:i:s') ?>
                    <br>
                    <i class="fas fa-sync-alt me-1"></i> Update: <?= $updated ?>
                </div>
            </div>
        </div>

        <?php if (isset($_GET['auto_reset'])) { ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fas fa-robot fa-2x me-3"></i>
                    <div>
                        <h5 class="alert-heading mb-1">Mobil Dikembalikan Otomatis</h5>
                        <p class="mb-0">Mobil telah dikembalikan otomatis oleh sistem karena melewati batas toleransi 15 menit.</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php } ?>

        <!-- Debug Info (Optional) -->
        <?php if ($peminjaman_saya && false): // Set true untuk debug 
        ?>
            <div class="debug-info">
                <strong>Debug Info:</strong><br>
                Tanggal: <?= $peminjaman_saya['tanggal_pinjam'] ?><br>
                Jam Mulai DB: <?= $jam_mulai_db ?><br>
                Jam Selesai DB: <?= $jam_selesai_db ?><br>
                Jam Mulai 24h: <?= $jam_mulai_24 ?><br>
                Jam Selesai 24h: <?= $jam_selesai_24 ?><br>
                Timestamp Now: <?= date('Y-m-d H:i:s', $now) ?><br>
                Durasi: <?= isset($durasi_jam) ? $durasi_jam . ' jam ' . $durasi_menit . ' menit' : 'N/A' ?>
            </div>
        <?php endif; ?>

        <?php if ($peminjaman_aktif && $peminjaman_saya && !$autoReset) { ?>
            <!-- HANYA TAMPILKAN STRUK JIKA ADA PEMINJAMAN AKTIF -->
            <div class="struk-card">
                <div class="struk-header">
                    <h3 class="mb-2">
                        <i class="fas fa-car me-2"></i>Struk Peminjaman Mobil
                    </h3>
                    <p class="mb-0">No. Transaksi: P<?= str_pad($peminjaman_saya['id'], 6, '0', STR_PAD_LEFT) ?></p>
                </div>

                <div class="struk-body">
                    <!-- Foto Mobil -->
                    <div class="text-center mb-4">
                        <?php if (!empty($peminjaman_saya['foto'])) { ?>
                            <img src="../uploads/<?= $peminjaman_saya['foto'] ?>" class="mobil-photo" alt="<?= htmlspecialchars($peminjaman_saya['nama_mobil']) ?>">
                        <?php } else { ?>
                            <div class="no-photo">
                                <i class="fas fa-car"></i>
                            </div>
                        <?php } ?>
                    </div>

                    <!-- Informasi Durasi -->
                    <?php if (isset($durasi_jam) && $durasi_jam > 0): ?>
                        <div class="durasi-info">
                            <i class="fas fa-clock me-2"></i>
                            <strong>Durasi Peminjaman:</strong> <?= $durasi_jam ?> jam <?= $durasi_menit ?> menit
                            (<?= date('H:i', strtotime($jam_mulai_24)) ?> - <?= date('H:i', strtotime($jam_selesai_24)) ?>)
                        </div>
                    <?php endif; ?>

                    <!-- Alert untuk Auto Ditolak -->
                    <?php if ($is_auto_ditolak) { ?>
                        <div class="auto-ditolak-alert">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-robot fa-2x me-3 text-warning"></i>
                                <div>
                                    <h5 class="alert-heading mb-1">Status Otomatis Ditolak</h5>
                                    <p class="mb-0">Peminjaman ini otomatis ditolak karena tidak di-ACC admin sebelum waktu selesai.</p>
                                    <small><i class="fas fa-clock me-1"></i> Waktu selesai: <?= date('H:i', $waktu_selesai) ?></small>
                                </div>
                            </div>
                        </div>
                    <?php } ?>

                    <!-- Info Peminjaman -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-item">
                                <span class="info-label">Nama Pegawai:</span>
                                <span class="info-value"><?= htmlspecialchars($user['nama']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Mobil:</span>
                                <span class="info-value"><?= htmlspecialchars($peminjaman_saya['nama_mobil']) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Plat Nomor:</span>
                                <span class="info-value badge bg-secondary fs-6"><?= htmlspecialchars($peminjaman_saya['nomor_plat']) ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-item">
                                <span class="info-label">Tanggal:</span>
                                <span class="info-value"><?= date('d/m/Y', strtotime($peminjaman_saya['tanggal_pinjam'])) ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Jam:</span>
                                <span class="info-value">
                                    <?php
                                    // Format jam untuk display (24 jam)
                                    $jam_mulai_display = date("H:i", strtotime($jam_mulai_24));
                                    $jam_selesai_display = date("H:i", strtotime($jam_selesai_24));
                                    echo $jam_mulai_display . " - " . $jam_selesai_display;
                                    ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Status:</span>
                                <span class="info-value">
                                    <?php if ($status === 'Ditolak') { ?>
                                        <span class="badge badge-ditolak status-badge">
                                            <i class="fas fa-ban me-1"></i> Ditolak <?= $is_auto_ditolak ? '(Otomatis)' : '' ?>
                                        </span>

                                    <?php } elseif ($status === 'Dibatalkan') { ?>
                                        <span class="badge badge-batal status-badge">
                                            <i class="fas fa-times me-1"></i> Dibatalkan
                                        </span>

                                    <?php } elseif ($status === 'Menunggu ACC') { ?>
                                        <span class="badge badge-menunggu status-badge">
                                            <i class="fas fa-clock me-1"></i> Menunggu ACC
                                        </span>

                                    <?php } elseif ($status === 'Lewat Batas') { ?>
                                        <span class="badge badge-lewat status-badge">
                                            <i class="fas fa-exclamation-triangle me-1"></i> Lewat Batas
                                        </span>

                                    <?php } elseif ($status === 'Dipinjam') { ?>
                                        <span class="badge badge-dipinjam status-badge">
                                            <i class="fas fa-car me-1"></i> Dipinjam
                                        </span>

                                    <?php } elseif ($status === 'Dikembalikan') { ?>
                                        <span class="badge badge-dikembalikan status-badge">
                                            <i class="fas fa-check-circle me-1"></i> Dikembalikan
                                        </span>

                                    <?php } elseif ($status === 'Menunggu Jadwal') { ?>
                                        <span class="badge badge-menunggu status-badge">
                                            <i class="fas fa-calendar me-1"></i> Menunggu Jadwal
                                        </span>

                                    <?php } else { ?>
                                        <span class="badge bg-secondary status-badge">
                                            <i class="fas fa-question-circle me-1"></i> Tidak diketahui
                                        </span>
                                    <?php } ?>

                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Keperluan -->
                    <div class="info-item">
                        <span class="info-label">Keperluan:</span>
                        <span class="info-value"><?= htmlspecialchars($peminjaman_saya['keperluan']) ?></span>
                    </div>

                    <!-- Countdown Timer -->
                    <?php if ($status === 'Dipinjam' || $status === 'Lewat Batas') { ?>
                        <div class="countdown-box">
                            <h5 class="mb-3">
                                <i class="fas fa-clock me-2"></i>
                                <?= $status === 'Lewat Batas' ? 'Waktu Telah Habis' : 'Sisa Waktu Peminjaman' ?>
                            </h5>
                            <div class="countdown-timer" id="countdownTimer">
                                <?php if ($status === 'Lewat Batas') { ?>
                                    <span class="text-danger">00:00:00</span>
                                <?php } ?>
                            </div>
                            <p class="mb-0">
                                <small>
                                    <i class="fas fa-info-circle me-1"></i>
                                    <?php if ($status === 'Lewat Batas') { ?>
                                        Melewati batas waktu. Toleransi 15 menit sebelum auto-return.
                                    <?php } else { ?>
                                        Toleransi pengembalian: 15 menit setelah jam selesai
                                    <?php } ?>
                                </small>
                            </p>
                        </div>
                    <?php } elseif ($status === 'Menunggu ACC') { ?>
                        <div class="alert alert-info">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-user-clock fa-2x me-3"></i>
                                <div>
                                    <h5 class="mb-1">Menunggu Persetujuan Admin</h5>
                                    <p class="mb-0">Pengajuan Anda sedang menunggu persetujuan dari admin. Silakan tunggu konfirmasi.</p>
                                    <?php if ($now > $waktu_selesai): ?>
                                        <div class="alert alert-warning mt-2 mb-0">
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            <strong>Perhatian:</strong> Jika tidak di-ACC sebelum <?= date('H:i', $waktu_selesai) ?>, status akan otomatis menjadi "Ditolak".
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php } elseif ($status === 'Menunggu Jadwal') { ?>
                        <div class="alert alert-warning">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-calendar-alt fa-2x me-3"></i>
                                <div>
                                    <h5 class="mb-1">Menunggu Jadwal Peminjaman</h5>
                                    <p class="mb-0">
                                        Peminjaman akan aktif pada:<br>
                                        <strong><?= date('d/m/Y', strtotime($peminjaman_saya['tanggal_pinjam'])) ?>
                                            <?= date('H:i', strtotime($jam_mulai_24)) ?> -
                                            <?= date('H:i', strtotime($jam_selesai_24)) ?></strong>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php } ?>

                    <!-- Action Buttons -->
                    <div class="action-buttons text-center mt-4">
                        <?php if ($status === 'Ditolak') { ?>
                            <!-- DITOLAK (Hanya jika masih aktif) -->
                            <?php if ($peminjaman_aktif): ?>
                                <a href="struk_pdf.php?id=<?= $peminjaman_saya['id']; ?>" target="_blank" class="btn btn-info">
                                    <i class="fas fa-print me-2"></i> Cetak PDF
                                </a>
                                <button class="btn btn-danger" disabled>
                                    <i class="fas fa-ban me-2"></i> <?= $is_auto_ditolak ? 'Ditolak (Otomatis)' : 'Ditolak Admin' ?>
                                </button>
                            <?php endif; ?>

                        <?php } elseif ($status === 'Dibatalkan') { ?>
                            <!-- DIBATALKAN (Hanya jika masih aktif) -->
                            <?php if ($peminjaman_aktif): ?>
                                <a href="struk_pdf.php?id=<?= $peminjaman_saya['id']; ?>" target="_blank" class="btn btn-info">
                                    <i class="fas fa-print me-2"></i> Cetak PDF
                                </a>
                                <button class="btn btn-secondary" disabled>
                                    <i class="fas fa-ban me-2"></i> Sudah Dibatalkan
                                </button>
                            <?php endif; ?>

                        <?php } elseif ($status === 'Dikembalikan') { ?>
                            <!-- DIKEMBALIKAN (Hanya jika masih aktif) -->
                            <?php if ($peminjaman_aktif): ?>
                                <a href="struk_pdf.php?id=<?= $peminjaman_saya['id']; ?>" target="_blank" class="btn btn-info">
                                    <i class="fas fa-print me-2"></i> Cetak PDF
                                </a>
                                <button class="btn btn-success" disabled>
                                    <i class="fas fa-check-circle me-2"></i> Selesai
                                </button>
                            <?php endif; ?>

                        <?php } elseif ($status === 'Menunggu Jadwal') { ?>
                            <!-- BELUM MULAI -->
                            <a href="struk_pdf.php?id=<?= $peminjaman_saya['id']; ?>" target="_blank" class="btn btn-info">
                                <i class="fas fa-print me-2"></i> Cetak PDF
                            </a>
                            <a href="../actions/cancel.php?id=<?= $peminjaman_saya['id']; ?>" class="btn btn-warning" onclick="return confirm('Yakin membatalkan peminjaman ini?')">
                                <i class="fas fa-times me-2"></i> Batalkan Peminjaman
                            </a>

                        <?php } elseif ($status === 'Dipinjam') { ?>
                            <!-- SUDAH MULAI -->
                            <a href="struk_pdf.php?id=<?= $peminjaman_saya['id']; ?>" target="_blank" class="btn btn-info">
                                <i class="fas fa-print me-2"></i> Cetak PDF
                            </a>
                            <a href="../actions/kembalikan.php?id=<?= $peminjaman_saya['id']; ?>" class="btn btn-danger" onclick="return confirm('Yakin mengembalikan mobil? Pastikan mobil dalam kondisi baik.')">
                                <i class="fas fa-car me-2"></i> Kembalikan Mobil
                            </a>

                        <?php } elseif ($status === 'Lewat Batas') { ?>
                            <!-- LEWAT BATAS -->
                            <a href="struk_pdf.php?id=<?= $peminjaman_saya['id']; ?>" target="_blank" class="btn btn-info">
                                <i class="fas fa-print me-2"></i> Cetak PDF
                            </a>
                            <a href="../actions/kembalikan.php?id=<?= $peminjaman_saya['id']; ?>" class="btn btn-danger" onclick="return confirm('Yakin mengembalikan mobil? Pastikan mobil dalam kondisi baik.')">
                                <i class="fas fa-exclamation-triangle me-2"></i> Kembalikan Mobil (Lewat)
                            </a>

                        <?php } elseif ($status === 'Menunggu ACC') { ?>
                            <!-- MENUNGGU ACC -->
                            <a href="struk_pdf.php?id=<?= $peminjaman_saya['id']; ?>" target="_blank" class="btn btn-info">
                                <i class="fas fa-print me-2"></i> Cetak PDF
                            </a>
                            <a href="../actions/cancel.php?id=<?= $peminjaman_saya['id']; ?>" class="btn btn-warning" onclick="return confirm('Yakin membatalkan pengajuan peminjaman ini?')">
                                <i class="fas fa-times me-2"></i> Batalkan Pengajuan
                            </a>

                            <?php if ($now > $waktu_selesai): ?>
                                <div class="alert alert-danger mt-3">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    <strong>Peringatan:</strong> Waktu peminjaman sudah lewat. Status akan otomatis menjadi "Ditolak".
                                </div>
                            <?php endif; ?>

                        <?php } ?>
                    </div>
                </div>
            </div>
        <?php } else { ?>
            <!-- TIDAK ADA PEMINJAMAN AKTIF -->
            <div class="no-active-booking">
                <div class="mb-4">
                    <i class="fas fa-car fa-5x text-muted mb-4"></i>
                    <h3 class="text-muted mb-3">Tidak Ada Peminjaman Aktif</h3>
                    <p class="text-muted mb-4">Anda tidak memiliki peminjaman mobil yang sedang berjalan saat ini.</p>
                    <div class="alert alert-info d-inline-block">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Info:</strong> Status peminjaman yang sudah selesai (Dikembalikan/Ditolak/Dibatalkan) tidak ditampilkan di sini.
                    </div>
                </div>

                <div class="action-buttons">
                    <a href="peminjaman.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-plus me-2"></i> Ajukan Peminjaman Baru
                    </a>
                    <a href="dashboard.php" class="btn btn-outline-primary btn-lg">
                        <i class="fas fa-tachometer-alt me-2"></i> Ke Dashboard
                    </a>
                    <a href="riwayat_pegawai.php" class="btn btn-outline-secondary btn-lg">
                        <i class="fas fa-history me-2"></i> Lihat Riwayat Lengkap
                    </a>
                </div>
            </div>
        <?php } ?>

        <!-- RIWAYAT PEMINJAMAN (Selalu ditampilkan) -->
        <div class="riwayat-card">
            <h5 class="mb-4">
                <i class="fas fa-history me-2"></i> Riwayat Peminjaman Terbaru
            </h5>

            <?php if (mysqli_num_rows($riwayat) > 0) { ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Tanggal</th>
                                <th>Mobil</th>
                                <th>Plat</th>
                                <th>Jam (24h)</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($r = mysqli_fetch_assoc($riwayat)) {
                                // Gunakan status_akhir jika ada, jika tidak gunakan status lama
                                $status_tampil = $r['status_akhir'] ?? $r['status'];

                                // Konversi waktu ke 24 jam untuk display
                                $jam_mulai_display = date("H:i", strtotime($r['jam_mulai']));
                                $jam_selesai_display = date("H:i", strtotime($r['jam_selesai']));

                                $status_class = '';
                                if ($status_tampil == 'Menunggu ACC' || $status_tampil == 'Menunggu Jadwal') $status_class = 'badge-menunggu';
                                elseif ($status_tampil == 'Dipinjam') $status_class = 'badge-dipinjam';
                                elseif ($status_tampil == 'Dikembalikan') $status_class = 'badge-dikembalikan';
                                elseif ($status_tampil == 'Dibatalkan') $status_class = 'badge-batal';
                                elseif ($status_tampil == 'Ditolak') $status_class = 'badge-ditolak';
                            ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($r['tanggal_pinjam'])) ?></td>
                                    <td><?= htmlspecialchars($r['nama_mobil']) ?></td>
                                    <td><?= htmlspecialchars($r['nomor_plat']) ?></td>
                                    <td><?= $jam_mulai_display ?> - <?= $jam_selesai_display ?></td>
                                    <td>
                                        <span class="badge <?= $status_class ?>">
                                            <?= $status_tampil ?>
                                            <?php if ($r['status'] == 'Menunggu ACC' && $status_tampil == 'Ditolak'): ?>
                                                <i class="fas fa-robot ms-1" title="Otomatis ditolak"></i>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="struk_pdf.php?id=<?= $r['id'] ?>" target="_blank" class="btn btn-sm btn-outline-info">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-3">
                    <a href="riwayat_pegawai.php" class="btn btn-outline-primary">
                        <i class="fas fa-list me-2"></i> Lihat Riwayat Lengkap
                    </a>
                </div>
            <?php } else { ?>
                <div class="text-center py-4">
                    <i class="fas fa-history fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Belum ada riwayat peminjaman</p>
                </div>
            <?php } ?>
        </div>

        <!-- Footer -->
        <footer class="mt-5 pt-3 text-center text-muted">
            <p class="mb-1">
                <small>Copyright &copy; Sistem Peminjaman Mobil Dinas <?= date('Y') ?></small>
            </p>
            <p class="mb-0">
                <small>Struk peminjaman | Update: <?= $updated ?></small>
            </p>
            <p class="mb-0">
                <small>Format waktu: 24 jam | Zona waktu: Asia/Makassar (WITA)</small>
            </p>
            <p class="mb-0">
                <small><i class="fas fa-robot me-1"></i> Peminjaman "Menunggu ACC" yang waktu selesainya lewat akan otomatis menjadi "Ditolak"</small>
            </p>
        </footer>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        <?php if ($peminjaman_aktif && $peminjaman_saya && ($status === 'Dipinjam' || $status === 'Lewat Batas')): ?>
            // COUNTDOWN TIMER - VERSI DIPERBAIKI
            const tanggalPinjam = "<?= $peminjaman_saya['tanggal_pinjam']; ?>";
            const jamMulai = "<?= $jam_mulai_24; ?>";
            const jamSelesai = "<?= $jam_selesai_24; ?>";

            console.log("=== TIMER DEBUG ===");
            console.log("Tanggal:", tanggalPinjam);
            console.log("Jam Mulai:", jamMulai);
            console.log("Jam Selesai:", jamSelesai);

            // Hitung durasi peminjaman untuk verifikasi
            const mulaiDate = new Date(tanggalPinjam + 'T' + jamMulai + '+08:00');
            const selesaiDate = new Date(tanggalPinjam + 'T' + jamSelesai + '+08:00');
            const durasiMs = selesaiDate.getTime() - mulaiDate.getTime();
            const durasiJam = Math.floor(durasiMs / (1000 * 60 * 60));
            const durasiMenit = Math.floor((durasiMs % (1000 * 60 * 60)) / (1000 * 60));

            console.log("Durasi Peminjaman:", durasiJam + " jam " + durasiMenit + " menit");

            if (durasiJam > 8) {
                console.warn("PERINGATAN: Durasi lebih dari 8 jam!");
            }

            // Timestamp untuk countdown (dalam milidetik)
            const selesaiTimestamp = selesaiDate.getTime();
            const batasToleransi = selesaiTimestamp + (15 * 60 * 1000); // 15 menit toleransi

            console.log("Selesai Timestamp:", new Date(selesaiTimestamp).toString());
            console.log("Batas Toleransi:", new Date(batasToleransi).toString());

            const countdownEl = document.getElementById("countdownTimer");

            function updateCountdown() {
                const now = new Date().getTime();
                const serverOffset = <?= $now * 1000 ?> - Date.now();
                const correctedNow = now + serverOffset;

                console.log("Now:", new Date(correctedNow).toString());

                // Hitung selisih dengan waktu selesai
                const diffSelesai = selesaiTimestamp - correctedNow;

                if (diffSelesai <= 0) {
                    // Sudah melewati waktu selesai
                    const diffToleransi = batasToleransi - correctedNow;

                    if (diffToleransi <= 0) {
                        // Sudah melewati batas toleransi
                        countdownEl.innerHTML = '<span class="text-danger fw-bold">00:00:00 - AUTO RETURN</span>';
                        document.title = "⏰ 00:00 - AUTO RETURN";

                        // Auto refresh setelah 3 detik
                        setTimeout(() => {
                            window.location.reload();
                        }, 3000);
                    } else {
                        // Masih dalam toleransi 15 menit
                        const m = Math.floor((diffToleransi % (1000 * 60 * 60)) / (1000 * 60));
                        const s = Math.floor((diffToleransi % (1000 * 60)) / 1000);

                        countdownEl.innerHTML = `<span class="text-warning fw-bold">+${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}</span>`;
                        document.title = `⚠️ +${m}:${s} - Struk`;
                    }
                } else {
                    // Masih dalam waktu peminjaman
                    const h = Math.floor(diffSelesai / (1000 * 60 * 60));
                    const m = Math.floor((diffSelesai % (1000 * 60 * 60)) / (1000 * 60));
                    const s = Math.floor((diffSelesai % (1000 * 60)) / 1000);

                    // Validasi: jika durasi > 8 jam, tampilkan peringatan
                    if (h > 8) {
                        countdownEl.innerHTML = '<span class="text-danger">ERROR: Durasi tidak valid</span>';
                        console.error("ERROR: Durasi countdown tidak wajar:", h + " jam");
                    } else {
                        countdownEl.innerHTML = `<span class="text-primary fw-bold">${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}</span>`;
                        document.title = `⏰ ${h}:${m}:${s} - Struk`;
                    }
                }
            }

            // Jalankan timer
            setInterval(updateCountdown, 1000);
            updateCountdown();

        <?php elseif ($peminjaman_aktif && $peminjaman_saya && $status === 'Menunggu ACC'): ?>
            // Timer untuk Menunggu ACC
            const waktuSelesaiMenunggu = <?= $waktu_selesai ?> * 1000;

            function updateMenungguACCTimer() {
                const now = new Date().getTime();
                const serverOffset = <?= $now * 1000 ?> - Date.now();
                const correctedNow = now + serverOffset;

                const diff = waktuSelesaiMenunggu - correctedNow;

                if (diff <= 0) {
                    window.location.reload();
                } else if (diff <= 5 * 60 * 1000) {
                    const minutes = Math.floor(diff / 60000);
                    document.title = `⏳ ${minutes}m - Struk Peminjaman`;
                } else {
                    document.title = "⏳ Menunggu ACC - Struk Peminjaman";
                }
            }

            setInterval(updateMenungguACCTimer, 30000);
            updateMenungguACCTimer();

        <?php elseif ($peminjaman_aktif && $peminjaman_saya && $status === 'Ditolak'): ?>
            document.title = "❌ Ditolak - Struk Peminjaman";
        <?php else: ?>
            document.title = "Struk Peminjaman - Tidak Ada Peminjaman Aktif";
        <?php endif; ?>

        // Auto refresh halaman setiap 60 detik untuk update status
        setTimeout(function() {
            location.reload();
        }, 60000);

        // Fungsi untuk menampilkan debug info (jika diperlukan)
        function toggleDebug() {
            const debugEl = document.querySelector('.debug-info');
            if (debugEl) {
                debugEl.style.display = debugEl.style.display === 'none' ? 'block' : 'none';
            }
        }

        // Untuk debugging: tekan Ctrl+Shift+D untuk menampilkan info debug
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.shiftKey && e.key === 'D') {
                toggleDebug();
                e.preventDefault();
            }
        });
    </script>
</body>

</html>