<?php
session_start();
include "../config/database.php";

// Set timezone ke Asia/Makassar
date_default_timezone_set('Asia/Makassar');

// Cek apakah pengguna sudah login
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit;
}

// Cek apakah ada parameter ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("ID peminjaman tidak valid.");
}

$id_peminjaman = (int)$_GET['id'];
$user = $_SESSION['user'];
$user_id = (int)$user['id'];

// Query data peminjaman
$query = mysqli_query($conn, "
    SELECT 
        p.*,
        m.nama_mobil,
        m.nomor_plat,
        m.foto,
        u.nama as nama_pegawai,
        u.nip as nip_pegawai
    FROM peminjaman p
    JOIN mobil m ON p.mobil_id = m.id
    JOIN users u ON p.user_id = u.id
    WHERE p.id = '$id_peminjaman'
");

if (mysqli_num_rows($query) == 0) {
    die("Data peminjaman tidak ditemukan.");
}

$data = mysqli_fetch_assoc($query);

// Hanya boleh akses data sendiri (kecuali admin)
if ($user['role'] != 'admin' && $data['user_id'] != $user_id) {
    die("Anda tidak memiliki akses untuk melihat struk ini.");
}

// Generate PDF
require_once('../tcpdf/tcpdf.php');

class PDF extends TCPDF
{
    // Page header
    public function Header()
    {
        // Logo
        $image_file = '../assets/banjarbaru.png';
        if (file_exists($image_file)) {
            $this->Image($image_file, 15, 10, 25, 25, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }

        // Header text
        $this->SetFont('helvetica', 'B', 14);
        $this->SetXY(45, 12);
        $this->Cell(145, 0, 'PEMERINTAH KOTA BANJARBARU', 0, 1, 'C');

        $this->SetFont('helvetica', 'B', 12);
        $this->SetXY(45, 18);
        $this->Cell(145, 0, 'DINAS KOMUNIKASI DAN INFORMATIKA', 0, 1, 'C');

        $this->SetFont('helvetica', '', 10);
        $this->SetXY(45, 24);
        $this->Cell(145, 0, 'Jl. Pangeran Suriansyah Nomor 5 Banjarbaru – Kalimantan Selatan', 0, 1, 'C');

        $this->SetFont('helvetica', '', 9);
        $this->SetXY(45, 29);
        $this->Cell(145, 0, 'Telp./Fax (0511) 5200052 Email : diskominfo@banjarbarukota.go.id', 0, 1, 'C');

        // Garis header
        $this->SetLineWidth(0.8);
        $this->Line(15, 37, 195, 37);

        // Set Y position setelah header
        $this->SetY(42);
    }

    // Page footer - untuk informasi tanggal cetak (rata kiri)
    public function Footer()
    {
        // Posisi footer di bagian bawah
        $this->SetY(-20);

        // Informasi tanggal cetak dan dicetak oleh - RATA KIRI
        $this->SetFont('helvetica', '', 8);
        $this->SetX(15); // Posisi di kiri
        $this->Cell(0, 4, 'Tanggal cetak: ' . date('d/m/Y H:i:s') . ' | Dicetak oleh: ' . ($_SESSION['user']['nama'] ?? '-'), 0, 1, 'L');
    }
}

// Create PDF object - Ukuran A4
$pdf = new PDF('P', 'mm', 'A4', true, 'UTF-8');
$pdf->SetCreator('Sistem Peminjaman Mobil Dinas');
$pdf->SetAuthor('Diskominfo Banjarbaru');
$pdf->SetTitle('STRUK PEMINJAMAN MOBIL DINAS');
$pdf->SetSubject('Struk Peminjaman');

// Set margins untuk A4
$pdf->SetMargins(15, 42, 15);
$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(15); // Margin untuk footer
$pdf->SetAutoPageBreak(TRUE, 30); // Aktifkan auto page break

$pdf->AddPage();

// Judul Struk
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'STRUK PEMINJAMAN MOBIL DINAS', 0, 1, 'C');

$pdf->SetFont('helvetica', '', 11);
$pdf->Cell(0, 6, 'No. Transaksi: P' . str_pad($data['id'], 6, '0', STR_PAD_LEFT), 0, 1, 'C');

// Garis bawah judul
$pdf->SetLineWidth(0.5);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(10);

// Data Peminjaman dalam 2 kolom - HEADER
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(95, 8, 'DATA PEMINJAM', 0, 0, 'L');
$pdf->Cell(95, 8, 'DATA MOBIL', 0, 1, 'L');
$pdf->Ln(2);

// Kolom Kiri: Data Peminjam
$x_start = 15;
$y_start = $pdf->GetY();
$pdf->SetXY($x_start, $y_start);

$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(40, 6, 'Nama Pegawai', 0, 0);
$pdf->Cell(5, 6, ':', 0, 0);
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell(0, 6, $data['nama_pegawai'], 0, 1);

$pdf->SetX($x_start);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(40, 6, 'NIP', 0, 0);
$pdf->Cell(5, 6, ':', 0, 0);
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell(0, 6, $data['nip_pegawai'] ?: '-', 0, 1);

$pdf->SetX($x_start);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(40, 6, 'Tanggal', 0, 0);
$pdf->Cell(5, 6, ':', 0, 0);
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell(0, 6, date('d/m/Y', strtotime($data['tanggal_pinjam'])), 0, 1);

$pdf->SetX($x_start);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(40, 6, 'Jam', 0, 0);
$pdf->Cell(5, 6, ':', 0, 0);
$pdf->SetFont('helvetica', '', 11);
$jam_mulai = date('H:i', strtotime($data['jam_mulai']));
$jam_selesai = date('H:i', strtotime($data['jam_selesai']));
$pdf->Cell(0, 6, $jam_mulai . ' - ' . $jam_selesai, 0, 1);

// Hitung durasi peminjaman dengan benar
$jam_mulai_time = $data['jam_mulai'];
$jam_selesai_time = $data['jam_selesai'];

// Konversi ke format waktu yang benar
if (strpos($jam_mulai_time, ':') !== false) {
    $jam_mulai_parts = explode(':', $jam_mulai_time);
    $jam_mulai_hour = (int)$jam_mulai_parts[0];
    $jam_mulai_minute = isset($jam_mulai_parts[1]) ? (int)$jam_mulai_parts[1] : 0;
} else {
    $jam_mulai_hour = (int)date('H', strtotime($jam_mulai_time));
    $jam_mulai_minute = (int)date('i', strtotime($jam_mulai_time));
}

if (strpos($jam_selesai_time, ':') !== false) {
    $jam_selesai_parts = explode(':', $jam_selesai_time);
    $jam_selesai_hour = (int)$jam_selesai_parts[0];
    $jam_selesai_minute = isset($jam_selesai_parts[1]) ? (int)$jam_selesai_parts[1] : 0;
} else {
    $jam_selesai_hour = (int)date('H', strtotime($jam_selesai_time));
    $jam_selesai_minute = (int)date('i', strtotime($jam_selesai_time));
}

// Hitung total menit
$total_mulai_menit = ($jam_mulai_hour * 60) + $jam_mulai_minute;
$total_selesai_menit = ($jam_selesai_hour * 60) + $jam_selesai_minute;

// Jika jam selesai lebih kecil dari jam mulai (misal 23:00 - 01:00), tambahkan 24 jam
if ($total_selesai_menit < $total_mulai_menit) {
    $total_selesai_menit += (24 * 60);
}

$durasi_menit = $total_selesai_menit - $total_mulai_menit;

$durasi_jam = floor($durasi_menit / 60);
$durasi_sisa_menit = $durasi_menit % 60;

$pdf->SetX($x_start);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(40, 6, 'Durasi', 0, 0);
$pdf->Cell(5, 6, ':', 0, 0);
$pdf->SetFont('helvetica', '', 11);
if ($durasi_jam > 0) {
    $pdf->Cell(0, 6, $durasi_jam . ' jam ' . $durasi_sisa_menit . ' menit', 0, 1);
} else {
    $pdf->Cell(0, 6, $durasi_sisa_menit . ' menit', 0, 1);
}

$pdf->SetX($x_start);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(40, 6, 'Status', 0, 0);
$pdf->Cell(5, 6, ':', 0, 0);
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell(0, 6, $data['status'], 0, 1);

// Kolom Kanan: Data Mobil
$x_start_right = 110;
$pdf->SetXY($x_start_right, $y_start);

$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(40, 6, 'Nama Mobil', 0, 0);
$pdf->Cell(5, 6, ':', 0, 0);
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell(0, 6, $data['nama_mobil'], 0, 1);

$pdf->SetX($x_start_right);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(40, 6, 'Plat Nomor', 0, 0);
$pdf->Cell(5, 6, ':', 0, 0);
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell(0, 6, $data['nomor_plat'], 0, 1);

$pdf->SetX($x_start_right);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(40, 6, 'ID Peminjaman', 0, 0);
$pdf->Cell(5, 6, ':', 0, 0);
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell(0, 6, 'P' . str_pad($data['id'], 6, '0', STR_PAD_LEFT), 0, 1);

$pdf->SetX($x_start_right);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(40, 6, 'Jenis', 0, 0);
$pdf->Cell(5, 6, ':', 0, 0);
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell(0, 6, 'Mobil Dinas', 0, 1);

// Kembali ke kolom kiri untuk keperluan
$pdf->SetXY($x_start, $y_start + 42);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(40, 6, 'Keperluan', 0, 1);
$pdf->SetFont('helvetica', '', 11);

// Keperluan
$keperluan_y = $pdf->GetY();
$pdf->SetXY($x_start, $keperluan_y);
$pdf->MultiCell(170, 6, $data['keperluan'], 0, 'L');
$pdf->Ln(10);

// Garis pemisah setelah keperluan
$pdf->SetLineWidth(0.5);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(10);

// Catatan penting di bagian bawah kiri
$notes_x = 15;
$notes_y = $pdf->GetY();
$pdf->SetXY($notes_x, $notes_y);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 6, 'CATATAN:', 0, 1);
$pdf->SetFont('helvetica', '', 9);
$notes = array(
    '1. Struk ini sah sebagai bukti peminjaman mobil dinas.',
    '2. Harap menjaga mobil dengan baik selama peminjaman.',
    '3. Kembalikan mobil tepat waktu sesuai jadwal.',
    '4. Pelanggaran akan dikenakan sanksi sesuai peraturan.'
);

