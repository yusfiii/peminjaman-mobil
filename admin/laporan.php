<?php
session_start();
include "../config/database.php";
include '../includes/sidebar.php';

// Proteksi admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$user = $_SESSION['user'];
$user_name = $user['nama'] ?? 'Admin';

$now = time(); // Waktu server saat ini
date_default_timezone_set('Asia/Makassar'); // Sesuaikan dengan zona waktu lokal

// SET DEFAULT PERIODE (Semua bulan dan tahun)
$current_month = date('m');
$current_year = date('Y');

// FILTER PERIODE
$filter_month = $_GET['bulan'] ?? 'all';
$filter_year = $_GET['tahun'] ?? 'all';
$filter_type = $_GET['jenis'] ?? 'overview'; // overview, peminjaman, mobil, pegawai, armada, status, distribusi, durasi

// Build WHERE clause based on filters
$where_clause = "";
if ($filter_month !== 'all' && $filter_year !== 'all') {
    $where_clause = "WHERE MONTH(tanggal_pinjam) = '$filter_month' AND YEAR(tanggal_pinjam) = '$filter_year'";
} elseif ($filter_month !== 'all') {
    $where_clause = "WHERE MONTH(tanggal_pinjam) = '$filter_month'";
} elseif ($filter_year !== 'all') {
    $where_clause = "WHERE YEAR(tanggal_pinjam) = '$filter_year'";
}

// QUERY STATISTIK UTAMA
// 1. Total Peminjaman (sesuai filter)
$total_peminjaman_query = "SELECT COUNT(*) as total FROM peminjaman";
if ($where_clause) {
    $total_peminjaman_query .= " " . $where_clause;
}
$total_peminjaman = mysqli_fetch_assoc(mysqli_query($conn, $total_peminjaman_query));

// 2. Mobil Paling Sering Dipinjam
$mobil_populer_query = "
    SELECT m.nama_mobil, m.nomor_plat, COUNT(p.id) as jumlah
    FROM peminjaman p
    JOIN mobil m ON p.mobil_id = m.id";

if ($filter_month !== 'all' && $filter_year !== 'all') {
    $mobil_populer_query .= " WHERE MONTH(p.tanggal_pinjam) = '$filter_month' AND YEAR(p.tanggal_pinjam) = '$filter_year'";
} elseif ($filter_month !== 'all') {
    $mobil_populer_query .= " WHERE MONTH(p.tanggal_pinjam) = '$filter_month'";
} elseif ($filter_year !== 'all') {
    $mobil_populer_query .= " WHERE YEAR(p.tanggal_pinjam) = '$filter_year'";
}

$mobil_populer_query .= " GROUP BY p.mobil_id ORDER BY jumlah DESC LIMIT 5";
$mobil_populer = mysqli_query($conn, $mobil_populer_query);

// 3. Pegawai Paling Aktif
$pegawai_aktif_query = "
    SELECT u.nama, u.nip, b.nama_bidang, COUNT(p.id) as jumlah
    FROM peminjaman p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN bidang b ON u.bidang_id = b.id";

if ($filter_month !== 'all' && $filter_year !== 'all') {
    $pegawai_aktif_query .= " WHERE MONTH(p.tanggal_pinjam) = '$filter_month' AND YEAR(p.tanggal_pinjam) = '$filter_year'";
} elseif ($filter_month !== 'all') {
    $pegawai_aktif_query .= " WHERE MONTH(p.tanggal_pinjam) = '$filter_month'";
} elseif ($filter_year !== 'all') {
    $pegawai_aktif_query .= " WHERE YEAR(p.tanggal_pinjam) = '$filter_year'";
}

$pegawai_aktif_query .= " GROUP BY p.user_id ORDER BY jumlah DESC LIMIT 5";
$pegawai_aktif = mysqli_query($conn, $pegawai_aktif_query);

// 4. Statistik Per Status
$status_stats_query = "SELECT status, COUNT(*) as jumlah FROM peminjaman";
if ($where_clause) {
    $status_stats_query .= " " . $where_clause;
}
$status_stats_query .= " GROUP BY status";
$status_stats = mysqli_query($conn, $status_stats_query);

