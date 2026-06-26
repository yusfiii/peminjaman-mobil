<?php
session_start();
include "../config/database.php";

// proteksi admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$user = $_SESSION['user'];
$user_name = $user['nama'] ?? 'Admin';
$user_role = $user['role'] ?? 'admin';

$now = time(); // Waktu server saat ini
date_default_timezone_set('Asia/Makassar'); // Sesuaikan dengan zona waktu lokal

// ambil statistik
$stats_query = mysqli_query($conn, "
    SELECT 
        (SELECT COUNT(*) FROM mobil) as total_mobil,
        (SELECT COUNT(*) FROM users WHERE role = 'pegawai') as total_pegawai,
        (SELECT COUNT(*) FROM peminjaman WHERE status = 'Menunggu ACC') as menunggu_acc,
        (SELECT COUNT(*) FROM peminjaman WHERE status = 'Dipinjam') as sedang_dipinjam
");

$stats = mysqli_fetch_assoc($stats_query);

// ambil data peminjaman
$data = mysqli_query($conn, "
    SELECT 
        peminjaman.*, 
        users.nama, 
        mobil.nama_mobil, 
        mobil.nomor_plat,
        mobil.foto
    FROM peminjaman 
    JOIN users ON users.id = peminjaman.user_id 
    JOIN mobil ON mobil.id = peminjaman.mobil_id
    ORDER BY 
        CASE 
            WHEN peminjaman.status = 'Menunggu ACC' THEN 1
            WHEN peminjaman.status = 'Dipinjam' THEN 2
            WHEN peminjaman.status = 'Lewat Batas' THEN 3
            ELSE 4
        END,
        peminjaman.tanggal_pinjam DESC,
        peminjaman.jam_mulai DESC
");

$updated = date('d/m/Y H:i');
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Peminjaman Mobil Dinas</title>

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

        .table-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-top: 30px;
        }

        .badge-menunggu {
            background-color: #6c757d;
            color: white;
        }

        .badge-dipinjam {
            background-color: #198754;
            color: white;
        }

        .badge-lewat {
            background-color: #dc3545;
            color: white;
        }

        .badge-dikembalikan {
            background-color: #0dcaf0;
            color: #000;
        }

        .badge-ditolak {
            background-color: #ffc107;
            color: #000;
        }

        .badge-batal {
            background-color: #6c757d;
            color: white;
        }

        .mobil-thumb {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #e0e0e0;
        }

        .no-photo-thumb {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px;
            font-size: 1.5rem;
        }

        .action-buttons .btn {
            margin: 2px;
            font-size: 0.8rem;
            padding: 5px 10px;
        }

        .update-time {
            font-size: 0.8rem;
            color: #95a5a6;
        }

        .filter-buttons .btn {
            margin-right: 5px;
            margin-bottom: 5px;
        }

        .urgent-badge {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4);
            }

            70% {
                box-shadow: 0 0 0 10px rgba(220, 53, 69, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
            }
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
                margin-bottom: 5px;
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

            <a href="admin-dashboard.php" class="nav-link <?= $current == 'admin-dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt me-2"></i> Dashboard Admin
            </a>

            <a href="kelola-mobil.php" class="nav-link <?= $current == 'kelola-mobil.php' ? '' : '' ?>">
                <i class="fas fa-car me-2"></i> Kelola Mobil
            </a>

            <a href="kelola-pegawai.php" class="nav-link <?= $current == 'kelola-pegawai.php' ? '' : '' ?>">
                <i class="fas fa-users me-2"></i> Kelola Pegawai
            </a>

            <a href="riwayat.php" class="nav-link <?= $current == 'riwayat.php' ? '' : '' ?>">
                <i class="fas fa-history me-2"></i> Riwayat Peminjaman
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
                        <i class="fas fa-tachometer-alt me-2"></i> Dashboard Admin
                    </h1>
                    <p class="text-muted mb-0">Panel pengelolaan sistem peminjaman mobil dinas</p>
                </div>
                <div class="update-time">
                    <i class="fas fa-clock me-1"></i> Waktu Server: <?= date('H:i:s') ?>
                    <br>
                    <i class="fas fa-sync-alt me-1"></i> Update: <?= $updated ?>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <!-- Total Mobil -->
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="stat-icon text-primary">
                        <i class="fas fa-car"></i>
                    </div>
                    <div class="stat-number"><?= $stats['total_mobil'] ?? 0 ?></div>
                    <div class="stat-title">Total Mobil</div>
                    <small class="text-muted">Kendaraan yang terdaftar</small>
                </div>
            </div>

            <!-- Total Pegawai -->
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="stat-icon text-success">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-number"><?= $stats['total_pegawai'] ?? 0 ?></div>
                    <div class="stat-title">Total Pegawai</div>
                    <small class="text-muted">Pengguna terdaftar</small>
                </div>
            </div>

            <!-- Menunggu ACC -->
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="stat-icon text-warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-number"><?= $stats['menunggu_acc'] ?? 0 ?></div>
                    <div class="stat-title">Menunggu ACC</div>
                    <small class="text-muted">Perlu persetujuan</small>
                </div>
            </div>

            <!-- Sedang Dipinjam -->
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="stat-icon text-info">
                        <i class="fas fa-road"></i>
                    </div>
                    <div class="stat-number"><?= $stats['sedang_dipinjam'] ?? 0 ?></div>
                    <div class="stat-title">Sedang Dipinjam</div>
                    <small class="text-muted">Dalam penggunaan</small>
                </div>
            </div>
        </div>

        <!-- Filter Buttons -->
        <div class="mb-3">
            <div class="filter-buttons">
                <button class="btn btn-outline-primary filter-btn active" data-filter="all">
                    <i class="fas fa-list me-1"></i> Semua
                </button>
                <button class="btn btn-outline-warning filter-btn" data-filter="menunggu">
                    <i class="fas fa-clock me-1"></i> Menunggu ACC (<?= $stats['menunggu_acc'] ?? 0 ?>)
                </button>
                <button class="btn btn-outline-success filter-btn" data-filter="dipinjam">
                    <i class="fas fa-car me-1"></i> Dipinjam (<?= $stats['sedang_dipinjam'] ?? 0 ?>)
                </button>
                <!-- <button class="btn btn-outline-danger filter-btn" data-filter="lewat">
                    <i class="fas fa-exclamation-triangle me-1"></i> Lewat Batas
                </button> -->
            </div>
        </div>

        <!-- Table Card -->
        <div class="table-card">
            <h5 class="mb-3">
                <i class="fas fa-clipboard-list me-2"></i> Daftar Peminjaman Mobil
            </h5>

            <div class="table-responsive">
                <table id="peminjamanTable" class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Pegawai</th>
                            <th>Mobil</th>
                            <th>Tanggal & Jam</th>
                            <th>Status</th>
                            <th>Foto</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($data) > 0): ?>
                            <?php while ($r = mysqli_fetch_assoc($data)):
                                // Tentukan status class
                                $status_class = '';
                                if ($r['status'] == 'Menunggu ACC') $status_class = 'badge-menunggu';
                                elseif ($r['status'] == 'Dipinjam') $status_class = 'badge-dipinjam';
                                elseif ($r['status'] == 'Dikembalikan') $status_class = 'badge-dikembalikan';
                                elseif ($r['status'] == 'Ditolak') $status_class = 'badge-ditolak';
                                elseif ($r['status'] == 'Dibatalkan') $status_class = 'badge-batal';
                                elseif ($r['status'] == 'Lewat Batas') $status_class = 'badge-lewat urgent-badge';

                                // Tentukan data-filter
                                $filter_class = '';
                                if ($r['status'] == 'Menunggu ACC') $filter_class = 'menunggu';
                                elseif ($r['status'] == 'Dipinjam') $filter_class = 'dipinjam';
                                elseif ($r['status'] == 'Lewat Batas') $filter_class = 'lewat';
                                else $filter_class = 'lainnya';
                            ?>
                                <tr class="peminjaman-item" data-status="<?= $filter_class ?>">
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($r['nama']) ?></strong><br>
                                            <small class="text-muted">NIP: <?= htmlspecialchars($r['user_id']) ?></small>
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
                                                <small class="text-muted">Plat: <?= htmlspecialchars($r['nomor_plat']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?= date('d/m/Y', strtotime($r['tanggal_pinjam'])) ?></strong><br>
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            <?= $r['jam_mulai'] ?> - <?= $r['jam_selesai'] ?>
                                        </small>
                                        <?php if ($r['tanggal_kembali']): ?>
                                            <br><small class="text-success">
                                                <i class="fas fa-check-circle me-1"></i>
                                                Kembali: <?= date('d/m/Y H:i', strtotime($r['tanggal_kembali'])) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $status_class ?>">
                                            <?php
                                            switch ($r['status']) {
                                                case 'Menunggu ACC':
                                                    echo '<i class="fas fa-clock me-1"></i>';
                                                    break;
                                                case 'Dipinjam':
                                                    echo '<i class="fas fa-car me-1"></i>';
                                                    break;
                                                case 'Dikembalikan':
                                                    echo '<i class="fas fa-check me-1"></i>';
                                                    break;
                                                case 'Ditolak':
                                                    echo '<i class="fas fa-times me-1"></i>';
                                                    break;
                                                case 'Dibatalkan':
                                                    echo '<i class="fas fa-ban me-1"></i>';
                                                    break;
                                                case 'Lewat Batas':
                                                    echo '<i class="fas fa-exclamation-triangle me-1"></i>';
                                                    break;
                                            }
                                            echo $r['status'];
                                            ?>
                                        </span>
                                        <br>
                                        <small class="text-muted mt-1 d-block">
                                            Keperluan: <?= mb_strlen($r['keperluan']) > 30 ? mb_substr($r['keperluan'], 0, 30) . '...' : htmlspecialchars($r['keperluan']) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if (!empty($r['foto'])): ?>
                                            <button class="btn btn-sm btn-outline-primary" onclick="showImageModal('../uploads/<?= $r['foto'] ?>', '<?= htmlspecialchars($r['nama_mobil']) ?>')">
                                                <i class="fas fa-eye"></i> Lihat
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted"><i class="fas fa-image"></i> Tidak ada</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($r['status'] == 'Menunggu ACC'): ?>
                                                <a href="../actions/verifikasi.php?id=<?= $r['id'] ?>&s=Dipinjam"
                                                    class="btn btn-success btn-sm"
                                                    onclick="return confirm('Setujui peminjaman ini?\nPegawai: <?= addslashes($r['nama']) ?>\nMobil: <?= addslashes($r['nama_mobil']) ?>')"
                                                    title="Setujui Peminjaman">
                                                    <i class="fas fa-check me-1"></i> ACC
                                                </a>
                                                <a href="../actions/verifikasi.php?id=<?= $r['id'] ?>&s=Ditolak"
                                                    class="btn btn-danger btn-sm"
                                                    onclick="return confirm('Tolak peminjaman ini?\nPegawai: <?= addslashes($r['nama']) ?>\nMobil: <?= addslashes($r['nama_mobil']) ?>')"
                                                    title="Tolak Peminjaman">
                                                    <i class="fas fa-times me-1"></i> Tolak
                                                </a>
                                            <?php elseif ($r['status'] == 'Dipinjam' || $r['status'] == 'Lewat Batas'): ?>
                                                <a href="../actions/verifikasi.php?id=<?= $r['id'] ?>&s=Dikembalikan"
                                                    class="btn btn-info btn-sm"
                                                    onclick="return confirm('Tandai mobil telah dikembalikan?\nPegawai: <?= addslashes($r['nama']) ?>\nMobil: <?= addslashes($r['nama_mobil']) ?>')"
                                                    title="Tandai Dikembalikan">
                                                    <i class="fas fa-undo me-1"></i> Kembalikan
                                                </a>
                                            <?php elseif ($r['status'] == 'Dikembalikan'): ?>
                                                <button class="btn btn-secondary btn-sm" disabled title="Selesai">
                                                    <i class="fas fa-check-circle me-1"></i> Selesai
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">Belum ada data peminjaman</h5>
                                    <p class="text-muted">Tidak ada pengajuan peminjaman saat ini</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="stat-card">
                    <h6 class="mb-3"><i class="fas fa-bolt me-2"></i> Aksi Cepat</h6>
                    <div class="row g-2">
                        <div class="col-6">
                            <a href="kelola-mobil.php" class="btn btn-primary w-100">
                                <i class="fas fa-plus me-2"></i> Tambah Mobil
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="kelola-pegawai.php" class="btn btn-success w-100">
                                <i class="fas fa-user-plus me-2"></i> Tambah Pegawai
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="riwayat.php" class="btn btn-info w-100 text-white">
                                <i class="fas fa-history me-2"></i> Lihat Riwayat
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="laporan.php" class="btn btn-warning w-100 text-white">
                                <i class="fas fa-chart-bar me-2"></i> Generate Laporan
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="stat-card">
                    <h6 class="mb-3"><i class="fas fa-info-circle me-2"></i> Informasi Sistem</h6>
                    <div class="small text-muted">
                        <p><i class="fas fa-server me-2"></i> Total Data:
                            <strong><?= $stats['total_mobil'] ?? 0 ?> mobil</strong>,
                            <strong><?= $stats['total_pegawai'] ?? 0 ?> pegawai</strong>
                        </p>
                        <p><i class="fas fa-tasks me-2"></i> Pending Actions:
                            <strong><?= $stats['menunggu_acc'] ?? 0 ?> pengajuan</strong> perlu ACC
                        </p>
                        <p><i class="fas fa-car me-2"></i> Status Mobil:
                            <strong><?= ($stats['total_mobil'] ?? 0) - ($stats['sedang_dipinjam'] ?? 0) ?> tersedia</strong>,
                            <strong><?= $stats['sedang_dipinjam'] ?? 0 ?> dipinjam</strong>
                        </p>
                        <p class="mb-0"><i class="fas fa-clock me-2"></i> Last Update: <?= $updated ?></p>
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
                <small>Admin Dashboard | Total <?= mysqli_num_rows($data) ?> data peminjaman | Update: <?= $updated ?></small>
            </p>
        </footer>
    </div>

    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Foto Mobil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="" class="img-fluid rounded" alt="Foto Mobil">
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

    <script>
        // Initialize DataTable
        $(document).ready(function() {
            $('#peminjamanTable').DataTable({
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
                    [2, 'desc']
                ],
                "pageLength": 10,
                "responsive": true
            });
        });

        // Filter functionality
        document.querySelectorAll('.filter-btn').forEach(button => {
            button.addEventListener('click', function() {
                const filter = this.getAttribute('data-filter');

                // Update active button
                document.querySelectorAll('.filter-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                this.classList.add('active');

                // Filter items
                const items = document.querySelectorAll('.peminjaman-item');

                items.forEach(item => {
                    const status = item.getAttribute('data-status');
                    if (filter === 'all') {
                        item.style.display = '';
                    } else if (status === filter) {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
                });

                // Update DataTable
                $('#peminjamanTable').DataTable().draw();
            });
        });

        // Show image in modal
        function showImageModal(imageSrc, mobilName) {
            document.getElementById('modalImage').src = imageSrc;
            document.getElementById('modalTitle').textContent = 'Foto: ' + mobilName;
            const modal = new bootstrap.Modal(document.getElementById('imageModal'));
            modal.show();
        }

        // Auto refresh untuk update status
        setInterval(function() {
            location.reload();
        }, 60000); // Refresh setiap 1 menit

        // Highlight urgent items
        document.querySelectorAll('.urgent-badge').forEach(badge => {
            badge.style.animation = 'pulse 2s infinite';
        });

        // Print function
        function printTable() {
            window.print();
        }

        // Window before print event
        window.addEventListener('beforeprint', function() {
            document.querySelector('.sidebar').style.display = 'none';
            document.querySelector('.main-content').style.marginLeft = '0';
            document.querySelector('.filter-buttons').style.display = 'none';
        });

        window.addEventListener('afterprint', function() {
            document.querySelector('.sidebar').style.display = 'flex';
            document.querySelector('.main-content').style.marginLeft = '250px';
            document.querySelector('.filter-buttons').style.display = 'block';
        });
    </script>
</body>

</html>