<?php
session_start();
include "../config/database.php";

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$user = $_SESSION['user'];
$user_id = (int)$user['id'];
$user_name = $user['nama'] ?? 'User';
$user_role = $user['role'] ?? 'pegawai';

$total_query = mysqli_query($conn, "
    SELECT COUNT(*) as total 
    FROM peminjaman 
    WHERE user_id = '$user_id'
");
$total_data = mysqli_fetch_assoc($total_query)['total'];

date_default_timezone_set('Asia/Makassar');

// Filter parameter
$filter_status = $_GET['status'] ?? '';
$filter_bulan = $_GET['bulan'] ?? '';
$filter_tahun = $_GET['tahun'] ?? '';

// Query dengan filter
$where_conditions = ["p.user_id = '$user_id'"];
$params = [];

if ($filter_status) {
    $where_conditions[] = "p.status = '$filter_status'";
    $params['status'] = $filter_status;
}

if ($filter_bulan && $filter_tahun) {
    $where_conditions[] = "YEAR(p.tanggal_pinjam) = '$filter_tahun' AND MONTH(p.tanggal_pinjam) = '$filter_bulan'";
    $params['bulan'] = $filter_bulan;
    $params['tahun'] = $filter_tahun;
} elseif ($filter_tahun) {
    $where_conditions[] = "YEAR(p.tanggal_pinjam) = '$filter_tahun'";
    $params['tahun'] = $filter_tahun;
} elseif ($filter_bulan) {
    $where_conditions[] = "MONTH(p.tanggal_pinjam) = '$filter_bulan'";
    $params['bulan'] = $filter_bulan;
}

$where_clause = implode(' AND ', $where_conditions);

// QUERY DURASI DAN STATUS OTOMATIS (TOLAK OTOMATIS JIKA LEWAT)
$riwayat = mysqli_query($conn, "
    SELECT 
        p.*,
        m.nama_mobil,
        m.nomor_plat,
        m.foto,
        -- TENTUKAN STATUS AKHIR (OTOMATIS TOLAK JIKA LEWAT)
        CASE 
            -- Jika status Menunggu ACC dan waktu selesai sudah lewat, otomatis Ditolak
            WHEN p.status = 'Menunggu ACC' 
                 AND CONCAT(p.tanggal_pinjam, ' ', p.jam_selesai) < NOW()
            THEN 'Ditolak'
            -- Status lainnya tetap
            ELSE p.status
        END as status_akhir,
        
        -- HITUNG DURASI BERDASARKAN STATUS (TANPA jam_kembali)
        CASE 
            -- STATUS DIPINJAM atau LEWAT BATAS: dari jam_mulai sampai sekarang
            WHEN p.status IN ('Dipinjam', 'Lewat Batas')
            THEN TIMESTAMPDIFF(
                MINUTE, 
                CONCAT(p.tanggal_pinjam, ' ', p.jam_mulai), 
                NOW()
            )
            
            -- STATUS DIKEMBALIKAN: dari jam_mulai sampai jam_selesai (karena tidak ada jam_kembali)
            WHEN p.status = 'Dikembalikan'
            THEN TIMESTAMPDIFF(
                MINUTE, 
                CONCAT(p.tanggal_pinjam, ' ', p.jam_mulai), 
                CONCAT(p.tanggal_pinjam, ' ', p.jam_selesai)
            )
            
            -- STATUS LAINNYA: durasi rencana dari jam_mulai sampai jam_selesai
            ELSE TIMESTAMPDIFF(
                MINUTE, 
                CONCAT(p.tanggal_pinjam, ' ', p.jam_mulai), 
                CONCAT(p.tanggal_pinjam, ' ', p.jam_selesai)
            )
        END as durasi_minutes
    FROM peminjaman p
    JOIN mobil m ON p.mobil_id = m.id
    WHERE $where_clause
    ORDER BY p.tanggal_pinjam DESC, p.jam_mulai DESC
");

// Reset pointer result set
mysqli_data_seek($riwayat, 0);

// Hitung statistik DENGAN LOGIKA STATUS OTOMATIS
$stats_query = mysqli_query($conn, "
    SELECT 
        CASE 
            WHEN p.status = 'Menunggu ACC' 
                 AND CONCAT(p.tanggal_pinjam, ' ', p.jam_selesai) < NOW()
            THEN 'Ditolak'
            ELSE p.status
        END as status_akhir,
        COUNT(*) as jumlah
    FROM peminjaman p
    WHERE p.user_id = '$user_id'
    GROUP BY status_akhir
    ORDER BY status_akhir
");

$stats = [];
while ($stat = mysqli_fetch_assoc($stats_query)) {
    $stats[$stat['status_akhir']] = $stat['jumlah'];
}

$updated = date('d/m/Y H:i');
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Peminjaman - Peminjaman Mobil Dinas</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">

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

        .filter-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .table-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
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

        .mobil-thumb {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #e0e0e0;
        }

        .no-photo-thumb {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px;
            font-size: 1.2rem;
        }

        .duration-badge {
            background-color: #e3f2fd;
            color: #1976d2;
            padding: 4px 8px;
            border-radius: 12px;
            font-weight: 500;
            font-size: 0.85rem;
            white-space: nowrap;
            display: inline-block;
        }

        .export-buttons .btn {
            margin-right: 5px;
            margin-bottom: 5px;
        }

        .stats-item {
            text-align: center;
            padding: 10px;
        }

        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stats-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .update-time {
            font-size: 0.8rem;
            color: #95a5a6;
        }

        .dataTables_wrapper {
            padding: 0;
        }

        .filter-active {
            background-color: #e7f1ff !important;
            border-left: 4px solid #0d6efd !important;
        }

        .auto-reject-info {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 10px 15px;
            margin-top: 15px;
            border-radius: 5px;
            font-size: 0.9rem;
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

            .export-buttons .btn {
                width: 100%;
                margin-right: 0;
            }
        }

        /* Print Styles */
        @media print {

            .sidebar,
            .filter-card,
            .stats-card,
            .export-buttons,
            .page-header,
            .update-time,
            .dataTables_filter,
            .dataTables_length,
            .dataTables_paginate {
                display: none !important;
            }

            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }

            .table-card {
                box-shadow: none !important;
                border: 1px solid #dee2e6 !important;
                padding: 10px !important;
            }

            body {
                background-color: white !important;
                font-size: 12pt !important;
            }

            h1,
            h2,
            h3,
            h4,
            h5,
            h6 {
                color: black !important;
            }

            table {
                font-size: 10pt !important;
            }

            footer {
                display: none !important;
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
                        <i class="fas fa-history me-2"></i> Riwayat Peminjaman Lengkap
                    </h1>
                    <p class="text-muted mb-0">Semua riwayat peminjaman mobil dinas Anda</p>
                </div>
                <div class="update-time">
                    <i class="fas fa-clock me-1"></i> Waktu Server: <?= date('H:i:s') ?>
                    <br>
                    <i class="fas fa-sync-alt me-1"></i> Update: <?= $updated ?>
                </div>
            </div>
        </div>

        <!-- Stats Card -->
        <div class="stats-card">
            <div class="row text-center">
                <div class="col-md-3 col-6 stats-item">
                    <div class="stats-number"><?= $total_data ?></div>
                    <div class="stats-label">Total Peminjaman Saya</div>
                </div>
                <div class="col-md-3 col-6 stats-item">
                    <div class="stats-number"><?= $stats['Dikembalikan'] ?? 0 ?></div>
                    <div class="stats-label">Selesai</div>
                </div>
                <div class="col-md-3 col-6 stats-item">
                    <div class="stats-number"><?= ($stats['Dipinjam'] ?? 0) + ($stats['Menunggu ACC'] ?? 0) + ($stats['Menunggu Jadwal'] ?? 0) ?></div>
                    <div class="stats-label">Aktif/Menunggu</div>
                </div>
                <div class="col-md-3 col-6 stats-item">
                    <div class="stats-number"><?= ($stats['Dibatalkan'] ?? 0) + ($stats['Ditolak'] ?? 0) ?></div>
                    <div class="stats-label">Dibatalkan/Ditolak</div>
                </div>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="filter-card <?= ($filter_status || $filter_bulan || $filter_tahun) ? 'filter-active' : '' ?>">
            <h5 class="mb-3">
                <i class="fas fa-filter me-2"></i> Filter Riwayat
                <?php if ($filter_status || $filter_bulan || $filter_tahun): ?>
                    <span class="badge bg-primary ms-2">Filter Aktif</span>
                <?php endif; ?>
            </h5>
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Semua Status</option>
                        <option value="Menunggu ACC" <?= $filter_status == 'Menunggu ACC' ? 'selected' : '' ?>>Menunggu ACC</option>
                        <option value="Menunggu Jadwal" <?= $filter_status == 'Menunggu Jadwal' ? 'selected' : '' ?>>Menunggu Jadwal</option>
                        <option value="Dipinjam" <?= $filter_status == 'Dipinjam' ? 'selected' : '' ?>>Dipinjam</option>
                        <option value="Lewat Batas" <?= $filter_status == 'Lewat Batas' ? 'selected' : '' ?>>Lewat Batas</option>
                        <option value="Dikembalikan" <?= $filter_status == 'Dikembalikan' ? 'selected' : '' ?>>Dikembalikan</option>
                        <option value="Dibatalkan" <?= $filter_status == 'Dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
                        <option value="Ditolak" <?= $filter_status == 'Ditolak' ? 'selected' : '' ?>>Ditolak</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Bulan</label>
                    <select name="bulan" class="form-select">
                        <option value="">Semua Bulan</option>
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?= $i ?>" <?= $filter_bulan == $i ? 'selected' : '' ?>>
                                <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tahun</label>
                    <select name="tahun" class="form-select">
                        <option value="">Semua Tahun</option>
                        <?php
                        $tahun_query = mysqli_query($conn, "
                            SELECT DISTINCT YEAR(tanggal_pinjam) as tahun 
                            FROM peminjaman 
                            WHERE user_id = '$user_id' 
                            ORDER BY tahun DESC
                        ");

                        while ($tahun = mysqli_fetch_assoc($tahun_query)):
                        ?>
                            <option value="<?= $tahun['tahun'] ?>" <?= $filter_tahun == $tahun['tahun'] ? 'selected' : '' ?>>
                                <?= $tahun['tahun'] ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="d-grid gap-2 w-100">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-2"></i> Terapkan Filter
                        </button>
                        <?php if ($filter_status || $filter_bulan || $filter_tahun): ?>
                            <a href="riwayat_pegawai.php" class="btn btn-outline-danger">
                                <i class="fas fa-times me-2"></i> Hapus Filter
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <!-- Info Filter Aktif -->
            <?php if ($filter_status || $filter_bulan || $filter_tahun): ?>
                <div class="mt-3 p-2 bg-light rounded">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i> Filter aktif:
                        <?php
                        $filters = [];
                        if ($filter_status) $filters[] = "Status: <strong>" . $filter_status . "</strong>";
                        if ($filter_bulan) $filters[] = "Bulan: <strong>" . date('F', mktime(0, 0, 0, $filter_bulan, 1)) . "</strong>";
                        if ($filter_tahun) $filters[] = "Tahun: <strong>" . $filter_tahun . "</strong>";
                        echo implode(" | ", $filters);
                        ?>
                    </small>
                </div>
            <?php endif; ?>

            <!-- Info Otomatis Ditolak -->
            <div class="auto-reject-info">
                <i class="fas fa-info-circle text-warning me-2"></i>
                <strong>Info:</strong> Peminjaman dengan status "Menunggu ACC" yang waktu selesainya sudah lewat akan otomatis berstatus "Ditolak".
            </div>
        </div>

        <!-- Info Jumlah Data -->
        <div class="alert alert-info d-flex justify-content-between align-items-center mb-3">
            <div>
                <i class="fas fa-database me-2"></i>
                <strong>Total <?= $total_data ?> peminjaman</strong> ditemukan
                <?php if (mysqli_num_rows($riwayat) != $total_data): ?>
                    <span class="ms-2">
                        (<?= mysqli_num_rows($riwayat) ?> data sesuai filter)
                    </span>
                <?php endif; ?>
            </div>
            <div class="export-buttons">
                <button class="btn btn-sm btn-success" onclick="exportToExcel()">
                    <i class="fas fa-file-excel me-1"></i> Excel
                </button>
                <a href="riwayat_pdf.php?<?= http_build_query(array_merge($_GET, ['cetak_pdf' => 1])) ?>" class="btn btn-sm btn-danger" target="_blank">
                    <i class="fas fa-print me-1"></i> Cetak PDF
                </a>
            </div>
        </div>

        <!-- Table Card -->
        <div class="table-card">
            <div class="table-responsive">
                <table id="riwayatTable" class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>No</th>
                            <th>Tanggal</th>
                            <th>Mobil</th>
                            <th>Plat</th>
                            <th>Jam</th>
                            <th>Durasi</th>
                            <th>Status</th>
                            <th>Keperluan/Tujuan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($riwayat) > 0): ?>
                            <?php
                            $no = 1;
                            mysqli_data_seek($riwayat, 0); // Reset pointer
                            while ($r = mysqli_fetch_assoc($riwayat)):
                                // Gunakan status_akhir jika ada, jika tidak gunakan status lama
                                $status_tampil = $r['status_akhir'] ?? $r['status'];

                                // FORMAT DURASI - SEDERHANA DAN JELAS
                                $durasi_text = '-';
                                $durasi_minutes = $r['durasi_minutes'] ?? 0;

                                if ($durasi_minutes > 0) {
                                    $hours = floor($durasi_minutes / 60);
                                    $minutes = $durasi_minutes % 60;

                                    if ($hours > 0) {
                                        $durasi_text = $hours . ' jam';
                                        if ($minutes > 0) {
                                            $durasi_text .= ' ' . $minutes . ' menit';
                                        }
                                    } else {
                                        $durasi_text = $minutes . ' menit';
                                    }

                                    // Tandai jika durasi rencana (untuk status menunggu)
                                    if ($status_tampil == 'Menunggu ACC' || $status_tampil == 'Menunggu Jadwal') {
                                        $durasi_text .= ' (rencana)';
                                    }
                                } elseif ($durasi_minutes === 0) {
                                    $durasi_text = '< 1 menit';
                                }

                                // Untuk status dibatalkan/ditolak, tampilkan durasi rencana
                                if (($status_tampil == 'Dibatalkan' || $status_tampil == 'Ditolak') && $durasi_minutes > 0) {
                                    $durasi_text .= ' (rencana)';
                                }

                                // Status class berdasarkan status_tampil
                                $status_class = '';
                                if ($status_tampil == 'Menunggu ACC') $status_class = 'badge-menunggu';
                                elseif ($status_tampil == 'Menunggu Jadwal') $status_class = 'badge-menunggu';
                                elseif ($status_tampil == 'Dipinjam') $status_class = 'badge-dipinjam';
                                elseif ($status_tampil == 'Dikembalikan') $status_class = 'badge-dikembalikan';
                                elseif ($status_tampil == 'Dibatalkan') $status_class = 'badge-batal';
                                elseif ($status_tampil == 'Ditolak') $status_class = 'badge-ditolak';
                                elseif ($status_tampil == 'Lewat Batas') $status_class = 'badge-lewat';
                            ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td>
                                        <strong><?= date('d/m/Y', strtotime($r['tanggal_pinjam'])) ?></strong>
                                        <?php if ($status_tampil == 'Dikembalikan' && $r['tanggal_kembali']): ?>
                                            <br><small class="text-muted">Kembali: <?= date('d/m/Y', strtotime($r['tanggal_kembali'])) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($r['foto'])): ?>
                                                <img src="../uploads/<?= $r['foto'] ?>" class="mobil-thumb me-2" alt="<?= htmlspecialchars($r['nama_mobil']) ?>">
                                            <?php else: ?>
                                                <div class="no-photo-thumb me-2">
                                                    <i class="fas fa-car"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <strong><?= htmlspecialchars($r['nama_mobil']) ?></strong><br>
                                                <small class="text-muted">ID: <?= $r['id'] ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($r['nomor_plat']) ?></span>
                                    </td>
                                    <td>
                                        <i class="fas fa-clock text-primary me-1"></i>
                                        <?= $r['jam_mulai'] ?> - <?= $r['jam_selesai'] ?>
                                        <?php if ($status_tampil == 'Ditolak' && $r['status'] == 'Menunggu ACC'): ?>
                                            <br><small class="text-danger"><i class="fas fa-exclamation-circle me-1"></i>Waktu habis, tidak di-ACC admin</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="duration-badge">
                                            <i class="fas fa-hourglass-half me-1"></i>
                                            <?= $durasi_text ?>
                                        </span>
                                        <?php if ($status_tampil == 'Dipinjam' || $status_tampil == 'Lewat Batas'): ?>
                                            <br><small class="text-warning">(masih berjalan)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $status_class ?>">
                                            <?php
                                            switch ($status_tampil) {
                                                case 'Menunggu ACC':
                                                    echo '<i class="fas fa-clock me-1"></i>';
                                                    break;
                                                case 'Menunggu Jadwal':
                                                    echo '<i class="fas fa-calendar me-1"></i>';
                                                    break;
                                                case 'Dipinjam':
                                                    echo '<i class="fas fa-car me-1"></i>';
                                                    break;
                                                case 'Dikembalikan':
                                                    echo '<i class="fas fa-check me-1"></i>';
                                                    break;
                                                case 'Dibatalkan':
                                                    echo '<i class="fas fa-times me-1"></i>';
                                                    break;
                                                case 'Ditolak':
                                                    echo '<i class="fas fa-ban me-1"></i>';
                                                    break;
                                                case 'Lewat Batas':
                                                    echo '<i class="fas fa-exclamation-triangle me-1"></i>';
                                                    break;
                                            }
                                            echo $status_tampil;
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="keperluan-text" style="max-width: 250px;" title="<?= htmlspecialchars($r['keperluan']) ?>">
                                            <?= mb_strlen($r['keperluan']) > 50 ? mb_substr($r['keperluan'], 0, 50) . '...' : htmlspecialchars($r['keperluan']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="struk_pdf.php?id=<?= $r['id'] ?>" target="_blank" class="btn btn-outline-info" title="Cetak Struk">
                                                <i class="fas fa-print"></i>
                                            </a>
                                            <?php if (($status_tampil == 'Menunggu ACC' || $status_tampil == 'Menunggu Jadwal') && !$r['tanggal_kembali']): ?>
                                                <a href="cancel.php?id=<?= $r['id'] ?>" class="btn btn-outline-warning" title="Batalkan" onclick="return confirm('Yakin membatalkan peminjaman ini?')">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-5">
                                    <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">Tidak ada data riwayat</h5>
                                    <p class="text-muted">Belum ada riwayat peminjaman<?= ($filter_status || $filter_bulan || $filter_tahun) ? ' dengan filter yang dipilih' : '' ?></p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Footer -->
        <footer class="mt-5 pt-3 text-center text-muted">
            <p class="mb-1">
                <small>Copyright &copy; Sistem Peminjaman Mobil Dinas <?= date('Y') ?></small>
            </p>
            <p class="mb-0">
                <small>Halaman riwayat peminjaman | Total data: <?= $total_data ?> | Update: <?= $updated ?></small>
            </p>
        </footer>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <!-- Excel Export -->
    <script src="https://cdn.jsdelivr.net/npm/exceljs/dist/exceljs.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/file-saver"></script>

    <script>
        // Initialize DataTable
        $(document).ready(function() {
            $('#riwayatTable').DataTable({
                "language": {
                    "lengthMenu": "Tampilkan _MENU_ data per halaman",
                    "zeroRecords": "Tidak ada data yang ditemukan",
                    "info": "Menampilkan halaman _PAGE_ dari _PAGES_",
                    "infoEmpty": "Tidak ada data tersedia",
                    "infoFiltered": "(disaring dari total _MAX_ data)",
                    "search": "Cari:",
                    "paginate": {
                        "first": "Pertama",
                        "last": "Terakhir",
                        "next": "Selanjutnya",
                        "previous": "Sebelumnya"
                    }
                },
                "order": [
                    [0, 'asc']
                ],
                "pageLength": 25,
                "responsive": true
            });
        });

        // Export to Excel
        function exportToExcel() {
            const table = document.getElementById('riwayatTable');
            const rows = table.querySelectorAll('tr');

            // Create workbook
            const workbook = new ExcelJS.Workbook();
            const worksheet = workbook.addWorksheet('Riwayat Peminjaman');

            // Add headers
            const headers = ['No', 'Tanggal', 'Mobil', 'Plat', 'Jam', 'Durasi', 'Status', 'Keperluan/Tujuan'];
            worksheet.addRow(headers);

            // Add data
            table.querySelectorAll('tbody tr').forEach(row => {
                const rowData = [];
                const cells = row.querySelectorAll('td');
                for (let i = 0; i < cells.length - 1; i++) {
                    const text = cells[i].textContent.replace(/\s+/g, ' ').trim();
                    rowData.push(text);
                }
                worksheet.addRow(rowData);
            });

            // Style headers
            const headerRow = worksheet.getRow(1);
            headerRow.font = {
                bold: true
            };
            headerRow.fill = {
                type: 'pattern',
                pattern: 'solid',
                fgColor: {
                    argb: 'FFE0E0E0'
                }
            };

            // Auto fit columns
            worksheet.columns.forEach(column => {
                let maxLength = 0;
                column.eachCell({
                    includeEmpty: true
                }, cell => {
                    const columnLength = cell.value ? cell.value.toString().length : 10;
                    if (columnLength > maxLength) {
                        maxLength = columnLength;
                    }
                });
                column.width = Math.min(maxLength + 2, 50);
            });

            // Save file
            workbook.xlsx.writeBuffer().then(buffer => {
                const blob = new Blob([buffer], {
                    type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                });
                saveAs(blob, 'Riwayat_Peminjaman_<?= preg_replace('/[^a-z0-9]/i', '_', $user_name) ?>_<?= date('Y-m-d') ?>.xlsx');
            });
        }

        // Auto refresh setiap 2 menit untuk update status
        setTimeout(function() {
            location.reload();
        }, 120000);
    </script>
</body>

</html>