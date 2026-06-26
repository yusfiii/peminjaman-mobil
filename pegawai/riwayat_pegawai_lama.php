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

if (isset($_GET['cetak_pdf'])) {

    require_once('../tcpdf/tcpdf.php');

    class PDF extends TCPDF
    {
        public function Header()
        {
            $image_file = '../assets/banjarbaru.png';
            $this->Image($image_file, 15, 10, 20, 20, 'PNG');

            $this->SetFont('helvetica', 'B', 12);
            $this->SetXY(40, 12);
            $this->Cell(150, 0, 'PEMERINTAH KOTA BANJARBARU', 0, 1, 'C');

            $this->SetFont('helvetica', 'B', 11);
            $this->SetXY(40, 18);
            $this->Cell(150, 0, 'DINAS KOMUNIKASI DAN INFORMATIKA', 0, 1, 'C');

            $this->SetFont('helvetica', '', 9);
            $this->SetXY(40, 24);
            $this->Cell(150, 0, 'Jl. Pangeran Suriansyah Nomor 5 Banjarbaru – Kalimantan Selatan', 0, 1, 'C');

            $this->SetFont('helvetica', '', 8);
            $this->SetXY(40, 29);
            $this->Cell(150, 0, 'Telp./Fax (0511) 5200052 Email : diskominfo@banjarbarukota.go.id', 0, 1, 'C');

            $this->SetLineWidth(0.8);
            $this->Line(15, 38, 195, 38);

            $this->SetY(42);
        }

        public function Footer()
        {
            $this->SetY(-20);
            $this->SetFont('helvetica', 'I', 8);
            $this->Line(15, $this->GetY() - 5, 195, $this->GetY() - 5);

            $this->SetX(15);
            $this->Cell(100, 5, 'Dicetak oleh : ' . $_SESSION['user']['nama'], 0, 0, 'L');

            $this->SetX(-60);
            $this->Cell(50, 5, 'Halaman ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, 0, 'R');
        }
    }

    $pdf = new PDF('P', 'mm', 'A4', true, 'UTF-8');
    $pdf->SetMargins(15, 42, 15);
    $pdf->SetAutoPageBreak(TRUE, 20);
    $pdf->AddPage();

    // Judul
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 7, 'LABORAN RIWAYAT PERMINJAMAN MOBILITAS DINAS', 0, 1, 'C');
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(6);

    // Info Pegawai
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(35, 6, 'Nama Pegawai', 0, 0);
    $pdf->Cell(5, 6, ':', 0, 0);
    $pdf->Cell(0, 6, $user_name, 0, 1);

    $pdf->Cell(35, 6, 'ID Pegawai', 0, 0);
    $pdf->Cell(5, 6, ':', 0, 0);
    $pdf->Cell(0, 6, $user_id, 0, 1);

    $pdf->Cell(35, 6, 'Jabatan', 0, 0);
    $pdf->Cell(5, 6, ':', 0, 0);
    $pdf->Cell(0, 6, ucfirst($user_role), 0, 1);
    $pdf->Ln(6);

    // Query Data
    $riwayat_pdf = mysqli_query($conn, "
        SELECT 
            p.*, m.nama_mobil, m.nomor_plat,
            CASE 
                WHEN p.tanggal_kembali IS NOT NULL 
                THEN TIMESTAMPDIFF(MINUTE, CONCAT(p.tanggal_pinjam,' ',p.jam_mulai), p.tanggal_kembali)
                ELSE 0
            END as durasi_minutes
        FROM peminjaman p
        JOIN mobil m ON p.mobil_id = m.id
        WHERE p.user_id = '$user_id'
        ORDER BY p.tanggal_pinjam DESC
    ");

    // Lebar tabel pas A4 (180 mm)
    $w = [8, 25, 38, 20, 25, 18, 22, 24];

    $header = ['No', 'Tanggal', 'Mobil', 'Plat', 'Jam', 'Durasi', 'Status', 'Keperluan'];

    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetFillColor(220, 220, 220);

    foreach ($header as $i => $h) {
        $pdf->Cell($w[$i], 7, $h, 1, 0, 'C', true);
    }
    $pdf->Ln();

    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetFillColor(245, 245, 245);

    $no = 1;
    while ($row = mysqli_fetch_assoc($riwayat_pdf)) {

        $tanggal = date('d/m/Y', strtotime($row['tanggal_pinjam']));
        $jam = $row['jam_mulai'] . " - " . $row['jam_selesai'];

        // Durasi tanpa menampilkan tanggal kembali
        $durasi = '-';
        if ($row['durasi_minutes'] > 0) {
            $jam_d = floor($row['durasi_minutes'] / 60);
            $mnt_d = $row['durasi_minutes'] % 60;
            $durasi = ($jam_d > 0 ? $jam_d . " jam " : "") . $mnt_d . " mnt";
        }

        $fill = ($no % 2 == 0);

        $pdf->Cell($w[0], 6, $no, 1, 0, 'C', $fill);
        $pdf->Cell($w[1], 6, $tanggal, 1, 0, 'C', $fill);
        $pdf->Cell($w[2], 6, $row['nama_mobil'], 1, 0, 'L', $fill);
        $pdf->Cell($w[3], 6, $row['nomor_plat'], 1, 0, 'C', $fill);
        $pdf->Cell($w[4], 6, $jam, 1, 0, 'C', $fill);
        $pdf->Cell($w[5], 6, $durasi, 1, 0, 'C', $fill);
        $pdf->Cell($w[6], 6, $row['status'], 1, 0, 'C', $fill);
        $pdf->Cell($w[7], 6, $row['keperluan'], 1, 0, 'L', $fill);
        $pdf->Ln();

        $no++;
    }

    // Tanda tangan kanan rata tengah
    $pdf->Ln(15);
    $ttdWidth = 70;
    $posX = 195 - $ttdWidth;

    $pdf->SetX($posX);
    $pdf->Cell($ttdWidth, 6, 'Mengetahui,', 0, 1, 'C');
    $pdf->Ln(15);

    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetX($posX);
    $pdf->Cell($ttdWidth, 6, 'Drs. KRISMAN', 0, 1, 'C');

    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetX($posX);
    $pdf->Cell($ttdWidth, 5, 'Pembina IV/a', 0, 1, 'C');

    $pdf->SetX($posX);
    $pdf->Cell($ttdWidth, 5, 'NIP 19730303 200003 1 009', 0, 1, 'C');

    $pdf->Output('Laboran_Riwayat_Peminjaman_' . $user_name . '_' . date('Y-m-d') . '.pdf', 'I');
    exit;
}


// KODE UTAMA (sama seperti sebelumnya)
$total_query = mysqli_query($conn, "
    SELECT COUNT(*) as total 
    FROM peminjaman 
    WHERE user_id = '$user_id'
");
$total_data = mysqli_fetch_assoc($total_query)['total'];

// Filter parameter - TIDAK ADA FILTER TAHUN DEFAULT
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

// Query riwayat peminjaman - SEMUA DATA
$riwayat = mysqli_query($conn, "
    SELECT 
        p.*,
        m.nama_mobil,
        m.nomor_plat,
        m.foto,
        CASE 
            WHEN p.tanggal_kembali IS NOT NULL 
            THEN TIMESTAMPDIFF(MINUTE, CONCAT(p.tanggal_pinjam, ' ', p.jam_mulai), p.tanggal_kembali)
            WHEN p.status = 'Dipinjam' OR p.status = 'Lewat Batas'
            THEN TIMESTAMPDIFF(MINUTE, CONCAT(p.tanggal_pinjam, ' ', p.jam_mulai), NOW())
            ELSE 0
        END as durasi_minutes
    FROM peminjaman p
    JOIN mobil m ON p.mobil_id = m.id
    WHERE $where_clause
    ORDER BY p.tanggal_pinjam DESC, p.jam_mulai DESC
");

// Reset pointer result set
mysqli_data_seek($riwayat, 0);

// Hitung statistik
$stats_query = mysqli_query($conn, "
    SELECT 
        p.status,
        COUNT(*) as jumlah
    FROM peminjaman p
    WHERE p.user_id = '$user_id'
    GROUP BY p.status
    ORDER BY p.status
");

$stats = [];
while ($stat = mysqli_fetch_assoc($stats_query)) {
    $stats[$stat['status']] = $stat['jumlah'];
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
                    <div class="stats-number"><?= $stats['Dibatalkan'] ?? 0 ?></div>
                    <div class="stats-label">Dibatalkan</div>
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
                <a href="?<?= http_build_query(array_merge($_GET, ['cetak_pdf' => 1])) ?>" class="btn btn-sm btn-danger" target="_blank">
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
                                // Format durasi
                                $durasi = '';
                                $durasi_minutes = $r['durasi_minutes'] ?? 0;

                                if ($durasi_minutes > 0) {
                                    $hours = floor($durasi_minutes / 60);
                                    $minutes = $durasi_minutes % 60;

                                    if ($hours > 0) {
                                        $durasi = $hours . ' jam';
                                        if ($minutes > 0) {
                                            $durasi .= ' ' . $minutes . ' menit';
                                        }
                                    } else {
                                        $durasi = $minutes . ' menit';
                                    }
                                } else {
                                    $durasi = '-';
                                }

                                // Status class
                                $status_class = '';
                                if ($r['status'] == 'Menunggu ACC') $status_class = 'badge-menunggu';
                                elseif ($r['status'] == 'Menunggu Jadwal') $status_class = 'badge-menunggu';
                                elseif ($r['status'] == 'Dipinjam') $status_class = 'badge-dipinjam';
                                elseif ($r['status'] == 'Dikembalikan') $status_class = 'badge-dikembalikan';
                                elseif ($r['status'] == 'Dibatalkan') $status_class = 'badge-batal';
                                elseif ($r['status'] == 'Lewat Batas') $status_class = 'badge-lewat';
                            ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td>
                                        <strong><?= date('d/m/Y', strtotime($r['tanggal_pinjam'])) ?></strong>
                                        <?php if ($r['tanggal_kembali']): ?>
                                            <br><small class="text-muted">Kembali: <?= date('d/m/Y H:i', strtotime($r['tanggal_kembali'])) ?></small>
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
                                    </td>
                                    <td>
                                        <span class="duration-badge">
                                            <i class="fas fa-hourglass-half me-1"></i>
                                            <?= $durasi ?>
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
                                            <?php if (($r['status'] == 'Menunggu ACC' || $r['status'] == 'Menunggu Jadwal') && !$r['tanggal_kembali']): ?>
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