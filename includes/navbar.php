<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include "../config/database.php";

$user = $_SESSION['user'] ?? null;
$user_id = $user['id'] ?? null;
$role = $user['role'] ?? null;

$notif = null;
$jumlah_notif = 0;

// ===============================
// AMBIL NOTIFIKASI
// ===============================
if ($user) {
    if ($role === 'admin') {
        $notif = mysqli_query($conn, "
            SELECT * FROM notifikasi
            WHERE role = 'admin'
              AND status = 'belum'
            ORDER BY created_at DESC
        ");
    } else {
        $notif = mysqli_query($conn, "
            SELECT * FROM notifikasi
            WHERE user_id = '$user_id'
              AND status = 'belum'
            ORDER BY created_at DESC
        ");
    }

    $jumlah_notif = mysqli_num_rows($notif);
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Peminjaman Mobil Dinas</title>

    <!-- ✅ BOOTSTRAP CSS (WAJIB) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- CSS PROJECT -->
    <link rel="stylesheet" href="../assets/style.css">
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top shadow-sm">
        <div class="container-fluid">
            <span class="navbar-brand fw-bold">Peminjaman Mobil Dinas</span>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">

                    <!-- 🔔 NOTIFIKASI -->
                    <li class="nav-item dropdown me-3">
                        <a class="nav-link dropdown-toggle text-white position-relative" href="#" role="button" data-bs-toggle="dropdown">
                            🔔
                            <?php if ($jumlah_notif > 0) { ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?= $jumlah_notif ?>
                                </span>
                            <?php } ?>
                        </a>

                        <ul class="dropdown-menu dropdown-menu-end shadow"
                            style="width:320px; max-height:300px; overflow-y:auto;">
                            <?php if ($jumlah_notif == 0) { ?>
                                <li class="dropdown-item text-muted text-center">
                                    Tidak ada notifikasi
                                </li>
                            <?php } ?>

                            <?php while ($n = mysqli_fetch_assoc($notif)) { ?>
                                <li class="dropdown-item small">
                                    <a href="../actions/baca_notifikasi.php?id=<?= $n['id'] ?>" class="text-decoration-none text-dark">
                                        <div class="fw-bold text-primary">
                                            <?= date('d/m/Y H:i', strtotime($n['created_at'])) ?>
                                        </div>
                                        <?= $n['pesan'] ?>
                                    </a>
                                </li>

                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                            <?php } ?>
                        </ul>
                    </li>

                    <!-- INFO USER -->
                    <?php if ($user) { ?>
                        <li class="nav-item me-3">
                            <span class="nav-link text-white">
                                <b><?= $user['nama'] ?></b> (<?= $user['role'] ?>)
                            </span>
                        </li>

                        <!-- LOGOUT -->
                        <li class="nav-item">
                            <a class="nav-link text-warning fw-bold"
                                href="../auth/logout.php"
                                onclick="return confirm('Yakin ingin logout?')">
                                Logout
                            </a>
                        </li>
                    <?php } ?>

                </ul>
            </div>
        </div>
    </nav>

    <?php
    // ===============================
    // TANDAI NOTIFIKASI SUDAH DIBACA
    // ===============================
    // if ($user) {
    //     if ($role === 'admin') {
    //         mysqli_query($conn, "UPDATE notifikasi SET status='dibaca' WHERE role='admin'");
    //     } else {
    //         mysqli_query($conn, "UPDATE notifikasi SET status='dibaca' WHERE user_id='$user_id'");
    //     }
    // }
