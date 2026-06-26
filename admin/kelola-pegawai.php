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

// Ambil data bidang dan seksi untuk dropdown
$bidang_result = mysqli_query($conn, "SELECT * FROM bidang ORDER BY nama_bidang");
$seksi_result = mysqli_query($conn, "SELECT * FROM seksi ORDER BY nama_seksi");

// Buat array untuk mapping seksi berdasarkan bidang_id
$seksi_by_bidang = [];
while ($seksi = mysqli_fetch_assoc($seksi_result)) {
    $seksi_by_bidang[$seksi['bidang_id']][] = $seksi;
}

// Reset pointer untuk digunakan lagi
mysqli_data_seek($seksi_result, 0);

// Tambah Pegawai
if (isset($_POST['tambah'])) {
    $nama = mysqli_real_escape_string($conn, $_POST['nama']);
    $nip = mysqli_real_escape_string($conn, $_POST['nip']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $jabatan = mysqli_real_escape_string($conn, $_POST['jabatan'] ?? '');
    $bidang_id = mysqli_real_escape_string($conn, $_POST['bidang_id'] ?? '');
    $seksi_id = mysqli_real_escape_string($conn, $_POST['seksi_id'] ?? '');
    $no_hp = mysqli_real_escape_string($conn, $_POST['no_hp'] ?? '');

    // Validasi seksi sesuai bidang
    if (!empty($seksi_id) && !empty($bidang_id)) {
        $check_seksi = mysqli_query($conn, "SELECT id FROM seksi WHERE id = '$seksi_id' AND bidang_id = '$bidang_id'");
        if (mysqli_num_rows($check_seksi) == 0) {
            $_SESSION['error'] = "Seksi yang dipilih tidak sesuai dengan bidang!";
            header("Location: kelola-pegawai.php");
            exit;
        }
    }

    // Cek apakah NIP sudah terdaftar
    $cek_nip = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM users WHERE nip = '$nip'"));

    if ($cek_nip > 0) {
        $_SESSION['error'] = "NIP '$nip' sudah terdaftar!";
    } else {
        // Enkripsi password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $query = "INSERT INTO users (nama, nip, password, role, jabatan, bidang_id, seksi_id, no_hp) 
                  VALUES ('$nama', '$nip', '$hashed_password', '$role', '$jabatan', 
                          " . ($bidang_id ? "'$bidang_id'" : "NULL") . ", 
                          " . ($seksi_id ? "'$seksi_id'" : "NULL") . ", 
                          '$no_hp')";

        if (mysqli_query($conn, $query)) {
            $_SESSION['success'] = "Pegawai berhasil ditambahkan!";
        } else {
            $_SESSION['error'] = "Gagal menambahkan pegawai: " . mysqli_error($conn);
        }
    }

    header("Location: kelola-pegawai.php");
    exit;
}

// Edit Pegawai
if (isset($_POST['edit'])) {
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    $nama = mysqli_real_escape_string($conn, $_POST['nama']);
    $nip = mysqli_real_escape_string($conn, $_POST['nip']);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $jabatan = mysqli_real_escape_string($conn, $_POST['jabatan'] ?? '');
    $bidang_id = mysqli_real_escape_string($conn, $_POST['bidang_id'] ?? '');
    $seksi_id = mysqli_real_escape_string($conn, $_POST['seksi_id'] ?? '');
    $no_hp = mysqli_real_escape_string($conn, $_POST['no_hp'] ?? '');

    // Validasi seksi sesuai bidang
    if (!empty($seksi_id) && !empty($bidang_id)) {
        $check_seksi = mysqli_query($conn, "SELECT id FROM seksi WHERE id = '$seksi_id' AND bidang_id = '$bidang_id'");
        if (mysqli_num_rows($check_seksi) == 0) {
            $_SESSION['error'] = "Seksi yang dipilih tidak sesuai dengan bidang!";
            header("Location: kelola-pegawai.php");
            exit;
        }
    }

    // Cek apakah NIP sudah digunakan oleh user lain
    $cek_nip = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM users WHERE nip = '$nip' AND id != '$id'"));

    if ($cek_nip > 0) {
        $_SESSION['error'] = "NIP '$nip' sudah digunakan oleh pegawai lain!";
    } else {
        // Update password jika diisi
        $password_update = "";
        if (!empty($_POST['password'])) {
            $password = mysqli_real_escape_string($conn, $_POST['password']);
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $password_update = ", password = '$hashed_password'";
        }

        // Handle NULL values
        $bidang_id_value = $bidang_id ? "'$bidang_id'" : "NULL";
        $seksi_id_value = $seksi_id ? "'$seksi_id'" : "NULL";

        $query = "UPDATE users SET 
                  nama = '$nama',
                  nip = '$nip',
                  role = '$role',
                  jabatan = '$jabatan',
                  bidang_id = $bidang_id_value,
                  seksi_id = $seksi_id_value,
                  no_hp = '$no_hp'
                  $password_update
                  WHERE id = '$id'";

        if (mysqli_query($conn, $query)) {
            $_SESSION['success'] = "Data pegawai berhasil diupdate!";
        } else {
            $_SESSION['error'] = "Gagal mengupdate pegawai: " . mysqli_error($conn);
        }
    }

    header("Location: kelola-pegawai.php");
    exit;
}

// Hapus Pegawai
if (isset($_GET['hapus'])) {
    $id = mysqli_real_escape_string($conn, $_GET['hapus']);

    // Cek apakah user sedang memiliki peminjaman aktif
    $cek_peminjaman = mysqli_num_rows(mysqli_query($conn, "
        SELECT id FROM peminjaman 
        WHERE user_id = '$id' 
            AND tanggal_kembali IS NULL
            AND status NOT IN ('Dikembalikan', 'Ditolak', 'Dibatalkan')
    "));

    if ($cek_peminjaman > 0) {
        $_SESSION['error'] = "Pegawai tidak dapat dihapus karena masih memiliki peminjaman aktif!";
    } else {
        // Tidak boleh menghapus diri sendiri
        if ($id == $_SESSION['user']['id']) {
            $_SESSION['error'] = "Anda tidak dapat menghapus akun sendiri!";
        } else {
            if (mysqli_query($conn, "DELETE FROM users WHERE id = '$id'")) {
                $_SESSION['success'] = "Pegawai berhasil dihapus!";
            } else {
                $_SESSION['error'] = "Gagal menghapus pegawai: " . mysqli_error($conn);
            }
        }
    }

    header("Location: kelola-pegawai.php");
    exit;
}

// Ambil data pegawai dengan JOIN ke tabel bidang dan seksi
$pegawai = mysqli_query($conn, "
    SELECT u.*, 
           b.nama_bidang,
           s.nama_seksi,
           (SELECT COUNT(*) FROM peminjaman WHERE user_id = u.id) as total_peminjaman,
           (SELECT COUNT(*) FROM peminjaman 
 WHERE user_id = u.id 
   AND tanggal_kembali IS NULL
   AND status NOT IN ('Ditolak', 'Dibatalkan')) as sedang_dipinjam
    FROM users u
    LEFT JOIN bidang b ON u.bidang_id = b.id
    LEFT JOIN seksi s ON u.seksi_id = s.id
    WHERE u.role IN ('pegawai', 'admin')
    ORDER BY u.role DESC, u.nama ASC
");

// Hitung statistik (tanpa status aktif/nonaktif)
$stats_query = mysqli_query($conn, "
    SELECT 
        COUNT(*) as total_pegawai,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as total_admin,
        SUM(CASE WHEN role = 'pegawai' THEN 1 ELSE 0 END) as total_pegawai_role
    FROM users
    WHERE role IN ('pegawai', 'admin')
");

$stats = mysqli_fetch_assoc($stats_query);

$updated = date('d/m/Y H:i');
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pegawai - Peminjaman Mobil Dinas</title>

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

        .badge-admin {
            background-color: #e74c3c;
            color: white;
        }

        .badge-pegawai {
            background-color: #3498db;
            color: white;
        }

        .user-avatar-sm {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            margin-right: 10px;
        }

        .avatar-admin {
            background-color: #e74c3c;
            color: white;
        }

        .avatar-pegawai {
            background-color: #3498db;
            color: white;
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

        /* .user-details {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
            border-left: 4px solid #0d6efd;
        } */

        .user-details p {
            margin-bottom: 5px;
            font-size: 0.9rem;
        }

        .password-strength {
            height: 5px;
            border-radius: 3px;
            margin-top: 5px;
            transition: all 0.3s;
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

            <a href="kelola-mobil.php" class="nav-link <?= $current == 'kelola-mobil.php' ? '' : '' ?>">
                <i class="fas fa-car me-2"></i> Kelola Mobil
            </a>

            <a href="kelola-pegawai.php" class="nav-link <?= $current == 'kelola-pegawai.php' ? 'active' : '' ?>">
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
                        <i class="fas fa-users me-2"></i> Kelola Pegawai
                    </h1>
                    <p class="text-muted mb-0">Manajemen data pengguna sistem peminjaman mobil</p>
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
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-number"><?= $stats['total_pegawai'] ?? 0 ?></div>
                    <div class="stat-title">Total Pengguna</div>
                    <small class="text-muted">Admin & Pegawai</small>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon text-danger">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="stat-number"><?= $stats['total_admin'] ?? 0 ?></div>
                    <div class="stat-title">Administrator</div>
                    <small class="text-muted">Pengelola sistem</small>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon text-success">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-number"><?= $stats['total_pegawai_role'] ?? 0 ?></div>
                    <div class="stat-title">Pegawai Biasa</div>
                    <small class="text-muted">Pengguna biasa sistem</small>
                </div>
            </div>
        </div>

        <!-- Form Tambah Pegawai -->
        <div class="form-card">
            <h4 class="mb-4">
                <i class="fas fa-user-plus me-2"></i> Tambah Pengguna Baru
            </h4>

            <form method="POST" id="tambahForm">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nama" required
                            placeholder="Masukkan nama lengkap">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">NIP <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nip" required
                            placeholder="Contoh: 197802032006041001">
                        <small class="text-muted">NIP digunakan untuk login</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password" id="password" required
                            placeholder="Minimal 6 karakter" minlength="6">
                        <div class="password-strength" id="passwordStrength"></div>
                        <small class="text-muted">Password akan dienkripsi secara otomatis</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Konfirmasi Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="confirm_password" id="confirm_password" required
                            placeholder="Ketik ulang password">
                        <div class="text-danger small" id="passwordMatch"></div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Role <span class="text-danger">*</span></label>
                        <select class="form-select" name="role" required>
                            <option value="">Pilih Role</option>
                            <option value="pegawai">Pegawai</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Jabatan</label>
                        <input type="text" class="form-control" name="jabatan"
                            placeholder="Contoh: Staff, Kabid, Kasubag">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Nomor HP</label>
                        <input type="text" class="form-control" name="no_hp"
                            placeholder="Contoh: 081234567890">
                    </div>

                    <!-- Bidang dan Seksi -->
                    <div class="col-md-6">
                        <label class="form-label">Bidang</label>
                        <select class="form-select" name="bidang_id" id="bidang_select" onchange="updateSeksiOptions()">
                            <option value="">Pilih Bidang</option>
                            <?php while ($bidang = mysqli_fetch_assoc($bidang_result)): ?>
                                <option value="<?= $bidang['id'] ?>"><?= htmlspecialchars($bidang['nama_bidang']) ?></option>
                            <?php endwhile; ?>
                        </select>
                        <small class="text-muted">Pilih bidang kerja</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Seksi</label>
                        <select class="form-select" name="seksi_id" id="seksi_select" disabled>
                            <option value="">Pilih Seksi</option>
                            <!-- Options akan diisi oleh JavaScript -->
                        </select>
                        <small class="text-muted">Pilih seksi setelah memilih bidang</small>
                    </div>

                    <div class="col-12 mt-3">
                        <button type="submit" name="tambah" class="btn btn-primary px-4">
                            <i class="fas fa-save me-2"></i> Simpan Pengguna
                        </button>
                        <button type="reset" class="btn btn-outline-secondary" onclick="resetSeksiSelect()">
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
                    <i class="fas fa-list me-1"></i> Semua (<?= $stats['total_pegawai'] ?? 0 ?>)
                </button>
                <button class="btn btn-outline-danger filter-btn" data-filter="admin">
                    <i class="fas fa-user-shield me-1"></i> Admin (<?= $stats['total_admin'] ?? 0 ?>)
                </button>
                <button class="btn btn-outline-info filter-btn" data-filter="pegawai">
                    <i class="fas fa-user me-1"></i> Pegawai (<?= $stats['total_pegawai_role'] ?? 0 ?>)
                </button>
            </div>
        </div>

        <!-- Table Card -->
        <div class="table-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i> Daftar Pengguna Sistem
                    <span class="badge bg-primary ms-2"><?= $stats['total_pegawai'] ?? 0 ?> Pengguna</span>
                </h5>

                <!-- TOMBOL CETAK PDF DI UJUNG KANAN -->
                <div>
                    <a href="cetak_pdf_users.php" class="btn btn-danger btn-sm" target="_blank">
                        <i class="fas fa-file-pdf me-1"></i> Cetak PDF
                    </a>
                </div>
            </div>



            <div class="table-responsive">
                <table id="pegawaiTable" class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Pengguna</th>
                            <th>Detail</th>
                            <th>Role</th>
                            <th>Peminjaman</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($pegawai) > 0): ?>
                            <?php $no = 1;
                            while ($p = mysqli_fetch_assoc($pegawai)):
                                $role_class = ($p['role'] == 'admin') ? 'badge-admin' : 'badge-pegawai';
                                $avatar_class = ($p['role'] == 'admin') ? 'avatar-admin' : 'avatar-pegawai';

                                $filter_classes = $p['role'];
                                $is_current_user = ($p['id'] == $_SESSION['user']['id']);
                            ?>
                                <tr class="user-item" data-filter="<?= $filter_classes ?>">
                                    <td><?= $no++ ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar-sm <?= $avatar_class ?>">
                                                <?= strtoupper(substr($p['nama'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <h6 class="mb-1"><?= htmlspecialchars($p['nama']) ?></h6>
                                                <small class="text-muted">NIP: <?= htmlspecialchars($p['nip']) ?></small>
                                                <?php if ($is_current_user): ?>
                                                    <br><small class="text-primary"><i class="fas fa-user me-1"></i> Anda</small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="user-details">
                                            <p class="mb-1">
                                                <strong>Jabatan:</strong> <?= $p['jabatan'] ?: '-' ?>
                                            </p>
                                            <p class="mb-1">
                                                <strong>Bidang:</strong> <?= $p['nama_bidang'] ?: '-' ?>
                                            </p>
                                            <p class="mb-1">
                                                <strong>Seksi:</strong> <?= $p['nama_seksi'] ?: '-' ?>
                                            </p>
                                            <p class="mb-1">
                                                <strong>HP:</strong> <?= $p['no_hp'] ?: '-' ?>
                                            </p>
                                            <!-- <p class="mb-0">
                                                <strong>Bergabung:</strong> <?= date('d/m/Y', strtotime($p['created_at'])) ?>
                                            </p> -->
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?= $role_class ?>">
                                            <i class="fas fa-<?= ($p['role'] == 'admin') ? 'user-shield' : 'user' ?> me-1"></i>
                                            <?= ucfirst($p['role']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="text-center">
                                            <div class="mb-2">
                                                <i class="fas fa-car fa-lg text-primary"></i>
                                            </div>
                                            <div>
                                                <strong><?= $p['total_peminjaman'] ?></strong> total
                                                <br>
                                                <small class="text-muted">
                                                    <?= $p['sedang_dipinjam'] ?> aktif
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-sm btn-outline-primary"
                                                onclick="editUser(<?= $p['id'] ?>)"
                                                title="Edit Pengguna" <?= $is_current_user ? 'disabled' : '' ?>>
                                                <i class="fas fa-edit"></i>
                                            </button>

                                            <a href="?hapus=<?= $p['id'] ?>"
                                                class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm('Hapus pengguna <?= addslashes($p['nama']) ?>?\nNIP: <?= addslashes($p['nip']) ?>\n\nPastikan pengguna tidak memiliki peminjaman aktif.')"
                                                title="Hapus Pengguna" <?= $is_current_user ? 'disabled' : '' ?>>
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">Belum ada data pengguna</h5>
                                    <p class="text-muted">Silakan tambahkan pengguna baru menggunakan form di atas</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Notes -->
        <div class="alert alert-info">
            <h6><i class="fas fa-info-circle me-2"></i> Catatan Penting:</h6>
            <ul class="mb-0">
                <li>Administrator memiliki akses penuh ke semua fitur sistem</li>
                <li>Pegawai hanya dapat mengajukan peminjaman dan melihat riwayat</li>
                <li>Tidak dapat menghapus akun sendiri</li>
                <li>Pengguna dengan peminjaman aktif tidak dapat dihapus</li>
                <li>Seksi harus sesuai dengan bidang yang dipilih</li>
                <li>Semua pengguna aktif secara default</li>
            </ul>
        </div>

        <!-- Footer -->
        <footer class="mt-5 pt-3 text-center text-muted">
            <p class="mb-1">
                <small>Copyright &copy; Sistem Peminjaman Mobil Dinas <?= date('Y') ?></small>
            </p>
            <p class="mb-0">
                <small>Kelola pegawai | Total <?= $stats['total_pegawai'] ?? 0 ?> pengguna | Update: <?= $updated ?></small>
            </p>
        </footer>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Pengguna</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="editModalBody">
                    <p>Loading data pengguna...</p>
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
        // Data seksi dari PHP
        const seksiByBidang = <?= json_encode($seksi_by_bidang) ?>;

        // Initialize DataTable
        $(document).ready(function() {
            $('#pegawaiTable').DataTable({
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

        // Fungsi untuk update opsi seksi berdasarkan bidang
        function updateSeksiOptions() {
            const bidangSelect = document.getElementById('bidang_select');
            const seksiSelect = document.getElementById('seksi_select');
            const bidangId = bidangSelect.value;

            // Reset seksi select
            seksiSelect.innerHTML = '<option value="">Pilih Seksi</option>';
            seksiSelect.disabled = !bidangId;

            if (bidangId && seksiByBidang[bidangId]) {
                seksiByBidang[bidangId].forEach(seksi => {
                    const option = document.createElement('option');
                    option.value = seksi.id;
                    option.textContent = seksi.nama_seksi;
                    seksiSelect.appendChild(option);
                });
                seksiSelect.disabled = false;
            }
        }

        // Reset seksi select
        function resetSeksiSelect() {
            const seksiSelect = document.getElementById('seksi_select');
            seksiSelect.innerHTML = '<option value="">Pilih Seksi</option>';
            seksiSelect.disabled = true;
        }

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
                const items = document.querySelectorAll('.user-item');

                items.forEach(item => {
                    const filters = item.getAttribute('data-filter').split(' ');
                    if (filter === 'all' || filters.includes(filter)) {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
                });

                // Update DataTable
                $('#pegawaiTable').DataTable().draw();
            });
        });

        // Password strength indicator
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const passwordStrength = document.getElementById('passwordStrength');
        const passwordMatch = document.getElementById('passwordMatch');

        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;

            // Check password strength
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
            if (password.match(/\d/)) strength++;
            if (password.match(/[^a-zA-Z\d]/)) strength++;

            // Update strength indicator
            const colors = ['#dc3545', '#ffc107', '#fd7e14', '#20c997', '#198754'];
            const texts = ['Sangat Lemah', 'Lemah', 'Cukup', 'Kuat', 'Sangat Kuat'];

            passwordStrength.style.width = (strength * 20) + '%';
            passwordStrength.style.backgroundColor = colors[strength];
            passwordStrength.title = texts[strength];

            // Check password match
            checkPasswordMatch();
        });

        confirmPasswordInput.addEventListener('input', checkPasswordMatch);

        function checkPasswordMatch() {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;

            if (confirmPassword === '') {
                passwordMatch.textContent = '';
            } else if (password === confirmPassword) {
                passwordMatch.textContent = '✓ Password cocok';
                passwordMatch.className = 'text-success small';
            } else {
                passwordMatch.textContent = '✗ Password tidak cocok';
                passwordMatch.className = 'text-danger small';
            }
        }

        // Form validation
        document.getElementById('tambahForm').addEventListener('submit', function(e) {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;

            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Password dan konfirmasi password tidak cocok!');
                return false;
            }

            if (password.length < 6) {
                e.preventDefault();
                alert('Password minimal 6 karakter!');
                return false;
            }

            // Validasi seksi sesuai bidang
            const bidangId = document.getElementById('bidang_select').value;
            const seksiId = document.getElementById('seksi_select').value;

            if (seksiId && !bidangId) {
                e.preventDefault();
                alert('Pilih bidang terlebih dahulu sebelum memilih seksi!');
                return false;
            }

            return true;
        });

        // Edit user function dengan AJAX
        function editUser(id) {
            // Tampilkan loading
            document.getElementById('editModalBody').innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Memuat data pengguna...</p>
                </div>
            `;

            // Tampilkan modal
            const modal = new bootstrap.Modal(document.getElementById('editModal'));
            modal.show();

            // Ambil data user via AJAX
            $.ajax({
                url: 'get-user-data.php',
                type: 'GET',
                data: {
                    id: id
                },
                dataType: 'json',
                success: function(data) {
                    if (data.success) {
                        // Buat form edit
                        let formHtml = `
                            <form method="POST" action="kelola-pegawai.php" id="editForm">
                                <input type="hidden" name="id" value="${data.user.id}">
                                <input type="hidden" name="edit" value="1">
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="nama" value="${data.user.nama}" required>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">NIP <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="nip" value="${data.user.nip}" required>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Password (Biarkan kosong jika tidak diubah)</label>
                                        <input type="password" class="form-control" name="password" id="editPassword" placeholder="Kosongkan jika tidak diubah">
                                        <small class="text-muted">Minimal 6 karakter</small>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Konfirmasi Password</label>
                                        <input type="password" class="form-control" name="confirm_password" id="editConfirmPassword" placeholder="Ketik ulang password">
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">Role <span class="text-danger">*</span></label>
                                        <select class="form-select" name="role" required>
                                            <option value="pegawai" ${data.user.role === 'pegawai' ? 'selected' : ''}>Pegawai</option>
                                            <option value="admin" ${data.user.role === 'admin' ? 'selected' : ''}>Administrator</option>
                                        </select>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">Jabatan</label>
                                        <input type="text" class="form-control" name="jabatan" value="${data.user.jabatan || ''}">
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label">Nomor HP</label>
                                        <input type="text" class="form-control" name="no_hp" value="${data.user.no_hp || ''}">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Bidang</label>
                                        <select class="form-select" name="bidang_id" id="editBidangSelect" onchange="updateEditSeksiOptions()">
                                            <option value="">Pilih Bidang</option>
                        `;

                        // Tambahkan opsi bidang (kita perlu mengembalikan pointer ke awal)
                        <?php
                        mysqli_data_seek($bidang_result, 0);
                        while ($bidang = mysqli_fetch_assoc($bidang_result)): ?>
                            formHtml += `<option value="<?= $bidang['id'] ?>" ${data.user.bidang_id == <?= $bidang['id'] ?> ? 'selected' : ''}><?= addslashes($bidang['nama_bidang']) ?></option>`;
                        <?php endwhile; ?>

                        formHtml += `
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Seksi</label>
                                        <select class="form-select" name="seksi_id" id="editSeksiSelect">
                                            <option value="">Pilih Seksi</option>
                        `;

                        // Tambahkan opsi seksi berdasarkan bidang
                        if (data.user.bidang_id && seksiByBidang[data.user.bidang_id]) {
                            seksiByBidang[data.user.bidang_id].forEach(seksi => {
                                const selected = data.user.seksi_id == seksi.id ? 'selected' : '';
                                formHtml += `<option value="${seksi.id}" ${selected}>${seksi.nama_seksi}</option>`;
                            });
                        }

                        formHtml += `
                                        </select>
                                    </div>

                                    <div class="col-12 mt-4">
                                        <div class="d-flex justify-content-end">
                                            <button type="button" class="btn btn-outline-secondary me-2" data-bs-dismiss="modal">
                                                Batal
                                            </button>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save me-2"></i> Simpan Perubahan
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        `;

                        document.getElementById('editModalBody').innerHTML = formHtml;

                        // Enable/disable seksi select berdasarkan bidang
                        const editBidangSelect = document.getElementById('editBidangSelect');
                        const editSeksiSelect = document.getElementById('editSeksiSelect');
                        editSeksiSelect.disabled = !editBidangSelect.value;

                        // Validasi form edit
                        document.getElementById('editForm').addEventListener('submit', function(e) {
                            const editPassword = document.getElementById('editPassword').value;
                            const editConfirmPassword = document.getElementById('editConfirmPassword').value;

                            if (editPassword && editPassword.length < 6) {
                                e.preventDefault();
                                alert('Password minimal 6 karakter!');
                                return false;
                            }

                            if (editPassword && editPassword !== editConfirmPassword) {
                                e.preventDefault();
                                alert('Password dan konfirmasi password tidak cocok!');
                                return false;
                            }

                            return true;
                        });
                    } else {
                        document.getElementById('editModalBody').innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                ${data.message || 'Gagal memuat data pengguna'}
                            </div>
                            <div class="text-center mt-3">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    Tutup
                                </button>
                            </div>
                        `;
                    }
                },
                error: function() {
                    document.getElementById('editModalBody').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Terjadi kesalahan saat mengambil data
                        </div>
                        <div class="text-center mt-3">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                Tutup
                            </button>
                        </div>
                    `;
                }
            });
        }

        // Fungsi untuk update seksi di modal edit
        function updateEditSeksiOptions() {
            const bidangSelect = document.getElementById('editBidangSelect');
            const seksiSelect = document.getElementById('editSeksiSelect');
            const bidangId = bidangSelect.value;

            // Reset seksi select
            seksiSelect.innerHTML = '<option value="">Pilih Seksi</option>';
            seksiSelect.disabled = !bidangId;

            if (bidangId && seksiByBidang[bidangId]) {
                seksiByBidang[bidangId].forEach(seksi => {
                    const option = document.createElement('option');
                    option.value = seksi.id;
                    option.textContent = seksi.nama_seksi;
                    seksiSelect.appendChild(option);
                });
                seksiSelect.disabled = false;
            }
        }

        // Auto refresh setiap 2 menit
        setTimeout(function() {
            location.reload();
        }, 120000);

        // Generate random password
        function generatePassword() {
            const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            let password = '';
            for (let i = 0; i < 8; i++) {
                password += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            passwordInput.value = password;
            confirmPasswordInput.value = password;
            passwordInput.dispatchEvent(new Event('input'));
        }

        // Add generate password button
        const passwordField = document.querySelector('input[name="password"]');
        const generateBtn = document.createElement('button');
        generateBtn.type = 'button';
        generateBtn.className = 'btn btn-sm btn-outline-secondary mt-1';
        generateBtn.innerHTML = '<i class="fas fa-key me-1"></i> Generate Password';
        generateBtn.onclick = generatePassword;
        passwordField.parentNode.appendChild(generateBtn);
    </script>
</body>

</html>