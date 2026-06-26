<?php
session_start();
include "../config/database.php";
include('../libs/phpqrcode/qrlib.php');

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

// Ambil data mobil
$mobil = mysqli_query($conn, "
    SELECT 
        m.id,
        m.nama_mobil,
        m.nomor_plat,
        m.tahun,
        m.warna,
        m.kapasitas,
        m.foto,
        m.keterangan,
        u.nama AS dipakai_oleh,
        CASE
            WHEN p.id IS NOT NULL THEN 'Dipinjam'
            ELSE 'Tersedia'
        END AS status
    FROM mobil m
    LEFT JOIN peminjaman p 
        ON m.id = p.mobil_id
        AND p.tanggal_kembali IS NULL
        AND p.status = 'Dipinjam'
    LEFT JOIN users u 
        ON p.user_id = u.id
    ORDER BY 
        CASE 
            WHEN p.id IS NOT NULL THEN 1 
            ELSE 2 
        END,
        m.nama_mobil ASC
");


// Hitung statistik
$total_mobil = mysqli_num_rows($mobil);
$tersedia_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM mobil m LEFT JOIN peminjaman p ON m.id = p.mobil_id AND p.tanggal_kembali IS NULL AND p.status = 'Dipinjam' WHERE p.id IS NULL");
$tersedia = mysqli_fetch_assoc($tersedia_query)['total'] ?? 0;
$dipinjam = $total_mobil - $tersedia;

$updated = date('d/m/Y H:i');
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Mobil - Peminjaman Mobil Dinas</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fb;
        }

        /* .sidebar {
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
        } */

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

        .mobil-card {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            height: 100%;
        }

        .mobil-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .mobil-img {
            height: 180px;
            object-fit: cover;
            width: 100%;
            background-color: #f8f9fa;
        }

        .qr-code {
            width: 120px;
            height: 120px;
            margin-top: 10px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 5px;
            background-color: white;
        }

        .status-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 12px;
            font-size: 0.8rem;
            border-radius: 20px;
        }

        .search-box {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .filter-buttons .btn {
            margin-right: 5px;
            margin-bottom: 5px;
        }

        .no-photo {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 180px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-size: 3rem;
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

            .mobil-card {
                margin-bottom: 20px;
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
                        <i class="fas fa-car me-2"></i> Status Mobil Dinas
                    </h1>
                    <p class="text-muted mb-0">Informasi ketersediaan dan status mobil dinas</p>
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
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon text-primary">
                        <i class="fas fa-car"></i>
                    </div>
                    <div class="stat-number"><?= $total_mobil ?></div>
                    <div class="stat-title">Total Mobil</div>
                    <small class="text-muted">Keseluruhan kendaraan dinas</small>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon text-success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-number"><?= $tersedia ?></div>
                    <div class="stat-title">Mobil Tersedia</div>
                    <small class="text-muted">Siap untuk dipinjam</small>
                </div>
            </div>

            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon text-warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-number"><?= $dipinjam ?></div>
                    <div class="stat-title">Sedang Dipinjam</div>
                    <small class="text-muted">Dalam peminjaman aktif</small>
                </div>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="search-box">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" class="form-control" id="searchInput" placeholder="Cari mobil berdasarkan nama, plat, atau status...">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="filter-buttons">
                        <button class="btn btn-outline-primary filter-btn" data-filter="all">
                            <i class="fas fa-list"></i> Semua
                        </button>
                        <button class="btn btn-outline-success filter-btn" data-filter="tersedia">
                            <i class="fas fa-check"></i> Tersedia
                        </button>
                        <button class="btn btn-outline-warning filter-btn" data-filter="dipinjam">
                            <i class="fas fa-clock"></i> Dipinjam
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobil Cards -->
        <div class="row" id="mobilContainer">
            <?php
            mysqli_data_seek($mobil, 0); // Reset pointer result set
            while ($m = mysqli_fetch_assoc($mobil)) {
                // Generate QR Code untuk mobil
                // Generate QR Code untuk mobil (lengkap semua field)
                $qr_data =
                    "ID: " . ($m['id'] ?? '-') . "\n" .
                    "Nama Mobil: " . ($m['nama_mobil'] ?? '-') . "\n" .
                    "Nomor Plat: " . ($m['nomor_plat'] ?? '-') . "\n" .
                    "Tahun: " . ($m['tahun'] ?? 'Tidak ada') . "\n" .
                    "Warna: " . ($m['warna'] ?? 'Tidak ada') . "\n" .
                    "Kapasitas: " . ($m['kapasitas'] ?? 'Tidak ada') . " orang\n" .
                    "Status: " . ($m['status'] ?? '-') . "\n" .
                    "Foto: " . (!empty($m['foto']) ? $m['foto'] : 'Tidak ada') . "\n" .
                    "Keterangan: " . ($m['keterangan'] ?? 'Tidak ada') . "\n" .
                    "Dipakai Oleh: " . ($m['dipakai_oleh'] ?? 'Tidak ada');


                // Nama file QR
                $qr_file = 'qrcodes/' . $m['id'] . '.png';


                // Pastikan folder 'qrcodes' ada
                if (!file_exists('qrcodes')) {
                    mkdir('qrcodes', 0777, true);
                }

                // Generate QR Code dan simpan di file
                QRcode::png($qr_data, $qr_file, 'L', 10, 2);

                // Status class
                $status_class = $m['status'] == 'Tersedia' ? 'bg-success' : 'bg-warning';
            ?>
                <div class="col-lg-4 col-md-6 mb-4 mobil-item" data-status="<?= strtolower($m['status']) ?>">
                    <div class="card mobil-card h-100">
                        <!-- Foto Mobil -->
                        <div style="position: relative;">
                            <?php if (!empty($m['foto'])) { ?>
                                <img src="../uploads/<?= $m['foto'] ?>" class="mobil-img" alt="<?= $m['nama_mobil'] ?>">
                            <?php } else { ?>
                                <div class="no-photo">
                                    <i class="fas fa-car"></i>
                                </div>
                            <?php } ?>

                            <!-- Status Badge -->
                            <span class="status-badge <?= $status_class ?> text-white">
                                <?= $m['status'] ?>
                            </span>
                        </div>

                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="fas fa-car me-2 text-primary"></i>
                                <?= htmlspecialchars($m['nama_mobil']) ?>
                            </h5>

                            <div class="card-text mb-3">
                                <div class="mb-2">
                                    <strong><i class="fas fa-tag me-2"></i> Plat Nomor:</strong><br>
                                    <span class="badge bg-secondary fs-6"><?= htmlspecialchars($m['nomor_plat']) ?></span>
                                </div>

                                <div class="mb-2">
                                    <strong><i class="fas fa-user me-2"></i> Status Penggunaan:</strong><br>
                                    <?php if ($m['status'] == "Tersedia") { ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check-circle me-1"></i> Tersedia
                                        </span>
                                        <p class="text-muted mt-1 mb-0"><small>Siap untuk dipinjam</small></p>
                                    <?php } else { ?>
                                        <span class="badge bg-warning text-dark">
                                            <i class="fas fa-clock me-1"></i> Dipinjam
                                        </span>
                                        <p class="text-muted mt-1 mb-0">
                                            <small>
                                                <i class="fas fa-user me-1"></i>
                                                <?= htmlspecialchars($m['dipakai_oleh'] ?: "Tidak diketahui") ?>
                                            </small>
                                        </p>
                                    <?php } ?>
                                </div>
                            </div>

                            <!-- QR Code -->
                            <div class="text-center border-top pt-3">
                                <p class="text-muted mb-2">
                                    <i class="fas fa-qrcode me-1"></i> Scan QR Code untuk informasi lengkap
                                </p>
                                <img src="<?= $qr_file ?>" class="qr-code" alt="QR Code">
                                <div class="mt-2">
                                    <button class="btn btn-sm btn-outline-primary" onclick="downloadQR('<?= $qr_file ?>', '<?= $m['nama_mobil'] ?>')">
                                        <i class="fas fa-download me-1"></i> Download QR
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="card-footer bg-transparent border-top">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i> ID: <?= $m['id'] ?>
                            </small>
                        </div>
                    </div>
                </div>
            <?php } ?>

            <?php if (mysqli_num_rows($mobil) == 0) { ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fas fa-car fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">Tidak ada data mobil</h4>
                        <p class="text-muted">Belum ada mobil yang terdaftar dalam sistem</p>
                    </div>
                </div>
            <?php } ?>
        </div>

        <!-- Footer -->
        <footer class="mt-5 pt-3 text-center text-muted">
            <p class="mb-1">
                <small>Copyright &copy; Sistem Peminjaman Mobil Dinas <?= date('Y') ?></small>
            </p>
            <p class="mb-0">
                <small>Total <?= $total_mobil ?> mobil terdaftar | Update: <?= $updated ?></small>
            </p>
        </footer>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Search Functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const mobilItems = document.querySelectorAll('.mobil-item');

            mobilItems.forEach(item => {
                const text = item.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });

        // Filter Functionality
        document.querySelectorAll('.filter-btn').forEach(button => {
            button.addEventListener('click', function() {
                const filter = this.getAttribute('data-filter');

                // Update active button
                document.querySelectorAll('.filter-btn').forEach(btn => {
                    btn.classList.remove('active');
                });
                this.classList.add('active');

                // Filter items
                const mobilItems = document.querySelectorAll('.mobil-item');

                mobilItems.forEach(item => {
                    if (filter === 'all') {
                        item.style.display = 'block';
                    } else {
                        const status = item.getAttribute('data-status');
                        if (status === filter) {
                            item.style.display = 'block';
                        } else {
                            item.style.display = 'none';
                        }
                    }
                });
            });
        });

        // Download QR Code
        function downloadQR(qrPath, mobilName) {
            const link = document.createElement('a');
            link.href = qrPath;
            link.download = 'QRCode_' + mobilName.replace(/\s+/g, '_') + '.png';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Auto refresh setiap 2 menit
        setTimeout(function() {
            location.reload();
        }, 120000);

        // Initialize filter buttons
        document.querySelector('[data-filter="all"]').classList.add('active');
    </script>
</body>

</html>