foreach ($notes as $note) {
    $pdf->Cell(0, 5, $note, 0, 1);
}

$pdf->Ln(15); // Spasi sebelum tanda tangan

// ====================
// BAGIAN TANDA TANGAN - POSISI KANAN TAPI TEKS RATA TENGAH DALAM KOLOM
// ====================

// Lebar kolom untuk tanda tangan (misal 80mm)
$signature_width = 80;

// Posisi X untuk kolom tanda tangan (posisi kanan)
$signature_x = 210 - $signature_width; // 195 adalah margin kanan (15) + lebar halaman (180)

// Mulai dari posisi Y saat ini
$signature_y = $pdf->GetY();

// Atur posisi X ke kanan
$pdf->SetX($signature_x);

// Kota dan tanggal - RATA TENGAH dalam kolom 80mm
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell($signature_width, 5, 'Banjarbaru, ' . date('d/m/Y'), 0, 1, 'C');
$pdf->SetX($signature_x); // Tetap di posisi X yang sama
$pdf->Ln(5); // Spasi

// "Mengetahui," - RATA TENGAH dalam kolom
$pdf->SetX($signature_x);
$pdf->Cell($signature_width, 5, 'Mengetahui,', 0, 1, 'C');
$pdf->SetX($signature_x);
$pdf->Ln(10); // Spasi untuk tanda tangan

// Nama - RATA TENGAH dalam kolom
$pdf->SetX($signature_x);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell($signature_width, 5, 'Drs. KRISMAN', 0, 1, 'C');
$pdf->SetX($signature_x);
$pdf->SetFont('helvetica', '', 9);

// Jabatan - RATA TENGAH dalam kolom
$pdf->SetX($signature_x);
$pdf->Cell($signature_width, 4, 'Pembina IV/a', 0, 1, 'C');
$pdf->SetX($signature_x);

// NIP - RATA TENGAH dalam kolom
$pdf->SetX($signature_x);
$pdf->Cell($signature_width, 4, 'NIP 19730303 200003 1 009', 0, 1, 'C');

// Output PDF - hanya satu halaman
$pdf->Output('Struk_Peminjaman_P' . str_pad($data['id'], 6, '0', STR_PAD_LEFT) . '.pdf', 'I');
exit;
