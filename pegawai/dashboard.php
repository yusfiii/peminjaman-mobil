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

$now = time(); // Waktu server saat ini
date_default_timezone_set('Asia/Makassar'); // Sesuaikan dengan zona waktu lokal

// =======================================
// AUTO RESET PEMINJAMAN AKTIF USER (TOLERANSI 15 MENIT)
// =======================================
$peminjaman_saya = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT p.*, m.nama_mobil, m.nomor_plat
    FROM peminjaman p
    JOIN mobil m ON p.mobil_id = m.id
    WHERE p.user_id = '{$user_id}'
      AND p.tanggal_kembali IS NULL
    ORDER BY p.id DESC
    LIMIT 1
"));

if ($peminjaman_saya) {
    $selesai = strtotime($peminjaman_saya['tanggal_pinjam'] . ' ' . $peminjaman_saya['jam_selesai']);
    $batas_toleransi = $selesai + (15 * 60); // 15 menit
    $now = time();

    if ($peminjaman_saya['status'] === 'Dipinjam' && $now > $batas_toleransi) {
        mysqli_query($conn, "
            UPDATE peminjaman
            SET status='Dikembalikan', tanggal_kembali=NOW()
            WHERE id='{$peminjaman_saya['id']}'
        ");

        mysqli_query($conn, "
            UPDATE mobil
            SET status='Tersedia', dipakai_oleh=NULL
            WHERE id='{$peminjaman_saya['mobil_id']}'
        ");

        header("Location: dashboard.php");
        exit;
    }
}

// =======================================
// QUERY DATA
// =======================================
$q_ready = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total_ready
    FROM mobil m
    LEFT JOIN peminjaman p 
        ON m.id = p.mobil_id
        AND p.tanggal_kembali IS NULL
        AND p.status = 'Dipinjam'
    WHERE p.id IS NULL
"));
$total_ready = (int)($q_ready['total_ready'] ?? 0);

$q_total_saya = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM peminjaman
    WHERE user_id = '{$user_id}'
"));
$total_peminjaman_saya = (int)($q_total_saya['total'] ?? 0);

$q_menunggu = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM peminjaman
    WHERE user_id = '{$user_id}'
      AND status = 'Menunggu ACC'
"));
$total_menunggu_acc = (int)($q_menunggu['total'] ?? 0);

$peminjaman = mysqli_query($conn, "
    SELECT 
        p.*, 
        m.nama_mobil,
        m.nomor_plat,
        u.nama AS nama_pegawai
    FROM peminjaman p
    JOIN mobil m ON p.mobil_id = m.id
    JOIN users u ON p.user_id = u.id
    WHERE DATE(p.tanggal_pinjam) = CURDATE()
      AND p.status = 'Dipinjam'
    ORDER BY p.jam_mulai ASC
");

$peminjaman_terakhir = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT p.tanggal_pinjam, p.jam_mulai, p.jam_selesai, p.status, m.nama_mobil, m.nomor_plat
    FROM peminjaman p
    JOIN mobil m ON m.id = p.mobil_id
    WHERE p.user_id = '{$user_id}'
    ORDER BY p.id DESC
    LIMIT 1
"));

$updated = date('d/m/Y H:i');
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Peminjaman Mobil Dinas</title>

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

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            height: 100%;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
        }

        .stat-title {
            color: #7f8c8d;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }

        .table-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-top: 30px;
        }

        .badge-dipinjam {
            background-color: #f39c12;
            color: white;
        }

        .badge-lewat {
            background-color: #e74c3c;
            color: white;
        }

        .badge-dikembalikan {
            background-color: #27ae60;
            color: white;
        }

        .badge-menunggu {
            background-color: #3498db;
            color: white;
        }

        .update-time {
            font-size: 0.8rem;
            color: #95a5a6;
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
        }
    </style>
</head>

<body>
    <?php include "../includes/sidebar.php"; ?>
    <!-- Sidebar -->
    <!-- <div class="sidebar d-flex flex-column">
        <div class="user-info">
            <div class="user-avatar">
                <i class="fas fa-user"></i>
            </div>
            <h6 class="mb-1"><?= htmlspecialchars($user_name) ?></h6>
            <span class="badge bg-<?= $user_role === 'admin' ? 'danger' : 'success' ?>">
                <?= $user_role === 'admin' ? 'Admin' : 'Pegawai' ?>
            </span>
        </div>

        <div class="flex-grow-1">
            <?php
            $current = basename($_SERVER['PHP_SELF']);
            ?>

            <a href="dashboard.php" class="nav-link <?= $current == 'dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
            </a>

            <a href="mobil.php" class="nav-link <?= $current == 'mobil.php' ? 'active' : '' ?>">
                <i class="fas fa-car me-2"></i> Status Mobil
            </a>

            <a href="peminjaman.php" class="nav-link <?= $current == 'peminjaman.php' ? 'active' : '' ?>">
                <i class="fas fa-file-alt me-2"></i> Ajukan Peminjaman
            </a>

            <a href="struk.php" class="nav-link <?= $current == 'struk.php' ? 'active' : '' ?>">
                <i class="fas fa-receipt me-2"></i> Struk Saya
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
                    <h1 class="h3 mb-2">Dashboard Peminjaman Mobil</h1>
                    <p class="text-muted mb-0">Selamat datang di sistem peminjaman mobil dinas</p>
                </div>
                <div class="update-time">
                    <i class="fas fa-clock me-1"></i> Waktu Server: <?= date('H:i:s') ?>
                    <br>
                    <i class="fas fa-sync-alt me-1"></i> Update: <?= $updated ?>
                </div>
            </div>
        </div>

        <!-- Welcome Alert -->
        <div class="alert alert-primary mb-4">
            <div class="d-flex align-items-center">
                <i class="fas fa-user-circle fa-2x me-3"></i>
                <div>
                    <h5 class="mb-1">Selamat datang, <?= htmlspecialchars($user_name) ?>!</h5>
                    <p class="mb-0">Anda login sebagai <strong><?= $user_role === 'admin' ? 'Administrator' : 'Pegawai' ?></strong></p>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <!-- Mobil Tersedia -->
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon text-primary">
                        <i class="fas fa-car"></i>
                    </div>
                    <div class="stat-number"><?= $total_ready ?></div>
                    <div class="stat-title">Mobil Tersedia</div>
                    <small class="text-muted">Saat ini</small>
                </div>
            </div>

            <!-- Total Peminjaman Saya -->
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon text-success">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-number"><?= $total_peminjaman_saya ?></div>
                    <div class="stat-title">Total Peminjaman</div>
                    <small class="text-muted">Akumulasi seluruh peminjaman</small>
                </div>
            </div>

            <!-- Menunggu ACC -->
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon text-info">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-number"><?= $total_menunggu_acc ?></div>
                    <div class="stat-title">Menunggu ACC</div>
                    <small class="text-muted">Menunggu persetujuan admin</small>
                </div>
            </div>

            <!-- Peminjaman Terakhir -->
            <div class="col-md-6 col-lg-3">
                <div class="stat-card">
                    <div class="stat-icon text-warning">
                        <i class="fas fa-history"></i>
                    </div>
                    <div class="stat-title">Peminjaman Terakhir</div>
                    <?php if ($peminjaman_terakhir) { ?>
                        <div class="mt-2">
                            <h6 class="mb-1"><?= htmlspecialchars($peminjaman_terakhir['nama_mobil']) ?></h6>
                            <small class="text-muted d-block">Plat: <?= htmlspecialchars($peminjaman_terakhir['nomor_plat']) ?></small>
                            <?php
                            $status_class = '';
                            if ($peminjaman_terakhir['status'] == 'Dipinjam') $status_class = 'badge-dipinjam';
                            elseif ($peminjaman_terakhir['status'] == 'Dikembalikan') $status_class = 'badge-dikembalikan';
                            elseif ($peminjaman_terakhir['status'] == 'Menunggu ACC') $status_class = 'badge-menunggu';
                            ?>
                            <span class="badge <?= $status_class ?> mt-2">
                                <?= htmlspecialchars($peminjaman_terakhir['status']) ?>
                            </span>
                        </div>
                    <?php } else { ?>
                        <p class="text-muted mt-2 mb-0">Belum ada peminjaman</p>
                    <?php } ?>
                </div>
            </div>
        </div>

        <!-- Peminjaman Hari Ini -->
        <div class="table-container">
            <h5 class="mb-3">
                <i class="fas fa-calendar-day me-2"></i> Peminjaman Aktif Hari Ini
            </h5>

            <?php if (mysqli_num_rows($peminjaman) == 0) { ?>
                <div class="text-center py-5">
                    <i class="fas fa-car fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Tidak ada peminjaman aktif hari ini</h5>
                    <p class="text-muted">Semua mobil tersedia untuk dipinjam</p>
                </div>
            <?php } else { ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Pegawai</th>
                                <th>Mobil</th>
                                <th>Plat</th>
                                <th>Jam</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($r = mysqli_fetch_assoc($peminjaman)) { ?>
                                <?php
                                $selesai = strtotime($r['tanggal_pinjam'] . ' ' . $r['jam_selesai']);
                                $badge_class = (time() > $selesai) ? 'badge-lewat' : 'badge-dipinjam';
                                $label = (time() > $selesai) ? 'Lewat Batas' : 'Dipinjam';
                                ?>
                                <tr>
                                    <td>
                                        <i class="fas fa-user me-2 text-primary"></i>
                                        <?= htmlspecialchars($r['nama_pegawai']) ?>
                                    </td>
                                    <td>
                                        <i class="fas fa-car me-2"></i>
                                        <?= htmlspecialchars($r['nama_mobil']) ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($r['nomor_plat']) ?></span>
                                    </td>
                                    <td>
                                        <i class="fas fa-clock me-2 text-info"></i>
                                        <?= htmlspecialchars($r['jam_mulai']) ?> - <?= htmlspecialchars($r['jam_selesai']) ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $badge_class ?>">
                                            <i class="fas fa-<?= (time() > $selesai) ? 'exclamation-triangle' : 'check-circle' ?> me-1"></i>
                                            <?= $label ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </div>

        <!-- Quick Actions -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="stat-card">
                    <h6 class="mb-3"><i class="fas fa-bolt me-2"></i> Aksi Cepat</h6>
                    <div class="row g-3">
                        <div class="col-md-3 col-6">
                            <a href="mobil.php" class="btn btn-primary w-100">
                                <i class="fas fa-car me-2"></i> Status Mobil
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="peminjaman.php" class="btn btn-success w-100">
                                <i class="fas fa-file-alt me-2"></i> Ajukan Peminjaman
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="struk.php" class="btn btn-info w-100 text-white">
                                <i class="fas fa-receipt me-2"></i> Struk Saya
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="../logout.php" class="btn btn-danger w-100">
                                <i class="fas fa-sign-out-alt me-2"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="mt-5 pt-3 text-center text-muted">
            <p class="mb-1">
                <small>Copyright &copy; Sistem Peminjaman Mobil Dinas <?= date('Y') ?></small>
            </p>
            <p class="mb-0">
                <small>Versi 1.0 | Terakhir update: <?= $updated ?></small>
            </p>
        </footer>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Auto refresh setiap 60 detik
        setTimeout(function() {
            location.reload();
        }, 60000);

        // Toggle sidebar untuk mobile
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('d-none');
            document.querySelector('.main-content').classList.toggle('col-12');
        }
    </script>
</body>

</html>