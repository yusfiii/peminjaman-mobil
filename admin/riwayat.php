<?php
session_start();
include "../config/database.php";

// Proteksi admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$user = $_SESSION['user'];
$user_name = $user['nama'] ?? 'Admin';
$user_role = $user['role'] ?? 'admin';

$now = time(); // Waktu server saat ini
date_default_timezone_set('Asia/Makassar'); // Sesuaikan dengan zona waktu lokal

// Hitung total peminjaman SEMUA PEGAWAI
$total_query = mysqli_query($conn, "
    SELECT COUNT(*) as total 
    FROM peminjaman 
");
$total_data = mysqli_fetch_assoc($total_query)['total'];

// Filter parameter
$filter_status = $_GET['status'] ?? '';
$filter_bulan = $_GET['bulan'] ?? '';
$filter_tahun = $_GET['tahun'] ?? '';
$filter_pegawai = $_GET['pegawai'] ?? '';

// Query dengan filter
$where_conditions = ["1=1"]; // Kondisi awal selalu true
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

if ($filter_pegawai) {
    $where_conditions[] = "u.id = '$filter_pegawai'";
    $params['pegawai'] = $filter_pegawai;
}

$where_clause = implode(' AND ', $where_conditions);

// QUERY DURASI YANG BENAR UNTUK ADMIN
$riwayat = mysqli_query($conn, "
    SELECT 
        p.*,
        m.nama_mobil,
        m.nomor_plat,
        m.foto,
        u.nama as nama_pegawai,
        u.nip,
        u.jabatan,
        b.nama_bidang,
        s.nama_seksi,
        -- HITUNG DURASI YANG BENAR
        CASE 
            -- STATUS DIPINJAM atau LEWAT BATAS: dari jam_mulai sampai sekarang
            WHEN p.status IN ('Dipinjam', 'Lewat Batas')
            THEN TIMESTAMPDIFF(
                MINUTE, 
                CONCAT(p.tanggal_pinjam, ' ', p.jam_mulai), 
                NOW()
            )
            
            -- STATUS DIKEMBALIKAN: dari jam_mulai sampai jam_selesai
            WHEN p.status = 'Dikembalikan'
            THEN TIMESTAMPDIFF(
                MINUTE, 
                CONCAT(p.tanggal_pinjam, ' ', p.jam_mulai), 
                CONCAT(p.tanggal_pinjam, ' ', p.jam_selesai)
            )
            
            -- STATUS LAINNYA (Menunggu, Dibatalkan): durasi rencana dari jam_mulai sampai jam_selesai
            ELSE TIMESTAMPDIFF(
                MINUTE, 
                CONCAT(p.tanggal_pinjam, ' ', p.jam_mulai), 
                CONCAT(p.tanggal_pinjam, ' ', p.jam_selesai)
            )
        END as durasi_minutes
    FROM peminjaman p
    JOIN mobil m ON p.mobil_id = m.id
    JOIN users u ON p.user_id = u.id
    LEFT JOIN bidang b ON u.bidang_id = b.id
    LEFT JOIN seksi s ON u.seksi_id = s.id
    WHERE $where_clause
    ORDER BY p.tanggal_pinjam DESC, p.jam_mulai DESC
");

// Reset pointer result set
mysqli_data_seek($riwayat, 0);

// Hitung statistik SEMUA PEGAWAI
$stats_query = mysqli_query($conn, "
    SELECT 
        p.status,
        COUNT(*) as jumlah
    FROM peminjaman p
    GROUP BY p.status
    ORDER BY p.status
");

$stats = [];
while ($stat = mysqli_fetch_assoc($stats_query)) {
    $stats[$stat['status']] = $stat['jumlah'];
}

// Ambil data pegawai untuk filter dropdown
$pegawai_list = mysqli_query($conn, "
    SELECT u.id, u.nama, u.nip, b.nama_bidang
    FROM users u
    LEFT JOIN bidang b ON u.bidang_id = b.id
    WHERE u.role = 'pegawai'
    ORDER BY u.nama ASC
");

$updated = date('d/m/Y H:i');
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Peminjaman Semua Pegawai - Peminjaman Mobil Dinas</title>

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
            background-color: #e74c3c;
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
            background-color: #e74c3c;
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

        .pegawai-info {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            margin-top: 5px;
            border-left: 4px solid #3498db;
        }

        .pegawai-info small {
            display: block;
            margin-bottom: 2px;
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
    </style>
</head>

<body>
    <?php include "../includes/sidebar.php"; ?>
    <!-- Sidebar -->
    <!-- <div class="sidebar d-flex flex-column">
        <div class="user-info">
            <div class="user-avatar">
                <i class="fas fa-user-shield"></i>
            </div>
            <h6 class="mb-1"><?= htmlspecialchars($user_name) ?></h6>
            <span class="badge bg-danger">
                Administrator
            </span>
        </div>

        <div class="flex-grow-1">
            <?php
            $current = basename($_SERVER['PHP_SELF']);
            ?>

            <a href="admin-dashboard.php" class="nav-link <?= $current == 'admin-dashboard.php' ? '' : '' ?>">
                <i class="fas fa-tachometer-alt me-2"></i> Dashboard Admin
            </a>

            <a href="kelola-mobil.php" class="nav-link <?= $current == 'kelola-mobil.php' ? '' : '' ?>">
                <i class="fas fa-car me-2"></i> Kelola Mobil
            </a>

            <a href="kelola-pegawai.php" class="nav-link <?= $current == 'kelola-pegawai.php' ? '' : '' ?>">
                <i class="fas fa-users me-2"></i> Kelola Pegawai
            </a>

            <a href="riwayat.php" class="nav-link <?= $current == 'riwayat.php' ? 'active' : '' ?>">
                <i class="fas fa-history me-2"></i> Riwayat Semua
            </a>

            <a href="laporan.php" class="nav-link <?= $current == 'laporan.php' ? '' : '' ?>">
                <i class="fas fa-chart-bar me-2"></i> Laporan
            </a>
        </div>

        <a href="../logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt me-2"></i> Logout
        </a>

        <div class="copyright">
            &copy; <?= date('Y') ?> Sistem Peminjaman Mobil Dinas
        </div>
    </div> -->

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-2">
                        <i class="fas fa-history me-2"></i> Riwayat Peminjaman Semua Pegawai
                    </h1>
                    <p class="text-muted mb-0">Semua riwayat peminjaman mobil dinas dari semua pegawai</p>
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
                    <div class="stats-label">Total Peminjaman</div>
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
                    <div class="stats-number"><?= ($stats['Ditolak'] ?? 0) + ($stats['Dibatalkan'] ?? 0) ?></div>
                    <div class="stats-label">Ditolak</div>
                </div>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="filter-card <?= ($filter_status || $filter_bulan || $filter_tahun || $filter_pegawai) ? 'filter-active' : '' ?>">
            <h5 class="mb-3">
                <i class="fas fa-filter me-2"></i> Filter Riwayat
                <?php if ($filter_status || $filter_bulan || $filter_tahun || $filter_pegawai): ?>
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
                        // Ambil tahun unik dari database SEMUA PEGAWAI
                        $tahun_query = mysqli_query($conn, "
                            SELECT DISTINCT YEAR(tanggal_pinjam) as tahun 
                            FROM peminjaman 
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
                <div class="col-md-3">
                    <label class="form-label">Pegawai</label>
                    <select name="pegawai" class="form-select">
                        <option value="">Semua Pegawai</option>
                        <?php
                        // Reset pointer pegawai_list
                        mysqli_data_seek($pegawai_list, 0);
                        while ($pegawai = mysqli_fetch_assoc($pegawai_list)): ?>
                            <option value="<?= $pegawai['id'] ?>" <?= $filter_pegawai == $pegawai['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pegawai['nama']) ?> - <?= htmlspecialchars($pegawai['nip']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-12 mt-2">
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search me-2"></i> Terapkan Filter
                        </button>
                        <?php if ($filter_status || $filter_bulan || $filter_tahun || $filter_pegawai): ?>
                            <a href="riwayat.php" class="btn btn-outline-danger">
                                <i class="fas fa-times me-2"></i> Hapus Filter
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <!-- Info Filter Aktif -->
            <?php if ($filter_status || $filter_bulan || $filter_tahun || $filter_pegawai): ?>
                <div class="mt-3 p-2 bg-light rounded">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i> Filter aktif:
                        <?php
                        $filters = [];
                        if ($filter_status) $filters[] = "Status: <strong>" . $filter_status . "</strong>";
                        if ($filter_bulan) $filters[] = "Bulan: <strong>" . date('F', mktime(0, 0, 0, $filter_bulan, 1)) . "</strong>";
                        if ($filter_tahun) $filters[] = "Tahun: <strong>" . $filter_tahun . "</strong>";
                        if ($filter_pegawai) {
                            // Ambil nama pegawai yang difilter
                            $pegawai_nama_query = mysqli_query($conn, "SELECT nama, nip FROM users WHERE id = '$filter_pegawai'");
                            if ($pegawai_nama_query && mysqli_num_rows($pegawai_nama_query) > 0) {
                                $pegawai_nama = mysqli_fetch_assoc($pegawai_nama_query);
                                $filters[] = "Pegawai: <strong>" . $pegawai_nama['nama'] . " (NIP: " . $pegawai_nama['nip'] . ")</strong>";
                            }
                        }
                        echo implode(" | ", $filters);
                        ?>
                    </small>
                </div>
            <?php endif; ?>
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
                <a href="cetak_pdf_admin.php?<?= http_build_query([
                                                    'status' => $filter_status,
                                                    'bulan' => $filter_bulan,
                                                    'tahun' => $filter_tahun,
                                                    'pegawai' => $filter_pegawai
                                                ]) ?>" target="_blank" class="btn btn-sm btn-danger">
                    <i class="fas fa-file-pdf me-1"></i> PDF
                </a>
                <a href="laporan.php" class="btn btn-sm btn-warning">
                    <i class="fas fa-chart-bar me-1"></i> Laporan
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
                            <th>Pegawai</th>
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
                            mysqli_data_seek($riwayat, 0);
                            while ($r = mysqli_fetch_assoc($riwayat)):
                                // FORMAT DURASI YANG BENAR
                                $durasi_text = '-';
                                $durasi_minutes = $r['durasi_minutes'] ?? 0;

                                // DEBUG: Untuk troubleshooting
                                // echo "<!-- DEBUG: ID=" . $r['id'] . 
                                //      ", Status=" . $r['status'] . 
                                //      ", jam_mulai=" . $r['jam_mulai'] . 
                                //      ", jam_selesai=" . $r['jam_selesai'] . 
                                //      ", durasi_minutes=" . $durasi_minutes . " -->";

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
                                    if ($r['status'] == 'Menunggu ACC' || $r['status'] == 'Menunggu Jadwal') {
                                        $durasi_text .= ' (rencana)';
                                    }
                                } elseif ($durasi_minutes === 0) {
                                    $durasi_text = '< 1 menit';
                                }

                                // Untuk status dibatalkan, tampilkan durasi rencana
                                if ($r['status'] == 'Dibatalkan' && $durasi_minutes > 0) {
                                    $durasi_text .= ' (rencana)';
                                }

                                // Status class
                                $status_class = '';
                                if ($r['status'] == 'Menunggu ACC') $status_class = 'badge-menunggu';
                                elseif ($r['status'] == 'Menunggu Jadwal') $status_class = 'badge-menunggu';
                                elseif ($r['status'] == 'Dipinjam') $status_class = 'badge-dipinjam';
                                elseif ($r['status'] == 'Dikembalikan') $status_class = 'badge-dikembalikan';
                                elseif ($r['status'] == 'Dibatalkan') $status_class = 'badge-batal';
                                elseif ($r['status'] == 'Ditolak') $status_class = 'badge-batal';
                                elseif ($r['status'] == 'Lewat Batas') $status_class = 'badge-lewat';
                            ?>
                                <tr>
                                    <td></td>
                                    <td data-order="<?= date('Y-m-d', strtotime($r['tanggal_pinjam'])) ?>">
                                        <strong><?= date('d/m/Y', strtotime($r['tanggal_pinjam'])) ?></strong>
                                        <?php if ($r['tanggal_kembali']): ?>
                                            <br><small class="text-muted">Kembali: <?= date('d/m/Y', strtotime($r['tanggal_kembali'])) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="pegawai-info">
                                            <strong><?= htmlspecialchars($r['nama_pegawai']) ?></strong>
                                            <small>NIP: <?= htmlspecialchars($r['nip']) ?></small>
                                            <small>Jabatan: <?= htmlspecialchars($r['jabatan']) ?: '-' ?></small>
                                            <?php if ($r['nama_bidang']): ?>
                                                <small>Bidang: <?= htmlspecialchars($r['nama_bidang']) ?></small>
                                            <?php endif; ?>
                                            <?php if ($r['nama_seksi']): ?>
                                                <small>Seksi: <?= htmlspecialchars($r['nama_seksi']) ?></small>
                                            <?php endif; ?>
                                        </div>
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
                                        <?php if ($r['status'] == 'Dipinjam' || $r['status'] == 'Lewat Batas'): ?>
                                            <br><small class="text-warning">(masih berjalan)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="duration-badge">
                                            <i class="fas fa-hourglass-half me-1"></i>
                                            <?= $durasi_text ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?= $status_class ?>">
                                            <?php
                                            switch ($r['status']) {
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
                                            echo $r['status'];
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
                                            <?php if ($r['status'] == 'Dipinjam' || $r['status'] == 'Lewat Batas'): ?>
                                                <a href="kembalikan.php?id=<?= $r['id'] ?>" class="btn btn-outline-success" title="Tandai Kembali" onclick="return confirm('Tandai peminjaman ini sebagai dikembalikan?')">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($r['status'] == 'Menunggu ACC' || $r['status'] == 'Menunggu Jadwal'): ?>
                                                <a href="terima_peminjaman.php?id=<?= $r['id'] ?>&action=terima" class="btn btn-outline-primary" title="Terima" onclick="return confirm('Terima peminjaman ini?')">
                                                    <i class="fas fa-check-circle"></i>
                                                </a>
                                                <a href="terima_peminjaman.php?id=<?= $r['id'] ?>&action=tolak" class="btn btn-outline-danger" title="Tolak" onclick="return confirm('Tolak peminjaman ini?')">
                                                    <i class="fas fa-times-circle"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center py-5">
                                    <i class="fas fa-history fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">Tidak ada data riwayat</h5>
                                    <p class="text-muted">Belum ada riwayat peminjaman<?= ($filter_status || $filter_bulan || $filter_tahun || $filter_pegawai) ? ' dengan filter yang dipilih' : '' ?></p>
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
                <small>Halaman riwayat peminjaman semua pegawai | Total data: <?= $total_data ?> | Update: <?= $updated ?></small>
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
            // Inisialisasi DataTables TANPA sorting di kolom No
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
                    [1, 'desc']
                ], // Urutkan berdasarkan tanggal
                "pageLength": 25,
                "responsive": true,
                "columnDefs": [{
                    "targets": 0, // Kolom No
                    "orderable": false,
                    "searchable": false
                }]
            });

            // Tambahkan nomor urut setelah DataTables selesai load
            setTimeout(function() {
                renumberTable();
            }, 100);

            // Fungsi untuk memberi nomor urut
            function renumberTable() {
                $('#riwayatTable tbody tr').each(function(index) {
                    $(this).find('td:first').text(index + 1);
                });
            }

            // Renumber saat pagination berubah
            $('#riwayatTable').on('draw.dt', function() {
                renumberTable();
            });
        });

        // Export to Excel
        function exportToExcel() {
            const table = document.getElementById('riwayatTable');
            const rows = table.querySelectorAll('tr');

            // Create workbook
            const workbook = new ExcelJS.Workbook();
            const worksheet = workbook.addWorksheet('Riwayat Semua Pegawai');

            // Add headers
            const headers = ['No', 'Tanggal', 'Nama Pegawai', 'NIP', 'Jabatan', 'Bidang', 'Seksi', 'Mobil', 'Plat', 'Jam Mulai', 'Jam Selesai', 'Durasi', 'Status', 'Keperluan/Tujuan'];
            worksheet.addRow(headers);

            // Add data
            table.querySelectorAll('tbody tr').forEach(row => {
                const rowData = [];
                const cells = row.querySelectorAll('td');

                // Kolom 1: No
                rowData.push(cells[0].textContent.trim());

                // Kolom 2: Tanggal
                rowData.push(cells[1].querySelector('strong').textContent.trim());

                // Kolom 3-7: Data Pegawai
                const pegawaiInfo = cells[2].querySelector('.pegawai-info');
                rowData.push(pegawaiInfo.querySelector('strong').textContent.trim());
                rowData.push(pegawaiInfo.querySelectorAll('small')[0].textContent.replace('NIP: ', '').trim());
                rowData.push(pegawaiInfo.querySelectorAll('small')[1].textContent.replace('Jabatan: ', '').trim());
                rowData.push(pegawaiInfo.querySelectorAll('small')[2] ? pegawaiInfo.querySelectorAll('small')[2].textContent.replace('Bidang: ', '').trim() : '');
                rowData.push(pegawaiInfo.querySelectorAll('small')[3] ? pegawaiInfo.querySelectorAll('small')[3].textContent.replace('Seksi: ', '').trim() : '');

                // Kolom 8: Mobil
                rowData.push(cells[3].querySelector('strong').textContent.trim());

                // Kolom 9: Plat
                rowData.push(cells[4].textContent.trim());

                // Kolom 10-11: Jam
                const jamText = cells[5].textContent.trim();
                const jamParts = jamText.split(' - ');
                rowData.push(jamParts[0]);
                rowData.push(jamParts[1]);

                // Kolom 12: Durasi
                rowData.push(cells[6].textContent.trim());

                // Kolom 13: Status
                rowData.push(cells[7].textContent.trim());

                // Kolom 14: Keperluan
                rowData.push(cells[8].title || cells[8].textContent.trim());

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
                saveAs(blob, 'Riwayat_Peminjaman_Semua_Pegawai_<?= date('Y-m-d') ?>.xlsx');
            });
        }

        // Auto refresh setiap 2 menit untuk update status
        setTimeout(function() {
            location.reload();
        }, 120000);

        // Print style
        window.addEventListener('beforeprint', function() {
            document.querySelector('.sidebar').style.display = 'none';
            document.querySelector('.main-content').style.marginLeft = '0';
            document.querySelector('.filter-card').style.display = 'none';
            document.querySelector('.export-buttons').style.display = 'none';
        });

        window.addEventListener('afterprint', function() {
            document.querySelector('.sidebar').style.display = 'flex';
            document.querySelector('.main-content').style.marginLeft = '250px';
            document.querySelector('.filter-card').style.display = 'block';
            document.querySelector('.export-buttons').style.display = 'block';
        });
    </script>
</body>

</html>