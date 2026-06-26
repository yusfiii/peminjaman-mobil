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

// Tambah Mobil
if (isset($_POST['tambah'])) {
    $nama = mysqli_real_escape_string($conn, $_POST['nama_mobil']);
    $plat = mysqli_real_escape_string($conn, $_POST['nomor_plat']);
    $tahun = mysqli_real_escape_string($conn, $_POST['tahun'] ?? '');
    $warna = mysqli_real_escape_string($conn, $_POST['warna'] ?? '');
    $kapasitas = mysqli_real_escape_string($conn, $_POST['kapasitas'] ?? '');
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan'] ?? '');

    // Upload foto
    $fotoName = null;
    if (!empty($_FILES['foto']['name'])) {
        $fotoName = time() . "_" . basename($_FILES['foto']['name']);
        $target_dir = "../uploads/";
        $target_file = $target_dir . $fotoName;

        // Validasi file
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($imageFileType, $allowed_types)) {
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $target_file)) {
                // Success
            } else {
                $_SESSION['error'] = "Gagal mengupload foto.";
            }
        } else {
            $_SESSION['error'] = "Format file tidak didukung. Gunakan JPG, JPEG, PNG, atau GIF.";
        }
    }

    $query = "INSERT INTO mobil (nama_mobil, nomor_plat, tahun, warna, kapasitas, keterangan, foto, status, dipakai_oleh) 
              VALUES ('$nama', '$plat', '$tahun', '$warna', '$kapasitas', '$keterangan', '$fotoName', 'Tersedia', NULL)";

    if (mysqli_query($conn, $query)) {
        $_SESSION['success'] = "Mobil berhasil ditambahkan!";
    } else {
        $_SESSION['error'] = "Gagal menambahkan mobil: " . mysqli_error($conn);
    }

    header("Location: kelola-mobil.php");
    exit;
}

// Edit Mobil
if (isset($_POST['edit'])) {
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    $nama = mysqli_real_escape_string($conn, $_POST['nama_mobil']);
    $plat = mysqli_real_escape_string($conn, $_POST['nomor_plat']);
    $tahun = mysqli_real_escape_string($conn, $_POST['tahun'] ?? '');
    $warna = mysqli_real_escape_string($conn, $_POST['warna'] ?? '');
    $kapasitas = mysqli_real_escape_string($conn, $_POST['kapasitas'] ?? '');
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan'] ?? '');

    // Upload foto baru jika ada
    if (!empty($_FILES['foto']['name'])) {
        $fotoName = time() . "_" . basename($_FILES['foto']['name']);
        $target_dir = "../uploads/";
        $target_file = $target_dir . $fotoName;

        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($imageFileType, $allowed_types)) {
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $target_file)) {
                // Hapus foto lama jika ada
                $old_foto = mysqli_fetch_assoc(mysqli_query($conn, "SELECT foto FROM mobil WHERE id = '$id'"))['foto'];
                if ($old_foto && file_exists("../uploads/" . $old_foto)) {
                    unlink("../uploads/" . $old_foto);
                }

                $foto_update = ", foto = '$fotoName'";
            }
        }
    } else {
        $foto_update = "";
    }

    $query = "UPDATE mobil SET 
              nama_mobil = '$nama', 
              nomor_plat = '$plat',
              tahun = '$tahun',
              warna = '$warna',
              kapasitas = '$kapasitas',
              keterangan = '$keterangan'
              $foto_update
              WHERE id = '$id'";

    if (mysqli_query($conn, $query)) {
        $_SESSION['success'] = "Data mobil berhasil diupdate!";
    } else {
        $_SESSION['error'] = "Gagal mengupdate mobil: " . mysqli_error($conn);
    }

    header("Location: kelola-mobil.php");
    exit;
}

