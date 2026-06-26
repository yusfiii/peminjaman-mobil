<?php
session_start();
include "../config/database.php";

// Aktifkan error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>DEBUG CANCEL PEMINJAMAN</h2>";
echo "<hr>";

if (!isset($_SESSION['user'])) {
    echo "<p style='color:red'>ANDA BELUM LOGIN!</p>";
    echo "<pre>";
    print_r($_SESSION);
    echo "</pre>";
    exit;
}

$user_id = $_SESSION['user']['id'];
$id = $_GET['id'] ?? null;

echo "<h3>Informasi Session:</h3>";
echo "User ID: " . $user_id . "<br>";
echo "User Nama: " . ($_SESSION['user']['nama'] ?? 'Tidak ada') . "<br>";
echo "User Role: " . ($_SESSION['user']['role'] ?? 'Tidak ada') . "<br>";

echo "<h3>Parameter:</h3>";
echo "ID dari URL: " . ($id ? $id : 'TIDAK ADA') . "<br>";

if (!$id) {
    echo "<p style='color:red'>ERROR: ID tidak ditemukan di URL!</p>";
    echo "<p>Gunakan: debug_cancel.php?id=NOMOR_ID</p>";
    exit;
}

// Cek koneksi database
echo "<h3>Cek Koneksi Database:</h3>";
if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}
echo "Koneksi database: OK<br>";

// CEK SEMUA TABEL PEMINJAMAN USER INI
echo "<h3>SEMUA DATA PEMINJAMAN USER ID = $user_id:</h3>";
$sql_all = "SELECT * FROM peminjaman WHERE user_id = '$user_id' ORDER BY id DESC";
$result_all = mysqli_query($conn, $sql_all);

