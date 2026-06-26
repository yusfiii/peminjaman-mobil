<?php
session_start();
include "../config/database.php";

if (!isset($_SESSION['user'])) {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = $_SESSION['user']['id'];
$id = $_GET['id'] ?? null;

if (!$id) {
    header("Location: ../pegawai/struk.php?error=1&pesan=ID tidak ditemukan");
    exit;
}

// Ambil data peminjaman
$query = mysqli_query($conn, "
    SELECT * FROM peminjaman 
    WHERE id = '$id' AND user_id = '$user_id'
");

if (!$query) {
    header("Location: ../pegawai/struk.php?error=1&pesan=Error query: " . mysqli_error($conn));
    exit;
}

$p = mysqli_fetch_assoc($query);

if (!$p) {
    header("Location: ../pegawai/struk.php?error=1&pesan=Peminjaman tidak ditemukan");
    exit;
}

// CEK STATUS - Bisa Dibatalkan jika statusnya 'Menunggu ACC'
if ($p['status'] != 'Menunggu ACC') {
    header("Location: ../pegawai/struk.php?error=1&pesan=Status peminjaman adalah '" . $p['status'] . "', tidak bisa dibatalkan");
    exit;
}

// Mulai transaksi
mysqli_begin_transaction($conn);

try {
    // Update status peminjaman jadi Dibatalkan
    $update_peminjaman = mysqli_query($conn, "
        UPDATE peminjaman 
        SET status = 'Dibatalkan' 
        WHERE id = '$id'
    ");
    
    if (!$update_peminjaman) {
        throw new Exception("Gagal update peminjaman");
    }
    
    // Update status mobil jadi Tersedia
    $update_mobil = mysqli_query($conn, "
        UPDATE mobil 
        SET status = 'Tersedia', dipakai_oleh = NULL 
        WHERE id = '{$p['mobil_id']}'
    ");
    
    if (!$update_mobil) {
        throw new Exception("Gagal update mobil");
    }
    
    // HAPUS BAGIAN NOTIFIKASI - karena tabel tidak ada
    
    mysqli_commit($conn);
    
    // Redirect ke struk.php dengan pesan sukses
    header("Location: ../pegawai/struk.php?success=1&pesan=Peminjaman berhasil dibatalkan");
    exit;
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    header("Location: ../pegawai/struk.php?error=1&pesan=Gagal: " . urlencode($e->getMessage()));
    exit;
}
?>