// Hapus Mobil
if (isset($_GET['hapus'])) {
    $id = mysqli_real_escape_string($conn, $_GET['hapus']);

    // Hapus foto jika ada
    $mobil = mysqli_fetch_assoc(mysqli_query($conn, "SELECT foto FROM mobil WHERE id = '$id'"));
    if ($mobil['foto'] && file_exists("../uploads/" . $mobil['foto'])) {
        unlink("../uploads/" . $mobil['foto']);
    }

    // Cek apakah mobil sedang dipinjam
    $cek_peminjaman = mysqli_num_rows(mysqli_query($conn, "
        SELECT id FROM peminjaman 
        WHERE mobil_id = '$id' AND tanggal_kembali IS NULL
    "));

    if ($cek_peminjaman > 0) {
        $_SESSION['error'] = "Mobil tidak dapat dihapus karena sedang dipinjam!";
    } else {
        if (mysqli_query($conn, "DELETE FROM mobil WHERE id = '$id'")) {
            $_SESSION['success'] = "Mobil berhasil dihapus!";
        } else {
            $_SESSION['error'] = "Gagal menghapus mobil: " . mysqli_error($conn);
        }
    }

    header("Location: kelola-mobil.php");
    exit;
}

// Ambil data mobil
$mobil = mysqli_query($conn, "
    SELECT m.*, 
           COALESCE(u.nama, '-') as peminjam,
           (SELECT COUNT(*) FROM peminjaman WHERE mobil_id = m.id AND status = 'Dikembalikan') as total_peminjaman
    FROM mobil m
    LEFT JOIN peminjaman p ON m.id = p.mobil_id AND p.tanggal_kembali IS NULL
    LEFT JOIN users u ON p.user_id = u.id
    ORDER BY m.nama_mobil ASC
");

// Hitung statistik
$stats_query = mysqli_query($conn, "
    SELECT 
        COUNT(*) as total_mobil,
        SUM(CASE WHEN status = 'Tersedia' THEN 1 ELSE 0 END) as tersedia,
        SUM(CASE WHEN status = 'Dipinjam' THEN 1 ELSE 0 END) as dipinjam
    FROM mobil
");

$stats = mysqli_fetch_assoc($stats_query);

$updated = date('d/m/Y H:i');
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Mobil - Peminjaman Mobil Dinas</title>

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

        .form-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .table-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .badge-tersedia {
            background-color: #198754;
            color: white;
        }

        .badge-dipinjam {
            background-color: #ffc107;
            color: #000;
        }

        .badge-perbaikan {
            background-color: #dc3545;
            color: white;
        }

        .mobil-thumb {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #e0e0e0;
            cursor: pointer;
            transition: transform 0.3s;
        }

        .mobil-thumb:hover {
            transform: scale(1.1);
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
        }

        .update-time {
            font-size: 0.8rem;
            color: #95a5a6;
        }

        .filter-buttons .btn {
            margin-right: 5px;
            margin-bottom: 5px;
        }

        .car-details {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
            border-left: 4px solid #0d6efd;
        }

        .car-details p {
            margin-bottom: 5px;
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

            .action-buttons .btn {
                width: 100%;
                margin-bottom: 5px;
            }

            .form-card {
                padding: 20px;
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

            <a href="kelola-mobil.php" class="nav-link <?= $current == 'kelola-mobil.php' ? 'active' : '' ?>">
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
                        <i class="fas fa-car me-2"></i> Kelola Mobil Dinas
                    </h1>
                    <p class="text-muted mb-0">Manajemen data kendaraan dinas</p>
                </div>
                <div class="update-time">
                    <i class="fas fa-clock me-1"></i> Waktu Server: <?= date('H:i:s') ?>
                    <br>
                    <i class="fas fa-sync-alt me-1"></i> Update: <?= $updated ?>
                </div>
            </div>
        </div>

        <!-- Notifications -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?= htmlspecialchars($_SESSION['success']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?= htmlspecialchars($_SESSION['error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon text-primary">
                        <i class="fas fa-car"></i>
                    </div>
                    <div class="stat-number"><?= $stats['total_mobil'] ?? 0 ?></div>
                    <div class="stat-title">Total Mobil</div>
                    <small class="text-muted">Keseluruhan kendaraan</small>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon text-success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-number"><?= $stats['tersedia'] ?? 0 ?></div>
                    <div class="stat-title">Mobil Tersedia</div>
                    <small class="text-muted">Siap untuk dipinjam</small>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon text-warning">
                        <i class="fas fa-road"></i>
                    </div>
                    <div class="stat-number"><?= $stats['dipinjam'] ?? 0 ?></div>
                    <div class="stat-title">Sedang Dipinjam</div>
                    <small class="text-muted">Dalam penggunaan</small>
                </div>
            </div>
        </div>

        <!-- Form Tambah Mobil -->
        <div class="form-card">
            <h4 class="mb-4">
                <i class="fas fa-plus-circle me-2"></i> Tambah Mobil Baru
            </h4>

            <form method="POST" enctype="multipart/form-data">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nama Mobil <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama_mobil" required
                            placeholder="Contoh: Toyota Avanza, Honda Civic">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Nomor Plat <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nomor_plat" required
                            placeholder="Contoh: B 1234 ABC">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Tahun</label>
                        <input type="number" class="form-control" name="tahun"
                            placeholder="Contoh: 2023" min="2000" max="2026">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Warna</label>
                        <input type="text" class="form-control" name="warna"
                            placeholder="Contoh: Hitam, Putih">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Kapasitas</label>
                        <input type="number" class="form-control" name="kapasitas"
                            placeholder="Jumlah penumpang">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Foto Mobil</label>
                        <input type="file" class="form-control" name="foto" accept="image/*">
                        <small class="text-muted">Format: JPG, PNG, GIF (Maks. 2MB)</small>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Keterangan Tambahan</label>
                        <textarea class="form-control" name="keterangan" rows="3"
                            placeholder="Kondisi mobil, fitur khusus, catatan penting..."></textarea>
                    </div>

                    <div class="col-12 mt-3">
                        <button type="submit" name="tambah" class="btn btn-primary px-4">
                            <i class="fas fa-save me-2"></i> Simpan Mobil
                        </button>
                        <button type="reset" class="btn btn-outline-secondary">
                            <i class="fas fa-undo me-2"></i> Reset Form
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Filter & Search -->
        <div class="mb-3">
            <div class="filter-buttons">
                <button class="btn btn-outline-primary filter-btn active" data-filter="all">
                    <i class="fas fa-list me-1"></i> Semua (<?= $stats['total_mobil'] ?? 0 ?>)
                </button>
                <button class="btn btn-outline-success filter-btn" data-filter="tersedia">
                    <i class="fas fa-check me-1"></i> Tersedia (<?= $stats['tersedia'] ?? 0 ?>)
                </button>
                <button class="btn btn-outline-warning filter-btn" data-filter="dipinjam">
                    <i class="fas fa-road me-1"></i> Dipinjam (<?= $stats['dipinjam'] ?? 0 ?>)
                </button>
            </div>
        </div>

        <!-- Table Card -->
        <div class="table-card">
            <h5 class="mb-3">
                <i class="fas fa-list me-2"></i> Daftar Mobil Dinas
                <span class="badge bg-primary ms-2"><?= $stats['total_mobil'] ?? 0 ?> Unit</span>
            </h5>

            <div class="table-responsive">
                <table id="mobilTable" class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Mobil</th>
                            <th>Detail</th>
                            <th>Status</th>
                            <th>Peminjam</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($mobil) > 0): ?>
                            <?php $no = 1;
                            while ($m = mysqli_fetch_assoc($mobil)):
                                $status_class = ($m['status'] == 'Tersedia') ? 'badge-tersedia' : 'badge-dipinjam';
                                $status_text = ($m['status'] == 'Tersedia') ? 'Tersedia' : 'Dipinjam';
                                $filter_class = strtolower($status_text);
                            ?>
                                <tr class="mobil-item" data-status="<?= $filter_class ?>">
                                    <td><?= $no++ ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($m['foto'])): ?>
                                                <img src="../uploads/<?= $m['foto'] ?>"
                                                    class="mobil-thumb me-3"
                                                    alt="<?= htmlspecialchars($m['nama_mobil']) ?>"
                                                    onclick="showImageModal('../uploads/<?= $m['foto'] ?>', '<?= htmlspecialchars($m['nama_mobil']) ?>')">
                                            <?php else: ?>
                                                <div class="no-photo-thumb me-3">
                                                    <i class="fas fa-car"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <h6 class="mb-1"><?= htmlspecialchars($m['nama_mobil']) ?></h6>
                                                <small class="text-muted">ID: <?= $m['id'] ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="car-details">
                                            <p class="mb-1">
                                                <strong>Plat:</strong>
                                                <span class="badge bg-secondary"><?= htmlspecialchars($m['nomor_plat']) ?></span>
                                            </p>
                                            <p class="mb-1">
                                                <strong>Tahun:</strong> <?= $m['tahun'] ?: '-' ?>
                                            </p>
                                            <p class="mb-1">
                                                <strong>Warna:</strong> <?= $m['warna'] ?: '-' ?>
                                            </p>
                                            <p class="mb-0">
                                                <strong>Kapasitas:</strong> <?= $m['kapasitas'] ? $m['kapasitas'] . ' orang' : '-' ?>
                                            </p>
                                            <?php if ($m['total_peminjaman'] > 0): ?>
                                                <p class="mb-0 mt-2">
                                                    <small class="text-info">
                                                        <i class="fas fa-history me-1"></i>
                                                        Telah dipinjam <?= $m['total_peminjaman'] ?> kali
                                                    </small>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?= $status_class ?>">
                                            <?php if ($m['status'] == 'Tersedia'): ?>
                                                <i class="fas fa-check-circle me-1"></i>
                                            <?php else: ?>
                                                <i class="fas fa-road me-1"></i>
                                            <?php endif; ?>
                                            <?= $status_text ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($m['status'] == 'Dipinjam' && $m['peminjam']): ?>
                                            <div class="text-center">
                                                <i class="fas fa-user text-primary mb-2" style="font-size: 1.5rem;"></i>
                                                <br>
                                                <strong><?= htmlspecialchars($m['peminjam']) ?></strong>
                                                <br>
                                                <small class="text-muted">Sedang digunakan</small>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-sm btn-outline-primary"
                                                onclick="editMobil(<?= $m['id'] ?>)"
                                                title="Edit Mobil">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="?hapus=<?= $m['id'] ?>"
                                                class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm('Hapus mobil <?= addslashes($m['nama_mobil']) ?>?\nPlat: <?= addslashes($m['nomor_plat']) ?>\n\nPastikan mobil tidak sedang dipinjam.')"
                                                title="Hapus Mobil">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <i class="fas fa-car fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">Belum ada data mobil</h5>
                                    <p class="text-muted">Silakan tambahkan mobil baru menggunakan form di atas</p>
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
                <small>Kelola mobil dinas | Total <?= $stats['total_mobil'] ?? 0 ?> unit | Update: <?= $updated ?></small>
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
            $('#mobilTable').DataTable({
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
                const items = document.querySelectorAll('.mobil-item');

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
                $('#mobilTable').DataTable().draw();
            });
        });

        // Show image in modal
        function showImageModal(imageSrc, mobilName) {
            document.getElementById('modalImage').src = imageSrc;
            document.getElementById('modalTitle').textContent = 'Foto: ' + mobilName;
            const modal = new bootstrap.Modal(document.getElementById('imageModal'));
            modal.show();
        }

        // Edit mobil function (placeholder - bisa dikembangkan)
        function editMobil(id) {
            alert('Fitur edit mobil akan segera tersedia!\nMobil ID: ' + id);
            // Di sini bisa diimplementasikan modal untuk edit mobil
        }

        // Auto refresh setiap 2 menit
        setTimeout(function() {
            location.reload();
        }, 120000);

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const fileInput = document.querySelector('input[name="foto"]');
            if (fileInput.files.length > 0) {
                const fileSize = fileInput.files[0].size / 1024 / 1024; // in MB
                if (fileSize > 2) {
                    e.preventDefault();
                    alert('Ukuran file foto maksimal 2MB!');
                    return false;
                }
            }
            return true;
        });
    </script>
</body>

</html>