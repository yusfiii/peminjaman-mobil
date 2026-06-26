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

// Ambil parameter filter dengan sanitasi
$status_filter = !empty($_GET['status']) ? $_GET['status'] : 'semua';
$bulan_filter = !empty($_GET['bulan']) ? $_GET['bulan'] : 'semua';
$tahun_filter = !empty($_GET['tahun']) ? $_GET['tahun'] : 'semua';
$pegawai_filter = !empty($_GET['pegawai']) ? $_GET['pegawai'] : 'semua';

$query_conditions = "WHERE 1=1";
$filter_info = [];

// Filter Logic
if (!empty($status_filter) && $status_filter != 'semua') {
    $query_conditions .= " AND p.status = '$status_filter'";
    $filter_info['status'] = ucfirst($status_filter);
} else {
    $filter_info['status'] = 'Semua Status';
}

if (!empty($bulan_filter) && $bulan_filter != 'semua') {
    $query_conditions .= " AND MONTH(p.tanggal_pinjam) = '$bulan_filter'";
    $nama_bulan = [
        '1' => 'Januari',
        '2' => 'Februari',
        '3' => 'Maret',
        '4' => 'April',
        '5' => 'Mei',
        '6' => 'Juni',
        '7' => 'Juli',
        '8' => 'Agustus',
        '9' => 'September',
        '10' => 'Oktober',
        '11' => 'November',
        '12' => 'Desember'
    ];
    if (isset($nama_bulan[$bulan_filter])) {
        $filter_info['bulan'] = $nama_bulan[$bulan_filter];
    } else {
        $filter_info['bulan'] = 'Semua Bulan';
    }
} else {
    $filter_info['bulan'] = 'Semua Bulan';
}

if (!empty($tahun_filter) && $tahun_filter != 'semua') {
    $query_conditions .= " AND YEAR(p.tanggal_pinjam) = '$tahun_filter'";
    $filter_info['tahun'] = $tahun_filter;
} else {
    $filter_info['tahun'] = 'Semua Tahun';
}

if (!empty($pegawai_filter) && $pegawai_filter != 'semua' && is_numeric($pegawai_filter)) {
    $query_conditions .= " AND p.user_id = '$pegawai_filter'";
    $peg_q = mysqli_query($conn, "SELECT nama FROM users WHERE id = '$pegawai_filter'");
    $filter_info['pegawai'] = (mysqli_num_rows($peg_q) > 0) ? mysqli_fetch_assoc($peg_q)['nama'] : 'Semua Pegawai';
} else {
    $filter_info['pegawai'] = 'Semua Pegawai';
}

