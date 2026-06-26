<?php
include "../config/database.php";

$id     = $_GET['id'] ?? null;
$status = $_GET['s'] ?? null;

if (!$id || !in_array($status, ['Dipinjam', 'Ditolak'])) {
    die('Aksi tidak valid');
}

// ambil data peminjaman
$p = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT * FROM peminjaman WHERE id='$id'
"));

if (!$p) {
    die('Data peminjaman tidak ditemukan');
}

// ============================
// HAPUS NOTIFIKASI LAMA (ANTI DOBEL)
// ============================
// mysqli_query($conn, "
//     DELETE FROM notifikasi
//     WHERE peminjaman_id='$id'
//       AND role='pegawai'
// ");

// ============================
// JIKA DISETUJUI
// ============================
if ($status === 'Dipinjam') {

    // update peminjaman
    mysqli_query($conn, "
        UPDATE peminjaman
        SET status='Dipinjam'
        WHERE id='$id'
    ");

    // update mobil → BARU dipinjam setelah ACC
    mysqli_query($conn, "
        UPDATE mobil
        SET 
            status='Dipinjam',
            dipakai_oleh='{$p['user_id']}'
        WHERE id='{$p['mobil_id']}'
    ");

    // notifikasi pegawai
    // mysqli_query($conn, "
    //     INSERT INTO notifikasi (user_id, peminjaman_id, role, pesan, status, created_at)
    //     VALUES (
    //         '{$p['user_id']}',
    //         '$id',
    //         'pegawai',
    //         'Pengajuan peminjaman Anda TELAH DISETUJUI admin',
    //         'belum',
    //         NOW()
    //     )
    // ");
}

// ============================
// JIKA DITOLAK
// ============================
if ($status === 'Ditolak') {

    // update peminjaman
    mysqli_query($conn, "
        UPDATE peminjaman
        SET 
            status = 'Ditolak',
            tanggal_kembali = tanggal_pinjam
        WHERE id = '$id'
    ");


    // pastikan mobil tetap tersedia
    mysqli_query($conn, "
        UPDATE mobil
        SET 
            status='Tersedia',
            dipakai_oleh=NULL
        WHERE id='{$p['mobil_id']}'
    ");

    // notifikasi pegawai
    // mysqli_query($conn, "
    //     INSERT INTO notifikasi (user_id, peminjaman_id, role, pesan, status, created_at)
    //     VALUES (
    //         '{$p['user_id']}',
    //         '$id',
    //         'pegawai',
    //         'Pengajuan peminjaman Anda DITOLAK admin',
    //         'belum',
    //         NOW()
    //     )
    // ");
}

header("Location: ../admin/admin-dashboard.php");
exit;
