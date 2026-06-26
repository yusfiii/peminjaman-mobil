<?php
session_start();
include "../config/database.php";

// Set error reporting untuk development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone ke Asia/Makassar
date_default_timezone_set('Asia/Makassar');

// Cek login
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') {
    header("Location: ../login.php");
    exit;
}

// Query untuk mengambil data pengguna dengan JOIN ke tabel bidang dan seksi
$query = mysqli_query($conn, "
    SELECT 
        u.id, 
        u.nama, 
        u.nip, 
        u.jabatan, 
        b.nama_bidang, 
        s.nama_seksi, 
        u.no_hp
    FROM users u
    LEFT JOIN bidang b ON u.bidang_id = b.id
    LEFT JOIN seksi s ON u.seksi_id = s.id
    WHERE u.role IN ('pegawai', 'admin')
    ORDER BY u.role DESC, u.nama ASC
");

// Cek jika query gagal
if (!$query) {
    die("Error dalam query: " . mysqli_error($conn));
}

$total_users = mysqli_num_rows($query);

require_once('../tcpdf/tcpdf.php');

class PDF extends TCPDF
{
    public function Header()
    {
        $image_file = '../assets/banjarbaru.png';
        if (file_exists($image_file)) {
            $this->Image($image_file, 15, 10, 18, 18, 'PNG');
        }
        $this->SetFont('helvetica', 'B', 12);
        $this->SetXY(38, 10);
        $this->Cell(142, 0, 'PEMERINTAH KOTA BANJARBARU', 0, 1, 'C');
        $this->SetFont('helvetica', 'B', 11);
        $this->SetX(38);
        $this->Cell(142, 0, 'DINAS KOMUNIKASI DAN INFORMATIKA', 0, 1, 'C');
        $this->SetFont('helvetica', '', 8);
        $this->SetX(38);
        $this->Cell(142, 0, 'Jl. Pangeran Suriansyah Nomor 5 Banjarbaru – Kalimantan Selatan', 0, 1, 'C');
        $this->SetFont('helvetica', '', 7);
        $this->SetX(38);
        $this->Cell(142, 0, 'Telp./Fax (0511) 5200052 Email : diskominfo@banjarbarukota.go.id', 0, 1, 'C');
        $this->SetLineWidth(0.5);
        $this->Line(15, 32, 195, 32);
    }
}

$pdf = new PDF('P', 'mm', 'A4', true, 'UTF-8');
$pdf->SetMargins(15, 40, 15);
$pdf->SetAutoPageBreak(TRUE, 25);
$pdf->AddPage();

// Judul
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 6, 'LAPORAN DATA PENGGUNA', 0, 1, 'C');
$pdf->Ln(2);

// Informasi Filter
$pdf->SetFillColor(240, 240, 240);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell(180, 6, ' INFORMASI DATA', 1, 1, 'L', true);
$pdf->SetFont('helvetica', '', 7);
$pdf->Cell(180, 6, ' Total Data: ' . $total_users . ' pengguna ditemukan', 1, 1, 'L');
$pdf->Ln(4);

// Tabel Header - SESUAIKAN LEBAR KOLOM: Jabatan diperkecil, No HP diperbesar
$w = array(10, 40, 25, 25, 35, 25, 20); // Total 180mm
// Perubahan: Jabatan dari 30mm ke 25mm, No HP dari 15mm ke 20mm
$header = array('No', 'Nama', 'NIP', 'Jabatan', 'Bidang', 'Seksi', 'No HP');

$pdf->SetFillColor(220, 220, 220);
$pdf->SetFont('helvetica', 'B', 8);
for ($i = 0; $i < count($header); $i++) {
    $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C', true);
}
$pdf->Ln();

// Data Loop - VERSI SEDERHANA SATU BARIS
$pdf->SetFont('helvetica', '', 7);
$no = 1;

while ($data = mysqli_fetch_assoc($query)) {
    // Penanganan tinggi baris otomatis
    $current_y = $pdf->GetY();
    if ($current_y > 240) {
        $pdf->AddPage();
    }

    // Format data yang kosong
    $nama = !empty($data['nama']) ? $data['nama'] : '-';
    $nip = !empty($data['nip']) ? $data['nip'] : '-';
    $jabatan = !empty($data['jabatan']) ? $data['jabatan'] : '-';
    $bidang = !empty($data['nama_bidang']) ? $data['nama_bidang'] : '-';
    $seksi = !empty($data['nama_seksi']) ? $data['nama_seksi'] : '-';
    $no_hp = !empty($data['no_hp']) ? $data['no_hp'] : '-';

    // Data dalam satu baris - SESUAIKAN DENGAN LEBAR KOLOM BARU
    $pdf->Cell($w[0], 6, $no++, 1, 0, 'C');
    $pdf->Cell($w[1], 6, substr($nama, 0, 25), 1, 0, 'L');
    $pdf->Cell($w[2], 6, substr($nip, 0, 15), 1, 0, 'C');
    $pdf->Cell($w[3], 6, substr($jabatan, 0, 15), 1, 0, 'L'); // Jabatan 15 karakter (dari 18)

    // Bidang dengan font sedikit lebih kecil agar muat
    $pdf->SetFont('helvetica', '', 6.5);
    $pdf->Cell($w[4], 6, substr($bidang, 0, 22), 1, 0, 'C');
    $pdf->SetFont('helvetica', '', 7);

    $pdf->Cell($w[5], 6, substr($seksi, 0, 15), 1, 0, 'C');
    $pdf->Cell($w[6], 6, substr($no_hp, 0, 15), 1, 1, 'C'); // No HP 15 karakter (dari 12)
}

// Simpan posisi Y saat ini untuk sinkronisasi
$y_after_table = $pdf->GetY() + 10;
if ($y_after_table > 240) {
    $pdf->AddPage();
    $y_after_table = 40;
}

// --- BAGIAN KIRI: Info Cetak ---
$pdf->SetXY(15, $y_after_table);
$pdf->SetFont('helvetica', 'I', 7);
$pdf->Cell(90, 4, 'Tanggal cetak: ' . date('d/m/Y H:i:s'), 0, 1, 'L');
$pdf->SetX(15);
$pdf->Cell(90, 4, 'Dicetak oleh: ' . $_SESSION['user']['nama'] . ' (Administrator)', 0, 0, 'L');

// --- BAGIAN KANAN: Tanda Tangan ---
$pdf->SetXY(130, $y_after_table);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(60, 5, 'Banjarbaru, ' . date('d F Y'), 0, 1, 'C');
$pdf->SetX(130);
$pdf->Cell(60, 5, 'Mengetahui,', 0, 1, 'C');
$pdf->Ln(15);

$pdf->SetX(130);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(60, 5, 'Drs. KRISMAN', 0, 1, 'C');
$pdf->SetX(130);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell(60, 4, 'Pembina IV/a', 0, 1, 'C');
$pdf->SetX(130);
$pdf->Cell(60, 4, 'NIP 19730303 200003 1 009', 0, 1, 'C');

$pdf->Output('Laporan_Pengguna.pdf', 'I');
