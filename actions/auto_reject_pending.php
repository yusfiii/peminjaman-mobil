<?php
include "../config/database.php";

// Update otomatis status Menunggu ACC yang sudah lewat waktu menjadi Ditolak
$query = mysqli_query($conn, "
    UPDATE peminjaman 
    SET status = 'Ditolak',
        catatan_admin = CONCAT('Ditolak otomatis: ', COALESCE(catatan_admin, ''), ' [Auto-reject: ', NOW(), ']')
    WHERE status = 'Menunggu ACC'
    AND CONCAT(tanggal_pinjam, ' ', jam_selesai) < NOW()
");

echo "Auto-reject executed: " . mysqli_affected_rows($conn) . " records updated.";