// Query Utama
$query = mysqli_query($conn, "
    SELECT p.*, m.nama_mobil, m.nomor_plat, m.warna, 
           u.nama as nama_pegawai, u.nip as nip_pegawai, u.jabatan
    FROM peminjaman p
    JOIN mobil m ON p.mobil_id = m.id
    JOIN users u ON p.user_id = u.id
    $query_conditions
    ORDER BY p.tanggal_pinjam DESC, p.jam_mulai DESC
");

$total_peminjaman = mysqli_num_rows($query);

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
$pdf->Cell(0, 6, 'LAPORAN RIWAYAT PEMINJAMAN MOBIL DINAS', 0, 1, 'C');
$pdf->Ln(2);

// Informasi Filter
$pdf->SetFillColor(240, 240, 240);
$pdf->SetFont('helvetica', 'B', 8);
$pdf->Cell(180, 6, ' INFORMASI FILTER', 1, 1, 'L', true);
$pdf->SetFont('helvetica', '', 7);
$pdf->Cell(45, 6, ' Status: ' . $filter_info['status'], 'LTB', 0, 'L');
$pdf->Cell(35, 6, ' Bulan: ' . $filter_info['bulan'], 'TB', 0, 'L');
$pdf->Cell(35, 6, ' Tahun: ' . $filter_info['tahun'], 'TB', 0, 'L');
$pdf->Cell(65, 6, ' Pegawai: ' . $filter_info['pegawai'], 'RTB', 1, 'L');
$pdf->SetFont('helvetica', 'B', 7);
$pdf->Cell(180, 5, ' Total Data: ' . $total_peminjaman . ' ditemukan', 1, 1, 'L');
$pdf->Ln(4);

// Tabel Header - PERBAIKAN LEBAR KOLOM: PERKECIL MOBIL, BESARKAN KEPERLUAN
$w = array(8, 22, 40, 35, 25, 20, 30); // Total 180mm: 
// No(8), Tanggal(22), Pegawai(40), Mobil(35), Durasi(25), Status(20), Keperluan(30) ← DIPERBESAR

$header = array('No', 'Tanggal', 'Pegawai', 'Mobil', 'Durasi', 'Status', 'Keperluan');

$pdf->SetFillColor(220, 220, 220);
$pdf->SetFont('helvetica', 'B', 8);
for ($i = 0; $i < count($header); $i++) {
    $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C', true);
}
$pdf->Ln();

// Data Loop
$pdf->SetFont('helvetica', '', 7);
$no = 1;

while ($data = mysqli_fetch_assoc($query)) {
    // Penanganan tinggi baris otomatis
    $current_y = $pdf->GetY();
    if ($current_y > 240) {
        $pdf->AddPage();
    }

    $tanggal = date('d/m/Y', strtotime($data['tanggal_pinjam']));

    // Hitung Durasi
    $start_time = date('H:i', strtotime($data['jam_mulai']));
    $end_time = date('H:i', strtotime($data['jam_selesai']));

    $start = strtotime($data['jam_mulai']);
    $end = strtotime($data['jam_selesai']);
    if ($end < $start) $end += 86400;
    $diff = $end - $start;
    $durasi_text = floor($diff / 3600) . "j " . (floor(($diff % 3600) / 60)) . "m";

    // Row 1: Data Utama - SEMUA DIRATA TENGAH
    $pdf->SetFont('helvetica', '', 7);
    $pdf->Cell($w[0], 5, $no++, 'LTR', 0, 'C');
    $pdf->Cell($w[1], 5, $tanggal, 'LTR', 0, 'C');
    $pdf->Cell($w[2], 5, $data['nama_pegawai'], 'LTR', 0, 'C'); // Tanpa substr
    $pdf->Cell($w[3], 5, $data['nama_mobil'], 'LTR', 0, 'C');   // Tanpa substr
    $pdf->Cell($w[4], 5, $start_time . ' - ' . $end_time, 'LTR', 0, 'C');
    $pdf->Cell($w[5], 5, ucfirst($data['status']), 'LTR', 0, 'C');
    $pdf->Cell($w[6], 5, $data['keperluan'], 'LTR', 1, 'C');    // Tanpa substr

    // Row 2: Detail (NIP dan Plat di kolom Mobil, Durasi detail)
    $pdf->SetFont('helvetica', 'I', 6);
    $pdf->Cell($w[0], 4, '', 'LBR', 0, 'C');
    $pdf->Cell($w[1], 4, '', 'LBR', 0, 'C');
    $pdf->Cell($w[2], 4, 'NIP: ' . $data['nip_pegawai'], 'LBR', 0, 'C');

    // Plat ditampilkan di baris kedua kolom Mobil
    $pdf->Cell($w[3], 4, 'Plat: ' . $data['nomor_plat'], 'LBR', 0, 'C');

    $pdf->Cell($w[4], 4, 'Durasi: ' . $durasi_text, 'LBR', 0, 'C');
    $pdf->Cell($w[5], 4, '', 'LBR', 0, 'C'); // Kolom status kosong di baris kedua
    $pdf->Cell($w[6], 4, '', 'LBR', 1, 'C');

    // Cek jika teks keperluan terlalu panjang dan perlu baris tambahan
    $keperluan_length = strlen($data['keperluan']);
    if ($keperluan_length > 30) {
        $pdf->SetFont('helvetica', 'I', 6);
        $pdf->Cell($w[0], 3, '', 0, 0, 'C');
        $pdf->Cell($w[1], 3, '', 0, 0, 'C');
        $pdf->Cell($w[2], 3, '', 0, 0, 'C');
        $pdf->Cell($w[3], 3, '', 0, 0, 'C');
        $pdf->Cell($w[4], 3, '', 0, 0, 'C');
        $pdf->Cell($w[5], 3, '', 0, 0, 'C');
        $pdf->Cell($w[6], 3, substr($data['keperluan'], 30, 30), 0, 1, 'C');
    }
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

$pdf->Output('Laporan_Peminjaman_Admin.pdf', 'I');
