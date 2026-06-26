<?php
session_start();
include "../config/database.php";

if (!isset($_GET['id'])) {
    die("ID tidak ditemukan");
}

$id = $_GET['id'];

// ambil data peminjaman
$q = mysqli_query($conn, "SELECT * FROM peminjaman WHERE id=$id");
$pinjam = mysqli_fetch_assoc($q);

$mobil_id = $pinjam['mobil_id'];

// ubah status peminjaman
mysqli_query($conn, "
    UPDATE peminjaman 
    SET konfirmasi_selesai='Sudah', status='Selesai'
    WHERE id=$id
");

// ubah status mobil
mysqli_query($conn, "
    UPDATE mobil 
    SET status='Tersedia', dipakai_oleh=NULL
    WHERE id=$mobil_id
");

header("Location: pegawai/dashboard.php");
exit;
