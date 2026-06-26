<?php
session_start();
include "../config/database.php";

if (!isset($_SESSION['user'])) {
    header("Location: auth/login.php");
    exit;
}

$user_id = $_SESSION['user']['id'];
$id = $_GET['id'];

// pastikan peminjaman milik user & masih aktif
$p = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT * FROM peminjaman
    WHERE id = '$id'
      AND user_id = '$user_id'
      AND tanggal_kembali IS NULL
"));

if (!$p) {
    header("Location: ../pegawai/dashboard.php");
    exit;
}

// 1️⃣ update peminjaman
mysqli_query($conn, "
    UPDATE peminjaman
    SET 
        status = 'Dikembalikan',
        tanggal_kembali = NOW()
    WHERE id = '$id'
");

// 2️⃣ update mobil
mysqli_query($conn, "
    UPDATE mobil
    SET 
        status = 'Tersedia',
        dipakai_oleh = NULL
    WHERE id = '{$p['mobil_id']}'
");

header("Location: ../pegawai/struk.php");
exit;