// 5. Grafik Peminjaman Per Bulan (12 bulan terakhir)
$chart_data = mysqli_query($conn, "
    SELECT 
        DATE_FORMAT(tanggal_pinjam, '%Y-%m') as bulan,
        COUNT(*) as jumlah
    FROM peminjaman
    WHERE tanggal_pinjam >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(tanggal_pinjam, '%Y-%m')
    ORDER BY bulan ASC
");

// 6. Utilisasi Mobil
$utilisasi_mobil_query = "
    SELECT 
        m.nama_mobil,
        m.nomor_plat,
        COUNT(p.id) as total_peminjaman,
        SUM(
            CASE 
                WHEN p.jam_mulai IS NOT NULL AND p.jam_selesai IS NOT NULL 
                THEN TIMESTAMPDIFF(HOUR, p.jam_mulai, p.jam_selesai)
                ELSE 0
            END
        ) as total_jam
    FROM mobil m
    LEFT JOIN peminjaman p ON m.id = p.mobil_id";

if ($filter_month !== 'all' && $filter_year !== 'all') {
    $utilisasi_mobil_query .= " AND MONTH(p.tanggal_pinjam) = '$filter_month' AND YEAR(p.tanggal_pinjam) = '$filter_year'";
} elseif ($filter_month !== 'all') {
    $utilisasi_mobil_query .= " AND MONTH(p.tanggal_pinjam) = '$filter_month'";
} elseif ($filter_year !== 'all') {
    $utilisasi_mobil_query .= " AND YEAR(p.tanggal_pinjam) = '$filter_year'";
}

$utilisasi_mobil_query .= " GROUP BY m.id ORDER BY total_jam DESC";
$utilisasi_mobil = mysqli_query($conn, $utilisasi_mobil_query);

// 7. Keterlambatan
$keterlambatan_query = "
    SELECT 
        p.id,
        p.tanggal_pinjam,
        p.jam_selesai,
        p.tanggal_kembali,
        u.nama as nama_pegawai,
        u.nip,
        m.nama_mobil,
        m.nomor_plat,
        TIMESTAMPDIFF(HOUR, CONCAT(p.tanggal_pinjam, ' ', p.jam_selesai), p.tanggal_kembali) as keterlambatan_jam
    FROM peminjaman p
    JOIN users u ON p.user_id = u.id
    JOIN mobil m ON p.mobil_id = m.id
    WHERE p.status IN ('Lewat Batas', 'Dikembalikan')
    AND p.tanggal_kembali > CONCAT(p.tanggal_pinjam, ' ', p.jam_selesai)";

if ($filter_month !== 'all' && $filter_year !== 'all') {
    $keterlambatan_query .= " AND MONTH(p.tanggal_pinjam) = '$filter_month' AND YEAR(p.tanggal_pinjam) = '$filter_year'";
} elseif ($filter_month !== 'all') {
    $keterlambatan_query .= " AND MONTH(p.tanggal_pinjam) = '$filter_month'";
} elseif ($filter_year !== 'all') {
    $keterlambatan_query .= " AND YEAR(p.tanggal_pinjam) = '$filter_year'";
}

$keterlambatan_query .= " ORDER BY keterlambatan_jam DESC LIMIT 10";
$keterlambatan = mysqli_query($conn, $keterlambatan_query);

// ============================
// QUERY LAPORAN BARU YANG DIMINTA
// ============================

// 8. Laporan Statistik Frekuensi Armada (Mobil mana yang paling sering dipakai)
$frekuensi_armada_query = "
    SELECT 
        m.nama_mobil,
        m.nomor_plat,
        m.status as status_mobil,
        COUNT(p.id) as total_peminjaman,
        SUM(
            CASE 
                WHEN p.jam_mulai IS NOT NULL AND p.jam_selesai IS NOT NULL 
                THEN TIMESTAMPDIFF(HOUR, p.jam_mulai, p.jam_selesai)
                ELSE 0
            END
        ) as total_jam_pakai,
        ROUND(AVG(
            CASE 
                WHEN p.jam_mulai IS NOT NULL AND p.jam_selesai IS NOT NULL 
                THEN TIMESTAMPDIFF(HOUR, p.jam_mulai, p.jam_selesai)
                ELSE 0
            END
        ), 1) as rata_rata_jam
    FROM mobil m
    LEFT JOIN peminjaman p ON m.id = p.mobil_id";

if ($filter_month !== 'all' && $filter_year !== 'all') {
    $frekuensi_armada_query .= " AND MONTH(p.tanggal_pinjam) = '$filter_month' AND YEAR(p.tanggal_pinjam) = '$filter_year'";
} elseif ($filter_month !== 'all') {
    $frekuensi_armada_query .= " AND MONTH(p.tanggal_pinjam) = '$filter_month'";
} elseif ($filter_year !== 'all') {
    $frekuensi_armada_query .= " AND YEAR(p.tanggal_pinjam) = '$filter_year'";
}

$frekuensi_armada_query .= " GROUP BY m.id ORDER BY total_peminjaman DESC, total_jam_pakai DESC";
$frekuensi_armada = mysqli_query($conn, $frekuensi_armada_query);

// 9. Laporan Rekapitulasi Status Pengajuan
$rekapitulasi_status_query = "
    SELECT 
        status,
        COUNT(*) as jumlah,
        ROUND(COUNT(*) * 100.0 / GREATEST((SELECT COUNT(*) FROM peminjaman";

if ($where_clause) {
    $rekapitulasi_status_query .= " " . $where_clause;
}

$rekapitulasi_status_query .= "), 1), 1) as persentase
    FROM peminjaman";

if ($where_clause) {
    $rekapitulasi_status_query .= " " . $where_clause;
}

$rekapitulasi_status_query .= " GROUP BY status
    ORDER BY 
        CASE status
            WHEN 'Menunggu ACC' THEN 1
            WHEN 'Menunggu Jadwal' THEN 2
            WHEN 'Dipinjam' THEN 3
            WHEN 'Dikembalikan' THEN 4
            WHEN 'Lewat Batas' THEN 5
            WHEN 'Ditolak' THEN 6
            ELSE 7
        END";

$rekapitulasi_status = mysqli_query($conn, $rekapitulasi_status_query);

// 10. Laporan Distribusi Peminjam per Unit Kerja
$distribusi_unit_query = "
    SELECT 
        COALESCE(b.nama_bidang, 'Tidak Ada Bidang') as nama_bidang,
        COALESCE(b.kode_bidang, '-') as kode_bidang,
        COUNT(DISTINCT p.user_id) as jumlah_pegawai,
        COUNT(p.id) as total_peminjaman,
        ROUND(AVG(
            CASE 
                WHEN p.jam_mulai IS NOT NULL AND p.jam_selesai IS NOT NULL 
                THEN TIMESTAMPDIFF(HOUR, p.jam_mulai, p.jam_selesai)
                ELSE 0
            END
        ), 1) as rata_rata_durasi
    FROM peminjaman p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN bidang b ON u.bidang_id = b.id";

if ($filter_month !== 'all' && $filter_year !== 'all') {
    $distribusi_unit_query .= " WHERE MONTH(p.tanggal_pinjam) = '$filter_month' AND YEAR(p.tanggal_pinjam) = '$filter_year'";
} elseif ($filter_month !== 'all') {
    $distribusi_unit_query .= " WHERE MONTH(p.tanggal_pinjam) = '$filter_month'";
} elseif ($filter_year !== 'all') {
    $distribusi_unit_query .= " WHERE YEAR(p.tanggal_pinjam) = '$filter_year'";
}

$distribusi_unit_query .= " GROUP BY COALESCE(b.id, 0), COALESCE(b.nama_bidang, 'Tidak Ada Bidang'), COALESCE(b.kode_bidang, '-')
    ORDER BY total_peminjaman DESC, jumlah_pegawai DESC";

$distribusi_unit = mysqli_query($conn, $distribusi_unit_query);

// 11. Laporan Durasi Peminjaman Terlama
$durasi_terlama_query = "
    SELECT 
        p.id,
        p.tanggal_pinjam,
        p.jam_mulai,
        p.jam_selesai,
        p.tanggal_kembali,
        p.keperluan,
        p.status,
        u.nama as nama_pegawai,
        u.nip,
        COALESCE(b.nama_bidang, 'Tidak Ada Bidang') as nama_bidang,
        m.nama_mobil,
        m.nomor_plat,
        TIMESTAMPDIFF(HOUR, p.jam_mulai, p.jam_selesai) as durasi_jam,
        MOD(TIMESTAMPDIFF(MINUTE, p.jam_mulai, p.jam_selesai), 60) as durasi_menit,
        CASE 
            WHEN p.tanggal_kembali IS NOT NULL 
            THEN 'Selesai'
            WHEN NOW() > CONCAT(p.tanggal_pinjam, ' ', p.jam_selesai)
            THEN 'Lewat Batas'
            ELSE 'Masih Berjalan'
        END as status_durasi
    FROM peminjaman p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN bidang b ON u.bidang_id = b.id
    JOIN mobil m ON p.mobil_id = m.id
    WHERE p.jam_mulai IS NOT NULL 
    AND p.jam_selesai IS NOT NULL";

if ($filter_month !== 'all' && $filter_year !== 'all') {
    $durasi_terlama_query .= " AND MONTH(p.tanggal_pinjam) = '$filter_month' AND YEAR(p.tanggal_pinjam) = '$filter_year'";
} elseif ($filter_month !== 'all') {
    $durasi_terlama_query .= " AND MONTH(p.tanggal_pinjam) = '$filter_month'";
} elseif ($filter_year !== 'all') {
    $durasi_terlama_query .= " AND YEAR(p.tanggal_pinjam) = '$filter_year'";
}

$durasi_terlama_query .= " ORDER BY durasi_jam DESC LIMIT 20";
$durasi_terlama = mysqli_query($conn, $durasi_terlama_query);

// Hitung statistik untuk chart overview
$selesai = 0;
$aktif = 0;
$ditolak = 0;
$lewat = 0;
$lainnya = 0;

mysqli_data_seek($status_stats, 0);
while ($stat = mysqli_fetch_assoc($status_stats)) {
    if ($stat['status'] == 'Dikembalikan') {
        $selesai = $stat['jumlah'];
    } elseif ($stat['status'] == 'Dipinjam') {
        $aktif += $stat['jumlah'];
    } elseif ($stat['status'] == 'Ditolak') {
        $ditolak = $stat['jumlah'];
    } elseif ($stat['status'] == 'Lewat Batas') {
        $lewat = $stat['jumlah'];
    } else {
        $lainnya += $stat['jumlah'];
    }
}

// Format untuk header
$waktu_server = date('H:i:s');
$updated = date('d/m/Y H:i');

// Buat teks periode
if ($filter_month === 'all' && $filter_year === 'all') {
    $periode_text = "Semua Periode";
} elseif ($filter_month === 'all' && $filter_year !== 'all') {
    $periode_text = "Tahun " . $filter_year;
} elseif ($filter_month !== 'all' && $filter_year === 'all') {
    $periode_text = "Bulan " . date('F', mktime(0, 0, 0, $filter_month, 1));
} else {
    $periode_text = date('F', mktime(0, 0, 0, $filter_month, 1)) . " " . $filter_year;
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Admin - Peminjaman Mobil Dinas</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            height: 100%;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            height: 400px;
        }

        .table-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .nav-tabs .nav-link.active {
            background-color: #0d6efd;
            color: white;
            border-color: #0d6efd;
        }

        .filter-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .insight-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .report-summary {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .status-menunggu {
            background-color: #ffc107;
            color: #000;
        }

        .status-dipinjam {
            background-color: #17a2b8;
            color: white;
        }

        .status-selesai {
            background-color: #28a745;
            color: white;
        }

        .status-lewat {
            background-color: #dc3545;
            color: white;
        }

        .status-ditolak {
            background-color: #6c757d;
            color: white;
        }

        .page-header {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .header-title {
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .header-subtitle {
            color: #7f8c8d;
            font-size: 0.95em;
            margin-bottom: 15px;
        }

        .header-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #ecf0f1;
            padding-top: 10px;
            margin-top: 10px;
        }

        .info-item {
            display: flex;
            align-items: center;
            font-size: 0.9em;
            color: #5d6d7e;
        }

        .info-item i {
            margin-right: 8px;
            color: #3498db;
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

            .chart-container {
                height: 300px;
            }

            .header-info {
                flex-direction: column;
                align-items: flex-start;
            }

            .info-item {
                margin-bottom: 5px;
            }
        }
    </style>
</head>

<body>
    <?php include "../includes/sidebar.php"; ?>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        <!-- HEADER -->
        <div class="page-header">
            <h1 class="header-title">
                <i class="fas fa-chart-bar me-2"></i>Laporan & Analisis
            </h1>
            <p class="header-subtitle">Semua riwayat peminjaman mobil dinas dari semua pegawai</p>

            <div class="header-info">
                <div class="info-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Periode: <?= $periode_text ?></span>
                </div>
                <div class="info-item">
                    <i class="fas fa-clock"></i>
                    <span>Waktu Server: <?= $waktu_server ?></span>
                </div>
                <div class="info-item">
                    <i class="fas fa-sync-alt"></i>
                    <span>Update: <?= $updated ?></span>
                </div>
            </div>
        </div>

        <!-- FILTER PERIODE -->
        <div class="filter-card">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Bulan</label>
                    <select name="bulan" class="form-select">
                        <option value="all" <?= $filter_month === 'all' ? 'selected' : '' ?>>Semua Bulan</option>
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?= $i ?>" <?= $filter_month == $i ? 'selected' : '' ?>>
                                <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tahun</label>
                    <select name="tahun" class="form-select">
                        <option value="all" <?= $filter_year === 'all' ? 'selected' : '' ?>>Semua Tahun</option>
                        <?php for ($i = date('Y') - 2; $i <= date('Y') + 1; $i++): ?>
                            <option value="<?= $i ?>" <?= $filter_year == $i ? 'selected' : '' ?>>
                                <?= $i ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Jenis Laporan</label>
                    <select name="jenis" class="form-select">
                        <option value="overview" <?= $filter_type == 'overview' ? 'selected' : '' ?>>Overview</option>
                        <option value="peminjaman" <?= $filter_type == 'peminjaman' ? 'selected' : '' ?>>Detail Peminjaman</option>
                        <option value="mobil" <?= $filter_type == 'mobil' ? 'selected' : '' ?>>Analisis Mobil</option>
                        <option value="pegawai" <?= $filter_type == 'pegawai' ? 'selected' : '' ?>>Analisis Pegawai</option>
                        <option value="armada" <?= $filter_type == 'armada' ? 'selected' : '' ?>>Frekuensi Armada</option>
                        <option value="status" <?= $filter_type == 'status' ? 'selected' : '' ?>>Rekap Status</option>
                        <option value="distribusi" <?= $filter_type == 'distribusi' ? 'selected' : '' ?>>Distribusi Unit</option>
                        <option value="durasi" <?= $filter_type == 'durasi' ? 'selected' : '' ?>>Durasi Peminjaman</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-2"></i>Terapkan Filter
                    </button>
                </div>
            </form>

            <div class="mt-3">
                <div class="btn-group">
                    <button class="btn btn-outline-secondary" onclick="setPeriod('harian')">
                        <i class="fas fa-calendar-day me-1"></i>Harian
                    </button>
                    <button class="btn btn-outline-secondary" onclick="setPeriod('bulanan')">
                        <i class="fas fa-calendar-alt me-1"></i>Bulanan
                    </button>
                    <button class="btn btn-outline-secondary" onclick="setPeriod('tahunan')">
                        <i class="fas fa-calendar me-1"></i>Tahunan
                    </button>
                    <button class="btn btn-outline-secondary" onclick="setPeriod('custom')">
                        <i class="fas fa-calendar-week me-1"></i>Custom
                    </button>
                </div>

                <!-- <div class="float-end">
                    <button class="btn btn-success" onclick="exportToExcel()">
                        <i class="fas fa-file-excel me-2"></i>Export Excel
                    </button>
                    <button class="btn btn-danger" onclick="window.print()">
                        <i class="fas fa-print me-2"></i>Cetak Laporan
                    </button>
                </div> -->
            </div>
        </div>

        <!-- STATISTIK UTAMA -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon text-primary">
                        <i class="fas fa-car fa-2x"></i>
                    </div>
                    <div class="stat-number"><?= $total_peminjaman['total'] ?? 0 ?></div>
                    <div class="stat-title">Total Peminjaman</div>
                    <small class="text-muted"><?= $periode_text ?></small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon text-success">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                    <div class="stat-number"><?= $selesai ?></div>
                    <div class="stat-title">Peminjaman Selesai</div>
                    <small class="text-muted">Berhasil dikembalikan</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon text-warning">
                        <i class="fas fa-clock fa-2x"></i>
                    </div>
                    <div class="stat-number"><?= $aktif ?></div>
                    <div class="stat-title">Sedang Aktif</div>
                    <small class="text-muted">Dalam proses peminjaman</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon text-secondary">
                        <i class="fas fa-times-circle fa-2x"></i>
                    </div>
                    <div class="stat-number"><?= $ditolak ?></div>
                    <div class="stat-title">Ditolak</div>
                    <small class="text-muted">Pengajuan ditolak</small>
                </div>
            </div>
        </div>

        <!-- TAB NAVIGASI -->
        <ul class="nav nav-tabs mb-4" id="reportTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button">
                    <i class="fas fa-tachometer-alt me-2"></i>Overview
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="peminjaman-tab" data-bs-toggle="tab" data-bs-target="#peminjaman" type="button">
                    <i class="fas fa-history me-2"></i>Detail Peminjaman
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="mobil-tab" data-bs-toggle="tab" data-bs-target="#mobil" type="button">
                    <i class="fas fa-car me-2"></i>Analisis Mobil
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pegawai-tab" data-bs-toggle="tab" data-bs-target="#pegawai" type="button">
                    <i class="fas fa-users me-2"></i>Analisis Pegawai
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="armada-tab" data-bs-toggle="tab" data-bs-target="#armada" type="button">
                    <i class="fas fa-chart-line me-2"></i>Frekuensi Armada
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="status-tab" data-bs-toggle="tab" data-bs-target="#status" type="button">
                    <i class="fas fa-clipboard-list me-2"></i>Rekap Status
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="distribusi-tab" data-bs-toggle="tab" data-bs-target="#distribusi" type="button">
                    <i class="fas fa-sitemap me-2"></i>Distribusi Unit
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="durasi-tab" data-bs-toggle="tab" data-bs-target="#durasi" type="button">
                    <i class="fas fa-hourglass-half me-2"></i>Durasi Peminjaman
                </button>
            </li>
        </ul>

        <!-- TAB CONTENT -->
        <div class="tab-content" id="reportTabContent">

            <!-- TAB 1: OVERVIEW -->
            <div class="tab-pane fade show active" id="overview">
                <div class="row">
                    <!-- CHART 1: TREN PEMINJAMAN -->
                    <div class="col-md-8">
                        <div class="chart-container">
                            <h5><i class="fas fa-chart-line me-2"></i>Tren Peminjaman (12 Bulan Terakhir)</h5>
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>

                    <!-- CHART 2: DISTRIBUSI STATUS -->
                    <div class="col-md-4">
                        <div class="chart-container">
                            <h5><i class="fas fa-chart-pie me-2"></i>Distribusi Status</h5>
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- INSIGHT & REKOMENDASI -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="insight-card">
                            <h6><i class="fas fa-lightbulb me-2"></i>Insight Sistem</h6>
                            <ul class="mb-0">
                                <li>Total <?= $total_peminjaman['total'] ?? 0 ?> peminjaman <?= strtolower($periode_text) ?></li>
                                <li><?= $lewat ?> peminjaman lewat batas</li>
                                <li><?= $aktif ?> peminjaman masih aktif</li>
                                <li>Rata-rata: <?= round($total_peminjaman['total'] / ($filter_month !== 'all' ? date('t') : 30), 1) ?> peminjaman/hari</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="insight-card">
                            <h6><i class="fas fa-cogs me-2"></i>Rekomendasi</h6>
                            <ul class="mb-0">
                                <li>Optimalkan jadwal untuk mobil populer</li>
                                <li>Monitor peminjaman yang mendekati batas</li>
                                <li>Evaluasi pegawai dengan keterlambatan</li>
                                <li>Perencanaan maintenance berkala</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: DETAIL PEMINJAMAN -->
            <div class="tab-pane fade" id="peminjaman">
                <div class="table-card">
                    <h5><i class="fas fa-list me-2"></i>Detail Peminjaman <?= $periode_text ?></h5>
                    <div class="table-responsive">
                        <table class="table table-hover" id="detailTable">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Pegawai</th>
                                    <th>Mobil</th>
                                    <th>Plat</th>
                                    <th>Jam Mulai</th>
                                    <th>Jam Selesai</th>
                                    <th>Durasi</th>
                                    <th>Status</th>
                                    <th>Keperluan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $detail_query = "SELECT p.*, u.nama, u.nip, m.nama_mobil, m.nomor_plat,
                                    TIMESTAMPDIFF(HOUR, p.jam_mulai, p.jam_selesai) as durasi_jam,
                                    MOD(TIMESTAMPDIFF(MINUTE, p.jam_mulai, p.jam_selesai), 60) as durasi_menit
                                    FROM peminjaman p
                                    JOIN users u ON p.user_id = u.id
                                    JOIN mobil m ON p.mobil_id = m.id";

                                if ($filter_month !== 'all' && $filter_year !== 'all') {
                                    $detail_query .= " WHERE MONTH(p.tanggal_pinjam) = '$filter_month' AND YEAR(p.tanggal_pinjam) = '$filter_year'";
                                } elseif ($filter_month !== 'all') {
                                    $detail_query .= " WHERE MONTH(p.tanggal_pinjam) = '$filter_month'";
                                } elseif ($filter_year !== 'all') {
                                    $detail_query .= " WHERE YEAR(p.tanggal_pinjam) = '$filter_year'";
                                }

                                $detail_query .= " ORDER BY p.tanggal_pinjam DESC";
                                $detail_result = mysqli_query($conn, $detail_query);

                                while ($detail = mysqli_fetch_assoc($detail_result)):
                                    $durasi_text = '';
                                    if ($detail['durasi_jam'] > 0 || $detail['durasi_menit'] > 0) {
                                        if ($detail['durasi_jam'] > 0) {
                                            $durasi_text .= $detail['durasi_jam'] . ' jam ';
                                        }
                                        if ($detail['durasi_menit'] > 0) {
                                            $durasi_text .= $detail['durasi_menit'] . ' menit';
                                        }
                                    } else {
                                        $durasi_text = '0 jam';
                                    }
                                ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($detail['tanggal_pinjam'])) ?></td>
                                        <td><?= $detail['nama'] ?><br><small><?= $detail['nip'] ?></small></td>
                                        <td><?= $detail['nama_mobil'] ?></td>
                                        <td><?= $detail['nomor_plat'] ?></td>
                                        <td><?= $detail['jam_mulai'] ?></td>
                                        <td><?= $detail['jam_selesai'] ?></td>
                                        <td><?= $durasi_text ?></td>
                                        <td>
                                            <span class="badge bg-<?=
                                                                    $detail['status'] == 'Dikembalikan' ? 'success' : ($detail['status'] == 'Dipinjam' ? 'warning' : ($detail['status'] == 'Lewat Batas' ? 'danger' : ($detail['status'] == 'Ditolak' ? 'secondary' : 'info')))
                                                                    ?>">
                                                <?= $detail['status'] ?>
                                            </span>
                                        </td>
                                        <td><?= substr($detail['keperluan'], 0, 50) ?>...</td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 3: ANALISIS MOBIL -->
            <div class="tab-pane fade" id="mobil">
                <div class="row">
                    <div class="col-md-6">
                        <div class="chart-container">
                            <h5><i class="fas fa-chart-bar me-2"></i>Top 5 Mobil Terpopuler</h5>
                            <canvas id="mobilChart"></canvas>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="table-card">
                            <h5><i class="fas fa-car me-2"></i>Utilisasi Mobil</h5>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Mobil</th>
                                            <th>Plat</th>
                                            <th>Total Dipinjam</th>
                                            <th>Total Jam</th>
                                            <th>Rata-rata/Jam</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php mysqli_data_seek($utilisasi_mobil, 0);
                                        while ($util = mysqli_fetch_assoc($utilisasi_mobil)):
                                            $rata_rata = $util['total_peminjaman'] > 0 ? round($util['total_jam'] / $util['total_peminjaman'], 1) : 0;
                                        ?>
                                            <tr>
                                                <td><?= $util['nama_mobil'] ?></td>
                                                <td><?= $util['nomor_plat'] ?></td>
                                                <td class="text-center"><?= $util['total_peminjaman'] ?></td>
                                                <td class="text-center"><?= $util['total_jam'] ?? 0 ?> jam</td>
                                                <td class="text-center"><?= $rata_rata ?> jam</td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 4: ANALISIS PEGAWAI -->
            <!-- Di dalam TAB 4: ANALISIS PEGAWAI (cari bagian ini di laporan.php) -->
            <div class="tab-pane fade" id="pegawai">
                <!-- Tambahkan tombol cetak PDF di sini -->
                <div class="mb-3 text-end">
                    <a href="laporan-analisis-pegawai-pdf.php?bulan=<?= $filter_month ?>&tahun=<?= $filter_year ?>"
                        target="_blank"
                        class="btn btn-danger">
                        <i class="fas fa-file-pdf me-2"></i>Cetak PDF Analisis Pegawai
                    </a>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="chart-container">
                            <h5><i class="fas fa-chart-bar me-2"></i>Top 5 Pegawai Teraktif</h5>
                            <canvas id="pegawaiChart"></canvas>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="table-card">
                            <h5><i class="fas fa-clock me-2"></i>Total Jam Peminjaman Pegawai</h5>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Pegawai</th>
                                            <th>Unit Kerja</th>
                                            <th>Total Peminjaman</th>
                                            <th>Total Jam</th>
                                            <th>Rata-rata/Jam</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $total_jam_pegawai_query = "
                                SELECT 
                                    u.nama as nama_pegawai,
                                    u.nip,
                                    b.nama_bidang,
                                    COUNT(p.id) as total_peminjaman,
                                    SUM(TIMESTAMPDIFF(HOUR, p.jam_mulai, p.jam_selesai)) as total_jam,
                                    ROUND(AVG(TIMESTAMPDIFF(HOUR, p.jam_mulai, p.jam_selesai)), 1) as rata_jam
                                FROM peminjaman p
                                JOIN users u ON p.user_id = u.id
                                LEFT JOIN bidang b ON u.bidang_id = b.id
                                WHERE p.jam_mulai IS NOT NULL 
                                AND p.jam_selesai IS NOT NULL";

                                        if ($filter_month !== 'all' && $filter_year !== 'all') {
                                            $total_jam_pegawai_query .= " AND MONTH(p.tanggal_pinjam) = '$filter_month' AND YEAR(p.tanggal_pinjam) = '$filter_year'";
                                        } elseif ($filter_month !== 'all') {
                                            $total_jam_pegawai_query .= " AND MONTH(p.tanggal_pinjam) = '$filter_month'";
                                        } elseif ($filter_year !== 'all') {
                                            $total_jam_pegawai_query .= " AND YEAR(p.tanggal_pinjam) = '$filter_year'";
                                        }

                                        $total_jam_pegawai_query .= " GROUP BY p.user_id ORDER BY total_jam DESC LIMIT 10";
                                        $total_jam_pegawai = mysqli_query($conn, $total_jam_pegawai_query);

                                        while ($pegawai = mysqli_fetch_assoc($total_jam_pegawai)):
                                        ?>
                                            <tr>
                                                <td>
                                                    <?= $pegawai['nama_pegawai'] ?>
                                                    <br><small><?= $pegawai['nip'] ?></small>
                                                </td>
                                                <td><?= $pegawai['nama_bidang'] ?? '-' ?></td>
                                                <td class="text-center"><?= $pegawai['total_peminjaman'] ?></td>
                                                <td class="text-center"><?= $pegawai['total_jam'] ?? 0 ?> jam</td>
                                                <td class="text-center"><?= $pegawai['rata_jam'] ?> jam</td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 5: FREKUENSI ARMADA -->
            <div class="tab-pane fade" id="armada">
                <div class="table-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5><i class="fas fa-chart-line me-2"></i>Statistik Frekuensi Armada</h5>
                        <div class="report-summary">
                            <small><i class="fas fa-info-circle me-1"></i>Mobil paling sering dipakai <?= strtolower($periode_text) ?></small>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover" id="frekuensiTable">
                            <thead class="table-dark">
                                <tr>
                                    <th>#</th>
                                    <th>Mobil</th>
                                    <th>Plat</th>
                                    <th>Status</th>
                                    <th class="text-center">Total Dipinjam</th>
                                    <th class="text-center">Total Jam Pakai</th>
                                    <th class="text-center">Rata-rata/Jam</th>
                                    <th class="text-center">Intensitas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $counter = 1;
                                mysqli_data_seek($frekuensi_armada, 0);
                                while ($armada = mysqli_fetch_assoc($frekuensi_armada)):
                                    $intensitas = '';
                                    $intensitas_class = '';
                                    if ($armada['total_peminjaman'] >= 10) {
                                        $intensitas = 'Sangat Tinggi';
                                        $intensitas_class = 'danger';
                                    } elseif ($armada['total_peminjaman'] >= 5) {
                                        $intensitas = 'Tinggi';
                                        $intensitas_class = 'warning';
                                    } elseif ($armada['total_peminjaman'] >= 1) {
                                        $intensitas = 'Sedang';
                                        $intensitas_class = 'info';
                                    } else {
                                        $intensitas = 'Rendah';
                                        $intensitas_class = 'secondary';
                                    }
                                ?>
                                    <tr>
                                        <td><?= $counter++ ?></td>
                                        <td><strong><?= $armada['nama_mobil'] ?></strong></td>
                                        <td><code><?= $armada['nomor_plat'] ?></code></td>
                                        <td>
                                            <span class="badge bg-<?= $armada['status_mobil'] == 'tersedia' ? 'success' : 'secondary' ?>">
                                                <?= ucfirst($armada['status_mobil']) ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-primary rounded-pill" style="font-size: 1em; padding: 5px 15px;">
                                                <?= $armada['total_peminjaman'] ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-info text-dark">
                                                <?= $armada['total_jam_pakai'] ?? 0 ?> jam
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?= $armada['rata_rata_jam'] ?? 0 ?> jam
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-<?= $intensitas_class ?> status-badge">
                                                <?= $intensitas ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="fas fa-lightbulb me-1"></i>
                            <strong>Interpretasi:</strong>
                            <span class="badge bg-danger">Sangat Tinggi</span> = ≥10 peminjaman,
                            <span class="badge bg-warning">Tinggi</span> = 5-9 peminjaman,
                            <span class="badge bg-info">Sedang</span> = 1-4 peminjaman,
                            <span class="badge bg-secondary">Rendah</span> = 0 peminjaman
                        </small>
                    </div>
                </div>
            </div>

            <!-- TAB 6: REKAPITULASI STATUS -->
            <div class="tab-pane fade" id="status">
                <div class="row">
                    <div class="col-md-5">
                        <div class="chart-container">
                            <h5><i class="fas fa-chart-pie me-2"></i>Distribusi Status Pengajuan</h5>
                            <canvas id="statusPengajuanChart"></canvas>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <div class="table-card">
                            <h5><i class="fas fa-clipboard-list me-2"></i>Rekapitulasi Status Peminjaman</h5>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Status</th>
                                            <th class="text-center">Jumlah</th>
                                            <th class="text-center">Persentase</th>
                                            <th class="text-center">Indikator</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $total_all = $total_peminjaman['total'] ?? 1;
                                        mysqli_data_seek($rekapitulasi_status, 0);
                                        while ($rekap = mysqli_fetch_assoc($rekapitulasi_status)):
                                            // Tentukan warna badge berdasarkan status
                                            $badge_class = '';
                                            if (strpos($rekap['status'], 'Menunggu') !== false) {
                                                $badge_class = 'status-menunggu';
                                            } elseif ($rekap['status'] == 'Dipinjam') {
                                                $badge_class = 'status-dipinjam';
                                            } elseif ($rekap['status'] == 'Dikembalikan') {
                                                $badge_class = 'status-selesai';
                                            } elseif ($rekap['status'] == 'Lewat Batas') {
                                                $badge_class = 'status-lewat';
                                            } elseif ($rekap['status'] == 'Ditolak') {
                                                $badge_class = 'status-ditolak';
                                            }

                                            // Progress bar width
                                            $progress_width = $rekap['persentase'];
                                        ?>
                                            <tr>
                                                <td>
                                                    <span class="status-badge <?= $badge_class ?>">
                                                        <?= $rekap['status'] ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <strong><?= $rekap['jumlah'] ?></strong>
                                                </td>
                                                <td>
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar 
                                                            <?= $rekap['status'] == 'Dikembalikan' ? 'bg-success' : ($rekap['status'] == 'Dipinjam' ? 'bg-warning' : ($rekap['status'] == 'Lewat Batas' ? 'bg-danger' : ($rekap['status'] == 'Ditolak' ? 'bg-secondary' : 'bg-info'))) ?>"
                                                            role="progressbar"
                                                            style="width: <?= $progress_width ?>%;"
                                                            aria-valuenow="<?= $progress_width ?>"
                                                            aria-valuemin="0"
                                                            aria-valuemax="100">
                                                            <?= $rekap['persentase'] ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($rekap['status'] == 'Dikembalikan'): ?>
                                                        <i class="fas fa-check-circle text-success" title="Selesai"></i>
                                                    <?php elseif ($rekap['status'] == 'Lewat Batas'): ?>
                                                        <i class="fas fa-exclamation-triangle text-danger" title="Perlu Perhatian"></i>
                                                    <?php elseif (strpos($rekap['status'], 'Menunggu') !== false): ?>
                                                        <i class="fas fa-clock text-warning" title="Dalam Proses"></i>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                        <tr class="table-dark">
                                            <td><strong>TOTAL</strong></td>
                                            <td class="text-center"><strong><?= $total_peminjaman['total'] ?? 0 ?></strong></td>
                                            <td><strong>100%</strong></td>
                                            <td></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 7: DISTRIBUSI PER UNIT KERJA -->
            <div class="tab-pane fade" id="distribusi">
                <div class="row">
                    <div class="col-md-6">
                        <div class="chart-container">
                            <h5><i class="fas fa-chart-bar me-2"></i>Distribusi Peminjaman per Unit Kerja</h5>
                            <canvas id="distribusiChart"></canvas>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="table-card">
                            <h5><i class="fas fa-sitemap me-2"></i>Detail Distribusi per Bidang/Seksi</h5>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-info">
                                        <tr>
                                            <th>Unit Kerja</th>
                                            <th class="text-center">Pegawai</th>
                                            <th class="text-center">Peminjaman</th>
                                            <th class="text-center">Rata Durasi</th>
                                            <th class="text-center">Aktivitas</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        mysqli_data_seek($distribusi_unit, 0);
                                        while ($unit = mysqli_fetch_assoc($distribusi_unit)):
                                            // Tentukan tingkat aktivitas
                                            $aktivitas = '';
                                            $aktivitas_class = '';
                                            if ($unit['total_peminjaman'] >= 15) {
                                                $aktivitas = 'Sangat Aktif';
                                                $aktivitas_class = 'success';
                                            } elseif ($unit['total_peminjaman'] >= 8) {
                                                $aktivitas = 'Aktif';
                                                $aktivitas_class = 'info';
                                            } elseif ($unit['total_peminjaman'] >= 1) {
                                                $aktivitas = 'Cukup Aktif';
                                                $aktivitas_class = 'warning';
                                            } else {
                                                $aktivitas = 'Minim Aktif';
                                                $aktivitas_class = 'secondary';
                                            }
                                        ?>
                                            <tr>
                                                <td>
                                                    <strong><?= $unit['nama_bidang'] ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?= $unit['kode_bidang'] ?></small>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-primary rounded-pill">
                                                        <?= $unit['jumlah_pegawai'] ?> orang
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <strong><?= $unit['total_peminjaman'] ?></strong>
                                                </td>
                                                <td class="text-center">
                                                    <?= $unit['rata_rata_durasi'] ?> jam
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-<?= $aktivitas_class ?>">
                                                        <?= $aktivitas ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 8: DURASI PEMINJAMAN TERLAMA -->
            <div class="tab-pane fade" id="durasi">
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="report-summary">
                            <h6><i class="fas fa-hourglass-half me-2"></i>Analisis Durasi Peminjaman Terlama</h6>
                            <p class="mb-0">Menampilkan 20 peminjaman dengan durasi terlama <?= strtolower($periode_text) ?></p>
                        </div>
                    </div>
                </div>
                <div class="table-card">
                    <h5><i class="fas fa-hourglass-end me-2"></i>Top 20 Durasi Peminjaman Terlama</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-warning">
                                <tr>
                                    <th>#</th>
                                    <th>Peminjaman ID</th>
                                    <th>Pegawai</th>
                                    <th>Unit Kerja</th>
                                    <th>Mobil</th>
                                    <th>Tanggal Pinjam</th>
                                    <th>Durasi</th>
                                    <th>Status</th>
                                    <th>Keperluan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $counter = 1;
                                mysqli_data_seek($durasi_terlama, 0);
                                while ($durasi = mysqli_fetch_assoc($durasi_terlama)):
                                    // Tentukan warna durasi
                                    $durasi_class = '';
                                    if ($durasi['durasi_jam'] >= 72) {
                                        $durasi_class = 'danger';
                                    } elseif ($durasi['durasi_jam'] >= 48) {
                                        $durasi_class = 'warning';
                                    } elseif ($durasi['durasi_jam'] >= 24) {
                                        $durasi_class = 'info';
                                    } else {
                                        $durasi_class = 'secondary';
                                    }

                                    // Format durasi
                                    $hari = floor($durasi['durasi_jam'] / 24);
                                    $jam = $durasi['durasi_jam'] % 24;
                                    $durasi_text = '';
                                    if ($hari > 0) {
                                        $durasi_text .= $hari . ' hari ';
                                    }
                                    if ($jam > 0) {
                                        $durasi_text .= $jam . ' jam ';
                                    }
                                    if ($durasi['durasi_menit'] > 0) {
                                        $durasi_text .= $durasi['durasi_menit'] . ' menit';
                                    }
                                ?>
                                    <tr>
                                        <td><?= $counter++ ?></td>
                                        <td><code>#PMJ<?= str_pad($durasi['id'], 4, '0', STR_PAD_LEFT) ?></code></td>
                                        <td>
                                            <strong><?= $durasi['nama_pegawai'] ?></strong>
                                            <br>
                                            <small><?= $durasi['nip'] ?></small>
                                        </td>
                                        <td><?= $durasi['nama_bidang'] ?></td>
                                        <td>
                                            <?= $durasi['nama_mobil'] ?>
                                            <br>
                                            <small><?= $durasi['nomor_plat'] ?></small>
                                        </td>
                                        <td>
                                            <?= date('d/m/Y', strtotime($durasi['tanggal_pinjam'])) ?>
                                            <br>
                                            <small><?= $durasi['jam_mulai'] ?>-<?= $durasi['jam_selesai'] ?></small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-<?= $durasi_class ?> p-2" style="font-size: 0.9em;">
                                                <strong><?= $durasi_text ?></strong>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?=
                                                                    $durasi['status'] == 'Dikembalikan' ? 'success' : ($durasi['status'] == 'Dipinjam' ? 'warning' : ($durasi['status'] == 'Lewat Batas' ? 'danger' : 'secondary'))
                                                                    ?>">
                                                <?= $durasi['status'] ?>
                                            </span>
                                            <br>
                                            <small class="text-muted"><?= $durasi['status_durasi'] ?></small>
                                        </td>
                                        <td>
                                            <span title="<?= htmlspecialchars($durasi['keperluan']) ?>">
                                                <?= substr($durasi['keperluan'], 0, 60) ?>...
                                            </span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            <strong>Kategori Durasi:</strong>
                            <span class="badge bg-danger">≥72 jam</span> = Sangat Panjang,
                            <span class="badge bg-warning">48-71 jam</span> = Panjang,
                            <span class="badge bg-info">24-47 jam</span> = Sedang,
                            <span class="badge bg-secondary">≤23 jam</span> = Normal
                        </small>
                    </div>
                </div>
            </div>

        </div>

        <!-- FOOTER -->
        <footer class="mt-5 text-center text-muted">
            <p><small>Laporan <?= $periode_text ?> | Generated: <?= $updated ?></small></p>
        </footer>

    </div>

    <!-- SCRIPTS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/exceljs/dist/exceljs.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/file-saver"></script>

    <script>
        // Initialize DataTable
        $(document).ready(function() {
            $('#detailTable').DataTable({
                pageLength: 25,
                order: [
                    [0, 'desc']
                ]
            });

            $('#frekuensiTable').DataTable({
                pageLength: 10,
                order: [
                    [4, 'desc']
                ]
            });
        });

        // CHARTS
        document.addEventListener('DOMContentLoaded', function() {
            // Chart 1: Trend Peminjaman
            const trendCtx = document.getElementById('trendChart').getContext('2d');
            const trendChart = new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: [
                        <?php
                        $chart_labels = [];
                        $chart_values = [];
                        mysqli_data_seek($chart_data, 0);
                        while ($chart = mysqli_fetch_assoc($chart_data)) {
                            $chart_labels[] = "'" . date('M Y', strtotime($chart['bulan'] . '-01')) . "'";
                            $chart_values[] = $chart['jumlah'];
                        }
                        echo implode(',', $chart_labels);
                        ?>
                    ],
                    datasets: [{
                        label: 'Jumlah Peminjaman',
                        data: [<?= implode(',', $chart_values) ?>],
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });

            // Chart 2: Status Distribution (OVERVIEW - tanpa lewat batas)
            const statusCtx = document.getElementById('statusChart').getContext('2d');
            const statusChart = new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Selesai', 'Aktif', 'Ditolak', 'Lainnya'],
                    datasets: [{
                        data: [<?= $selesai ?>, <?= $aktif ?>, <?= $ditolak ?>, <?= $lainnya ?>],
                        backgroundColor: ['#28a745', '#ffc107', '#6c757d', '#17a2b8']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });

            // Chart 3: Mobil Populer
            const mobilCtx = document.getElementById('mobilChart').getContext('2d');
            const mobilChart = new Chart(mobilCtx, {
                type: 'bar',
                data: {
                    labels: [
                        <?php
                        $mobil_labels = [];
                        $mobil_values = [];
                        mysqli_data_seek($mobil_populer, 0);
                        while ($mobil = mysqli_fetch_assoc($mobil_populer)) {
                            $mobil_labels[] = "'" . addslashes($mobil['nama_mobil']) . "'";
                            $mobil_values[] = $mobil['jumlah'];
                        }
                        echo implode(',', $mobil_labels);
                        ?>
                    ],
                    datasets: [{
                        label: 'Jumlah Peminjaman',
                        data: [<?= implode(',', $mobil_values) ?>],
                        backgroundColor: '#3498db'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y'
                }
            });

            // Chart 4: Pegawai Aktif
            const pegawaiCtx = document.getElementById('pegawaiChart').getContext('2d');
            const pegawaiChart = new Chart(pegawaiCtx, {
                type: 'bar',
                data: {
                    labels: [
                        <?php
                        $pegawai_labels = [];
                        $pegawai_values = [];
                        mysqli_data_seek($pegawai_aktif, 0);
                        while ($pegawai = mysqli_fetch_assoc($pegawai_aktif)) {
                            $pegawai_labels[] = "'" . addslashes($pegawai['nama']) . "'";
                            $pegawai_values[] = $pegawai['jumlah'];
                        }
                        echo implode(',', $pegawai_labels);
                        ?>
                    ],
                    datasets: [{
                        label: 'Jumlah Peminjaman',
                        data: [<?= implode(',', $pegawai_values) ?>],
                        backgroundColor: '#e74c3c'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y'
                }
            });

            // Chart 5: Status Pengajuan (REKAP STATUS - semua status termasuk lewat batas)
            const statusPengajuanCtx = document.getElementById('statusPengajuanChart').getContext('2d');

            <?php
            // Siapkan data untuk chart status pengajuan (untuk tab Rekap Status)
            $status_labels_all = [];
            $status_data_all = [];
            $status_colors_all = [];

            mysqli_data_seek($rekapitulasi_status, 0);
            while ($rekap = mysqli_fetch_assoc($rekapitulasi_status)) {
                $status_labels_all[] = "'" . addslashes($rekap['status']) . "'";
                $status_data_all[] = $rekap['jumlah'];

                // Tentukan warna berdasarkan status
                if ($rekap['status'] == 'Dikembalikan') {
                    $status_colors_all[] = "'#28a745'";
                } elseif ($rekap['status'] == 'Dipinjam') {
                    $status_colors_all[] = "'#ffc107'";
                } elseif ($rekap['status'] == 'Lewat Batas') {
                    $status_colors_all[] = "'#dc3545'";
                } elseif (strpos($rekap['status'], 'Menunggu') !== false) {
                    $status_colors_all[] = "'#17a2b8'";
                } elseif ($rekap['status'] == 'Ditolak') {
                    $status_colors_all[] = "'#6c757d'";
                } else {
                    $status_colors_all[] = "'#9b59b6'";
                }
            }
            ?>

            const statusPengajuanChart = new Chart(statusPengajuanCtx, {
                type: 'pie',
                data: {
                    labels: [<?= implode(',', $status_labels_all) ?>],
                    datasets: [{
                        data: [<?= implode(',', $status_data_all) ?>],
                        backgroundColor: [<?= implode(',', $status_colors_all) ?>],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                        }
                    }
                }
            });

            // Chart 6: Distribusi Unit Kerja
            const distribusiCtx = document.getElementById('distribusiChart').getContext('2d');

            <?php
            // Siapkan data untuk chart distribusi unit
            $unit_labels = [];
            $unit_data = [];

            mysqli_data_seek($distribusi_unit, 0);
            while ($unit = mysqli_fetch_assoc($distribusi_unit)) {
                if (!empty($unit['nama_bidang'])) {
                    $unit_labels[] = "'" . addslashes($unit['nama_bidang']) . "'";
                    $unit_data[] = $unit['total_peminjaman'];
                }
            }
            ?>

            const distribusiChart = new Chart(distribusiCtx, {
                type: 'bar',
                data: {
                    labels: [<?= implode(',', $unit_labels) ?>],
                    datasets: [{
                        label: 'Jumlah Peminjaman',
                        data: [<?= implode(',', $unit_data) ?>],
                        backgroundColor: [
                            '#3498db', '#e74c3c', '#2ecc71', '#f39c12',
                            '#9b59b6', '#1abc9c', '#d35400', '#34495e',
                            '#7f8c8d', '#27ae60', '#8e44ad', '#c0392b'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        });

        // EXPORT FUNCTION
        function exportToExcel() {
            const currentTab = document.querySelector('.tab-pane.active');
            const table = currentTab.querySelector('table');

            if (table) {
                alert('Mengekspor data dari tab aktif...');
                // Implementasi export Excel bisa ditambahkan di sini
                // Gunakan library exceljs untuk export
            } else {
                alert('Tidak ada tabel untuk diekspor di tab ini.');
            }
        }

        // SET PERIOD FILTER
        function setPeriod(period) {
            const today = new Date();
            let month = today.getMonth() + 1;
            let year = today.getFullYear();

            switch (period) {
                case 'harian':
                    window.location.href = `?bulan=${month}&tahun=${year}&jenis=<?= $filter_type ?>&harian=true`;
                    break;
                case 'bulanan':
                    window.location.href = `?bulan=all&tahun=${year}&jenis=<?= $filter_type ?>`;
                    break;
                case 'tahunan':
                    window.location.href = `?bulan=all&tahun=all&jenis=<?= $filter_type ?>`;
                    break;
                case 'custom':
                    const fromDate = prompt('Tanggal mulai (YYYY-MM-DD):');
                    const toDate = prompt('Tanggal selesai (YYYY-MM-DD):');
                    if (fromDate && toDate) {
                        window.location.href = `?from=${fromDate}&to=${toDate}&jenis=<?= $filter_type ?>&custom=true`;
                    }
                    break;
            }
        }

        // AUTO REFRESH DATA
        setTimeout(() => {
            location.reload();
        }, 300000); // Refresh setiap 5 menit
    </script>
</body>

</html>