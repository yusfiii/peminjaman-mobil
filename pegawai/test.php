<?php
session_start();
include "../config/database.php";

// Proteksi login
if (!isset($_SESSION['user'])) {
    header("Location: ../auth/login.php");
    exit;
}

$user = $_SESSION['user'];
$user_id = (int)$user['id'];
$user_name = $user['nama'] ?? 'User';
$user_role = $user['role'] ?? 'pegawai';

$now = time(); // Waktu server saat ini
date_default_timezone_set('Asia/Makassar'); // Sesuaikan dengan zona waktu lokal

/* =======================
   PROSES SUBMIT FORM
======================= */
$success_msg = '';
$error_msg = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $mobil_id     = mysqli_real_escape_string($conn, $_POST['mobil_id']);
    $tanggal      = mysqli_real_escape_string($conn, $_POST['tanggal_pinjam']);
    $jam_mulai    = date('H:i', strtotime($_POST['jam_mulai']));
    $jam_selesai  = date('H:i', strtotime($_POST['jam_selesai']));
    $keperluan    = mysqli_real_escape_string($conn, $_POST['keperluan']);

    // Aturan jam kerja
    $jam_kerja_mulai   = '07:00';
    $jam_kerja_selesai = '17:00';

    if (
        $jam_mulai < $jam_kerja_mulai ||
        $jam_mulai > $jam_kerja_selesai ||
        $jam_selesai < $jam_kerja_mulai ||
        $jam_selesai > $jam_kerja_selesai
    ) {
        $error_msg = 'Peminjaman hanya diperbolehkan pada jam kerja (07:00 - 17:00)';
    } elseif ($jam_selesai <= $jam_mulai) {
        $error_msg = 'Jam selesai harus lebih besar dari jam mulai';
    } else {
        // ===============================
        // VALIDASI BENTROK JADWAL MOBIL
        // ===============================
        $bentrok = mysqli_num_rows(mysqli_query($conn, "
            SELECT id FROM peminjaman
            WHERE mobil_id = '$mobil_id'
              AND tanggal_pinjam = '$tanggal'
              AND tanggal_kembali IS NULL
              AND (
                    ('$jam_mulai' BETWEEN jam_mulai AND jam_selesai)
                    OR
                    ('$jam_selesai' BETWEEN jam_mulai AND jam_selesai)
                    OR
                    (jam_mulai BETWEEN '$jam_mulai' AND '$jam_selesai')
                  )
        "));

        if ($bentrok > 0) {
            $error_msg = 'Mobil sudah dipinjam pada jam tersebut. Silakan pilih jam lain.';
        } else {
            // Validasi 1 pegawai 1 peminjaman aktif
            $cek = mysqli_num_rows(mysqli_query($conn, "
                SELECT id FROM peminjaman
                WHERE user_id = '$user_id'
                    AND tanggal_kembali IS NULL
                    AND status NOT IN ('Ditolak', 'Dibatalkan', 'Dikembalikan')
                    -- TAMBAHKAN FILTER INI
            "));

            if ($cek > 0) {
                $error_msg = 'Anda masih memiliki peminjaman aktif! Silakan selesaikan peminjaman sebelumnya.';
            } else {
                // Insert peminjaman
                $insert = mysqli_query($conn, "
                    INSERT INTO peminjaman
                    (user_id, mobil_id, tanggal_pinjam, jam_mulai, jam_selesai, keperluan, status)
                    VALUES
                    ('$user_id', '$mobil_id', '$tanggal', '$jam_mulai', '$jam_selesai', '$keperluan', 'Menunggu ACC')
                ");

                if ($insert) {
                    $success_msg = 'Peminjaman berhasil diajukan! Menunggu persetujuan admin.';

                    // Notifikasi admin
                    mysqli_query($conn, "
    INSERT INTO notifikasi (role, pesan)
    VALUES ('admin', 'Pengajuan peminjaman baru dari {$user['nama']}')
");
                } else {
                    $error_msg = 'Gagal mengajukan peminjaman! Silakan coba lagi.';
                }
            }
        }
    }
}

// Ambil data mobil yang tersedia
$mobil = mysqli_query($conn, "
    SELECT m.*
    FROM mobil m
    WHERE m.status = 'Tersedia'
      AND NOT EXISTS (
          SELECT 1 FROM peminjaman p
          WHERE p.mobil_id = m.id
            AND p.tanggal_kembali IS NULL
            AND p.status NOT IN ('Ditolak', 'Dibatalkan', 'Dikembalikan')
      )
    ORDER BY m.nama_mobil ASC
");

// Ambil peminjaman aktif user
$peminjaman_aktif = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT p.*, m.nama_mobil, m.nomor_plat
    FROM peminjaman p
    JOIN mobil m ON p.mobil_id = m.id
    WHERE p.user_id = '$user_id'
      AND p.tanggal_kembali IS NULL
      AND p.status NOT IN ('Dikembalikan', 'Ditolak', 'Dibatalkan')
      -- TAMBAHKAN FILTER STATUS INI
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
    <title>Ajukan Peminjaman - Peminjaman Mobil Dinas</title>

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

        .form-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .mobil-list {
            color: #2c3e50;
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            background-color: #f8f9fa;
        }

        .mobil-item {
            padding: 10px;
            border-bottom: 1px solid #e0e0e0;
            transition: background-color 0.3s;
        }

        .mobil-item:hover {
            background-color: #e9ecef;
        }

        .mobil-item:last-child {
            border-bottom: none;
        }

        .rules-box {
            background-color: #f8f9fa;
            border-left: 4px solid #3498db;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .rules-box ul {
            margin-bottom: 0;
            padding-left: 20px;
        }

        .rules-box li {
            margin-bottom: 5px;
        }

        .update-time {
            font-size: 0.8rem;
            color: #95a5a6;
        }

        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .form-control,
        .form-select {
            border-radius: 8px;
            border: 1px solid #ddd;
            padding: 10px 15px;
            transition: all 0.3s;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }

        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
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

            <a href="dashboard.php" class="nav-link <?= $current == 'dashboard.php' ? '' : '' ?>">
                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
            </a>

            <a href="mobil.php" class="nav-link <?= $current == 'mobil.php' ? '' : '' ?>">
                <i class="fas fa-car me-2"></i> Status Mobil
            </a>

            <a href="peminjaman.php" class="nav-link <?= $current == 'peminjaman.php' ? 'active' : '' ?>">
                <i class="fas fa-file-alt me-2"></i> Ajukan Peminjaman
            </a>

            <a href="struk.php" class="nav-link <?= $current == 'struk.php' ? '' : '' ?>">
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
                    <h1 class="h3 mb-2">
                        <i class="fas fa-file-alt me-2"></i> Ajukan Peminjaman Mobil
                    </h1>
                    <p class="text-muted mb-0">Isi form untuk mengajukan peminjaman mobil dinas</p>
                </div>
                <div class="update-time">
                    <i class="fas fa-clock me-1"></i> Waktu Server: <?= date('H:i:s') ?>
                    <br>
                    <i class="fas fa-sync-alt me-1"></i> Update: <?= $updated ?>
                </div>
            </div>
        </div>

        <!-- Notifikasi -->
        <?php if ($success_msg): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?= htmlspecialchars($success_msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?= htmlspecialchars($error_msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Peminjaman Aktif Warning -->
        <?php if ($peminjaman_aktif): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                    <div>
                        <h5 class="alert-heading mb-2">Anda memiliki peminjaman aktif!</h5>
                        <p class="mb-1">
                            <strong><?= htmlspecialchars($peminjaman_aktif['nama_mobil']) ?></strong>
                            (<?= htmlspecialchars($peminjaman_aktif['nomor_plat']) ?>)
                        </p>
                        <p class="mb-0">
                            Status: <span class="badge bg-warning text-dark"><?= htmlspecialchars($peminjaman_aktif['status']) ?></span>
                            | Tanggal: <?= htmlspecialchars($peminjaman_aktif['tanggal_pinjam']) ?>
                        </p>
                        <p class="mb-0 mt-2">
                            <a href="struk.php" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye me-1"></i> Lihat Detail
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Rules & Guidelines -->
        <div class="rules-box">
            <h6 class="mb-3"><i class="fas fa-info-circle me-2 text-primary"></i>Ketentuan Peminjaman:</h6>
            <ul class="text-muted">
                <li>Peminjaman hanya diperbolehkan pada jam kerja (07:00 - 17:00)</li>
                <li>Setiap pegawai hanya dapat meminjam 1 mobil dalam waktu yang bersamaan</li>
                <li>Pastikan mobil yang dipilih tersedia pada tanggal dan jam yang diinginkan</li>
                <li>Pengajuan akan diproses setelah mendapatkan persetujuan admin</li>
                <li>Pengembalian mobil tepat waktu untuk menghindari sanksi</li>
            </ul>
        </div>

        <!-- Mobil yang Tersedia -->
        <?php
        $total_mobil_tersedia = mysqli_num_rows($mobil);
        mysqli_data_seek($mobil, 0); // Reset pointer
        ?>

        <?php if ($total_mobil_tersedia > 0): ?>
            <div class="info-card">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="mb-1">
                            <i class="fas fa-car me-2"></i> Mobil Tersedia
                        </h5>
                        <p class="mb-0"><?= $total_mobil_tersedia ?> mobil siap untuk dipinjam</p>
                    </div>
                    <div class="badge bg-light text-primary fs-6">
                        <?= $total_mobil_tersedia ?> Unit
                    </div>
                </div>

                <div class="mobil-list mt-3">
                    <?php while ($m = mysqli_fetch_assoc($mobil)): ?>
                        <div class="mobil-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1"><?= htmlspecialchars($m['nama_mobil']) ?></h6>
                                    <small class="text-muted">Plat: <?= htmlspecialchars($m['nomor_plat']) ?></small>
                                </div>
                                <span class="badge bg-success">
                                    <i class="fas fa-check me-1"></i> Tersedia
                                </span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <div class="d-flex align-items-center">
                    <i class="fas fa-info-circle fa-2x me-3"></i>
                    <div>
                        <h5 class="mb-1">Tidak Ada Mobil Tersedia</h5>
                        <p class="mb-0">Semua mobil sedang dipinjam. Silakan coba lagi nanti.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Form Peminjaman -->
        <?php if (!$peminjaman_aktif && $total_mobil_tersedia > 0): ?>
            <div class="form-card">
                <h4 class="mb-4">
                    <i class="fas fa-edit me-2"></i> Form Pengajuan Peminjaman
                </h4>

                <form method="POST" id="peminjamanForm">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Pilih Mobil <span class="text-danger">*</span></label>
                            <select name="mobil_id" class="form-select" required>
                                <option value="">-- Pilih Mobil --</option>
                                <?php
                                mysqli_data_seek($mobil, 0); // Reset pointer lagi
                                while ($m = mysqli_fetch_assoc($mobil)):
                                ?>
                                    <option value="<?= $m['id']; ?>">
                                        <?= htmlspecialchars($m['nama_mobil']); ?> (<?= htmlspecialchars($m['nomor_plat']); ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <small class="text-muted">Pilih mobil yang tersedia dari daftar di atas</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Tanggal Peminjaman <span class="text-danger">*</span></label>
                            <input type="date" name="tanggal_pinjam" class="form-control" required
                                min="<?= date('Y-m-d'); ?>">
                            <small class="text-muted">Minimal hari ini</small>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Jam Mulai <span class="text-danger">*</span></label>
                            <input type="time" name="jam_mulai" class="form-control"
                                min="07:00" max="17:00" required>
                            <small class="text-muted">Jam kerja: 07:00 - 17:00</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Jam Selesai <span class="text-danger">*</span></label>
                            <input type="time" name="jam_selesai" class="form-control"
                                min="07:00" max="17:00" required>
                            <small class="text-muted">Harus lebih dari jam mulai</small>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Keperluan Peminjaman <span class="text-danger">*</span></label>
                        <textarea name="keperluan" class="form-control" rows="4"
                            placeholder="Jelaskan keperluan peminjaman secara detail...
Contoh:
- Menghadiri rapat di kantor Bupati
- Mengantar dokumen penting
- Kunjungan kerja lapangan
- Lainnya (sebutkan)" required></textarea>
                        <small class="text-muted">Isi dengan jelas dan lengkap untuk mempercepat persetujuan</small>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted">
                                <i class="fas fa-shield-alt me-1"></i> Data Anda aman dan terjamin
                            </small>
                        </div>
                        <button type="submit" class="btn btn-submit text-white px-5">
                            <i class="fas fa-paper-plane me-2"></i> Ajukan Peminjaman
                        </button>
                    </div>
                </form>
            </div>
        <?php elseif ($peminjaman_aktif): ?>
            <div class="alert alert-info text-center py-4">
                <i class="fas fa-info-circle fa-3x mb-3 text-primary"></i>
                <h4 class="mb-3">Tidak Dapat Mengajukan Peminjaman Baru</h4>
                <p class="mb-0">Anda masih memiliki peminjaman aktif. Silakan selesaikan peminjaman terlebih dahulu sebelum mengajukan yang baru.</p>
                <div class="mt-3">
                    <a href="struk.php" class="btn btn-primary">
                        <i class="fas fa-eye me-2"></i> Lihat Peminjaman Aktif
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Footer -->
        <footer class="mt-5 pt-3 text-center text-muted">
            <p class="mb-1">
                <small>Copyright &copy; Sistem Peminjaman Mobil Dinas <?= date('Y') ?></small>
            </p>
            <p class="mb-0">
                <small>Form pengajuan peminjaman | Update: <?= $updated ?></small>
            </p>
        </footer>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Set minimum date to today
        document.querySelector('input[name="tanggal_pinjam"]').min = new Date().toISOString().split('T')[0];

        // Form validation
        document.getElementById('peminjamanForm').addEventListener('submit', function(e) {
            const jamMulai = document.querySelector('input[name="jam_mulai"]').value;
            const jamSelesai = document.querySelector('input[name="jam_selesai"]').value;

            if (jamSelesai <= jamMulai) {
                e.preventDefault();
                alert('Jam selesai harus lebih besar dari jam mulai!');
                return false;
            }

            // Additional validation
            const tanggal = document.querySelector('input[name="tanggal_pinjam"]').value;
            const selectedDate = new Date(tanggal);
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            if (selectedDate < today) {
                e.preventDefault();
                alert('Tanggal peminjaman tidak boleh di masa lalu!');
                return false;
            }

            return true;
        });

        // Time validation
        const timeInputs = document.querySelectorAll('input[type="time"]');
        timeInputs.forEach(input => {
            input.addEventListener('change', function() {
                const value = this.value;
                const min = '07:00';
                const max = '17:00';

                if (value < min || value > max) {
                    alert('Jam harus antara 07:00 - 17:00');
                    this.value = '';
                }
            });
        });

        // Auto refresh untuk update ketersediaan mobil
        setTimeout(function() {
            location.reload();
        }, 300000); // 5 menit
    </script>
</body>

</html>