if (!$result_all) {
    echo "<p style='color:red'>Error query: " . mysqli_error($conn) . "</p>";
} else {
    $jumlah = mysqli_num_rows($result_all);
    echo "Jumlah peminjaman: " . $jumlah . "<br><br>";

    if ($jumlah > 0) {
        echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse;'>";
        echo "<tr style='background: #f0f0f0;'>";
        echo "<th>ID</th>";
        echo "<th>Mobil ID</th>";
        echo "<th>Status</th>";
        echo "<th>Tgl Pinjam</th>";
        echo "<th>Jam Mulai</th>";
        echo "<th>Jam Selesai</th>";
        echo "<th>Tgl Kembali</th>";
        echo "<th>Aksi</th>";
        echo "</tr>";

        while ($row = mysqli_fetch_assoc($result_all)) {
            $bgcolor = ($row['id'] == $id) ? 'style="background: #ffffcc;"' : '';
            echo "<tr $bgcolor>";
            echo "<td><strong>" . $row['id'] . "</strong></td>";
            echo "<td>" . $row['mobil_id'] . "</td>";
            echo "<td>";
            if ($row['status'] == 'Menunggu ACC') echo "<span style='color: orange; font-weight: bold;'>⏳ " . $row['status'] . "</span>";
            elseif ($row['status'] == 'Dipinjam') echo "<span style='color: green; font-weight: bold;'>✅ " . $row['status'] . "</span>";
            elseif ($row['status'] == 'Ditolak') echo "<span style='color: red; font-weight: bold;'>❌ " . $row['status'] . "</span>";
            elseif ($row['status'] == 'Dibatalkan') echo "<span style='color: gray; font-weight: bold;'>✖️ " . $row['status'] . "</span>";
            elseif ($row['status'] == 'Dikembalikan') echo "<span style='color: blue; font-weight: bold;'>↩️ " . $row['status'] . "</span>";
            else echo $row['status'];
            echo "</td>";
            echo "<td>" . $row['tanggal_pinjam'] . "</td>";
            echo "<td>" . $row['jam_mulai'] . "</td>";
            echo "<td>" . $row['jam_selesai'] . "</td>";
            echo "<td>" . ($row['tanggal_kembali'] ?? '<i>NULL</i>') . "</td>";
            echo "<td><a href='?id=" . $row['id'] . "'><button>Cek ID ini</button></a></td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color:red'>TIDAK ADA DATA PEMINJAMAN UNTUK USER INI!</p>";
    }
}

// CEK KHUSUS UNTUK ID YANG DIMINTA
if ($id) {
    echo "<h3>CEK KHUSUS UNTUK ID = $id:</h3>";

    $sql_specific = "SELECT * FROM peminjaman WHERE id = '$id'";
    $result_specific = mysqli_query($conn, $sql_specific);

    if (!$result_specific) {
        echo "<p style='color:red'>Error query: " . mysqli_error($conn) . "</p>";
    } else {
        $data = mysqli_fetch_assoc($result_specific);

        if (!$data) {
            echo "<p style='color:red'>❌ DATA DENGAN ID $id TIDAK DITEMUKAN DI TABEL PEMINJAMAN!</p>";
        } else {
            echo "<p style='color:green'>✅ DATA DITEMUKAN!</p>";

            echo "<h4>Data Lengkap:</h4>";
            echo "<pre>";
            print_r($data);
            echo "</pre>";

            // CEK APAKAH MILIK USER INI?
            echo "<h4>Validasi Kepemilikan:</h4>";
            if ($data['user_id'] != $user_id) {
                echo "<p style='color:red'>❌ DATA INI MILIK USER ID = " . $data['user_id'] . ", BUKAN MILIK ANDA ($user_id)!</p>";
            } else {
                echo "<p style='color:green'>✅ DATA MILIK USER INI</p>";
            }

            // CEK STATUS
            echo "<h4>Validasi Status:</h4>";
            echo "Status saat ini: <strong>" . $data['status'] . "</strong><br>";

            $bisa_dibatalkan = false;
            $alasan = "";

            if ($data['status'] == 'Menunggu ACC') {
                $bisa_dibatalkan = true;
                $alasan = "Status 'Menunggu ACC' bisa dibatalkan";
            } elseif ($data['status'] == 'Dipinjam') {
                // Cek waktu
                $mulai = strtotime($data['tanggal_pinjam'] . ' ' . $data['jam_mulai']);
                $now = time();

                echo "Waktu mulai: " . date('Y-m-d H:i:s', $mulai) . "<br>";
                echo "Waktu sekarang: " . date('Y-m-d H:i:s', $now) . "<br>";

                if ($now < $mulai) {
                    $bisa_dibatalkan = true;
                    $alasan = "Status 'Dipinjam' tapi belum mulai, bisa dibatalkan";
                } else {
                    $alasan = "Status 'Dipinjam' dan sudah mulai, tidak bisa dibatalkan";
                }
            } else {
                $alasan = "Status '" . $data['status'] . "' tidak bisa dibatalkan";
            }

            if ($bisa_dibatalkan) {
                echo "<p style='color:green; font-weight:bold'>✅ BISA DIBATALKAN: $alasan</p>";

                // Tawarkan force cancel
                echo "<h4>ACTION:</h4>";
                echo "<form method='post' action='' onsubmit='return confirm(\"Yakin ingin membatalkan?\");'>";
                echo "<input type='hidden' name='id' value='$id'>";
                echo "<input type='hidden' name='mobil_id' value='" . $data['mobil_id'] . "'>";
                echo "<button type='submit' name='cancel' style='background: #ff4444; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;'>🔴 PROSES CANCEL PEMINJAMAN #$id</button>";
                echo "</form>";
            } else {
                echo "<p style='color:red; font-weight:bold'>❌ TIDAK BISA DIBATALKAN: $alasan</p>";
            }
        }
    }
}

// PROSES CANCEL JIKA TOMBOL DITEKAN
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cancel'])) {
    echo "<h3>PROSES CANCEL:</h3>";

    $cancel_id = $_POST['id'];
    $mobil_id = $_POST['mobil_id'];

    echo "Memproses cancel ID: $cancel_id, Mobil ID: $mobil_id<br>";

    // Mulai transaksi
    mysqli_begin_transaction($conn);

    try {
        // 1. Update peminjaman
        $update_peminjaman = mysqli_query($conn, "UPDATE peminjaman SET status = 'Dibatalkan' WHERE id = '$cancel_id'");

        if (!$update_peminjaman) {
            throw new Exception("Gagal update peminjaman: " . mysqli_error($conn));
        }
        echo "✅ Update peminjaman: BERHASIL<br>";

        // 2. Update mobil
        $update_mobil = mysqli_query($conn, "UPDATE mobil SET status = 'Tersedia', dipakai_oleh = NULL WHERE id = '$mobil_id'");

        if (!$update_mobil) {
            throw new Exception("Gagal update mobil: " . mysqli_error($conn));
        }
        echo "✅ Update mobil: BERHASIL<br>";

        // 3. Insert notifikasi
        $notif = mysqli_query($conn, "INSERT INTO notifikasi (role, pesan, created_at) VALUES ('admin', 'Peminjaman #$cancel_id dibatalkan via debug', NOW())");

        mysqli_commit($conn);

        echo "<p style='color:green; font-size:18px; font-weight:bold; background:#e8f5e9; padding:15px; border-radius:5px;'>✅✅ CANCEL BERHASIL! Data peminjaman #$cancel_id sudah dibatalkan.</p>";

        // Tampilkan data setelah update
        $cek_after = mysqli_query($conn, "SELECT * FROM peminjaman WHERE id = '$cancel_id'");
        $after = mysqli_fetch_assoc($cek_after);
        echo "Status setelah update: <strong>" . $after['status'] . "</strong><br>";

        echo "<br><a href='../pegawai/struk.php' style='background: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔍 Lihat di Struk</a>";
        echo " ";
        echo "<a href='?id=$cancel_id' style='background: #2196F3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>↻ Refresh Debug</a>";
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo "<p style='color:red'>❌ GAGAL: " . $e->getMessage() . "</p>";
    }

    exit;
}

echo "<hr>";
echo "<br><br>";
echo "<a href='../pegawai/struk.php' style='background: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔍 Kembali ke Struk</a>";
echo " ";
echo "<a href='?' style='background: #FFC107; color: black; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>⟲ Refresh Halaman Ini</a>";
