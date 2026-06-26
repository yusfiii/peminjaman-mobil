<?php
session_start();
include "../config/database.php";

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$user = $_SESSION['user'];
$user_id = (int)$user['id'];
$user_name = $user['nama'] ?? 'User';
$user_role = $user['role'] ?? 'pegawai';

// Filter parameter dari GET
$filter_status = $_GET['status'] ?? '';
$filter_bulan = $_GET['bulan'] ?? '';
$filter_tahun = $_GET['tahun'] ?? '';

// Query dengan filter
$where_conditions = ["p.user_id = '$user_id'"];

if ($filter_status) {
    $where_conditions[] = "p.status = '$filter_status'";
}

if ($filter_bulan && $filter_tahun) {
    $where_conditions[] = "YEAR(p.tanggal_pinjam) = '$filter_tahun' AND MONTH(p.tanggal_pinjam) = '$filter_bulan'";
} elseif ($filter_tahun) {
    $where_conditions[] = "YEAR(p.tanggal_pinjam) = '$filter_tahun'";
} elseif ($filter_bulan) {
    $where_conditions[] = "MONTH(p.tanggal_pinjam) = '$filter_bulan'";
}

$where_clause = implode(' AND ', $where_conditions);

require_once('../tcpdf/tcpdf.php');

class PDF extends TCPDF
{
    public function Header()
    {
        $image_file = '../assets/banjarbaru.png';
        $this->Image($image_file, 15, 10, 20, 20, 'PNG');

        $this->SetFont('helvetica', 'B', 12);
        $this->SetXY(40, 12);
        $this->Cell(150, 0, 'PEMERINTAH KOTA BANJARBARU', 0, 1, 'C');

        $this->SetFont('helvetica', 'B', 11);
        $this->SetXY(40, 18);
        $this->Cell(150, 0, 'DINAS KOMUNIKASI DAN INFORMATIKA', 0, 1, 'C');

        $this->SetFont('helvetica', '', 9);
        $this->SetXY(40, 24);
        $this->Cell(150, 0, 'Jl. Pangeran Suriansyah Nomor 5 Banjarbaru – Kalimantan Selatan', 0, 1, 'C');

        $this->SetFont('helvetica', '', 8);
        $this->SetXY(40, 29);
        $this->Cell(150, 0, 'Telp./Fax (0511) 5200052 Email : diskominfo@banjarbarukota.go.id', 0, 1, 'C');

        $this->SetLineWidth(0.8);
        $this->Line(15, 38, 195, 38);

        $this->SetY(42);
    }

    public function Footer()
    {
        $this->SetY(-20);
        $this->SetFont('helvetica', 'I', 8);
        $this->Line(15, $this->GetY() - 5, 195, $this->GetY() - 5);

        $this->SetX(15);
        $this->Cell(100, 5, 'Dicetak oleh : ' . $_SESSION['user']['nama'], 0, 0, 'L');

        $this->SetX(-60);
        $this->Cell(50, 5, 'Halaman ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, 0, 'R');
    }
}

$pdf = new PDF('P', 'mm', 'A4', true, 'UTF-8');
$pdf->SetMargins(15, 42, 15);
$pdf->SetAutoPageBreak(TRUE, 20);
$pdf->AddPage();

// Judul
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 7, 'LAPORAN RIWAYAT PEMINJAMAN MOBIL DINAS', 0, 1, 'C');
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(6);

// Info Pegawai
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(35, 6, 'Nama Pegawai', 0, 0);
$pdf->Cell(5, 6, ':', 0, 0);
$pdf->Cell(0, 6, $user_name, 0, 1);

$pdf->Cell(35, 6, 'ID Pegawai', 0, 0);
$pdf->Cell(5, 6, ':', 0, 0);
$pdf->Cell(0, 6, $user_id, 0, 1);

$pdf->Cell(35, 6, 'Jabatan', 0, 0);
$pdf->Cell(5, 6, ':', 0, 0);
$pdf->Cell(0, 6, ucfirst($user_role), 0, 1);

// Info Filter
if ($filter_status || $filter_bulan || $filter_tahun) {
    $pdf->Cell(35, 6, 'Filter', 0, 0);
    $pdf->Cell(5, 6, ':', 0, 0);

    $filters = [];
    if ($filter_status) $filters[] = "Status: " . $filter_status;
    if ($filter_bulan) $filters[] = "Bulan: " . date('F', mktime(0, 0, 0, $filter_bulan, 1));
    if ($filter_tahun) $filters[] = "Tahun: " . $filter_tahun;

    $pdf->Cell(0, 6, implode(" | ", $filters), 0, 1);
}

$pdf->Cell(35, 6, 'Tanggal Cetak', 0, 0);
$pdf->Cell(5, 6, ':', 0, 0);
$pdf->Cell(0, 6, date('d/m/Y'), 0, 1);
$pdf->Ln(6);

// Query Data
$riwayat_pdf = mysqli_query($conn, "
    SELECT 
        p.*, 
        m.nama_mobil, 
        m.nomor_plat,
        CASE 
            WHEN p.tanggal_kembali IS NOT NULL 
            THEN TIMESTAMPDIFF(MINUTE, 
                CONCAT(p.tanggal_pinjam, ' ', p.jam_mulai), 
                CONCAT(p.tanggal_kembali, ' ', p.jam_selesai)
            )
            ELSE 0
        END as durasi_minutes
    FROM peminjaman p
    JOIN mobil m ON p.mobil_id = m.id
    WHERE $where_clause
    ORDER BY p.tanggal_pinjam DESC, p.jam_mulai DESC
");

// Cek jika query gagal
if (!$riwayat_pdf) {
    die("Error dalam query: " . mysqli_error($conn));
}

// Lebar tabel - DISESUAIKAN DENGAN LEBAR HALAMAN (180mm total)
$w = [10, 25, 35, 20, 35, 25, 30]; // Total: 180mm (sesuaikan dengan margin)
$header = ['No', 'Tanggal', 'Mobil', 'Plat', 'Jam & Durasi', 'Status', 'Keperluan'];

$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(220, 220, 220);

// Header tabel - semua rata tengah
foreach ($header as $i => $h) {
    $pdf->Cell($w[$i], 7, $h, 1, 0, 'C', true);
}
$pdf->Ln();

$pdf->SetFont('helvetica', '', 8);
$pdf->SetFillColor(245, 245, 245);

$no = 1;
$total_rows = mysqli_num_rows($riwayat_pdf);

if ($total_rows > 0) {
    mysqli_data_seek($riwayat_pdf, 0);

    while ($row = mysqli_fetch_assoc($riwayat_pdf)) {
        $tanggal = date('d/m/Y', strtotime($row['tanggal_pinjam']));
        $jam_mulai = date('H:i', strtotime($row['jam_mulai']));
        $jam_selesai = date('H:i', strtotime($row['jam_selesai']));

        // Perhitungan durasi
        $durasi_text = '-';
        if ($row['durasi_minutes'] > 0) {
            $jam_d = floor($row['durasi_minutes'] / 60);
            $mnt_d = $row['durasi_minutes'] % 60;

            if ($jam_d > 0 && $mnt_d > 0) {
                $durasi_text = $jam_d . "j " . $mnt_d . "m";
            } elseif ($jam_d > 0) {
                $durasi_text = $jam_d . "j";
            } elseif ($mnt_d > 0) {
                $durasi_text = $mnt_d . "m";
            }
        }

        $fill = ($no % 2 == 0);
        $row_height = 12; // Tinggi baris untuk 2 baris data

        // Baris 1: Data utama - SEMUA RATA TENGAH
        $pdf->Cell($w[0], $row_height / 2, $no, 'LTR', 0, 'C', $fill);
        $pdf->Cell($w[1], $row_height / 2, $tanggal, 'LTR', 0, 'C', $fill);
        $pdf->Cell($w[2], $row_height / 2, $row['nama_mobil'], 'LTR', 0, 'C', $fill);
        $pdf->Cell($w[3], $row_height / 2, $row['nomor_plat'], 'LTR', 0, 'C', $fill);

        // Kolom Jam & Durasi - baris 1 (Jam)
        $pdf->Cell($w[4], $row_height / 2, $jam_mulai . ' - ' . $jam_selesai, 'LTR', 0, 'C', $fill);

        $pdf->Cell($w[5], $row_height / 2, $row['status'], 'LTR', 0, 'C', $fill);
        $pdf->Cell($w[6], $row_height / 2, $row['keperluan'], 'LTR', 1, 'C', $fill);

        // Baris 2: Hanya untuk kolom Jam & Durasi (durasi) dan border bawah untuk semua
        $pdf->Cell($w[0], $row_height / 2, '', 'LBR', 0, 'C', $fill);
        $pdf->Cell($w[1], $row_height / 2, '', 'LBR', 0, 'C', $fill);
        $pdf->Cell($w[2], $row_height / 2, '', 'LBR', 0, 'C', $fill);
        $pdf->Cell($w[3], $row_height / 2, '', 'LBR', 0, 'C', $fill);

        // Kolom Jam & Durasi - baris 2 (Durasi)
        $pdf->SetFont('helvetica', 'I', 7);
        $pdf->Cell($w[4], $row_height / 2, 'Durasi: ' . $durasi_text, 'LBR', 0, 'C', $fill);
        $pdf->SetFont('helvetica', '', 8);

        $pdf->Cell($w[5], $row_height / 2, '', 'LBR', 0, 'C', $fill);
        $pdf->Cell($w[6], $row_height / 2, '', 'LBR', 1, 'C', $fill);

        $no++;
    }
} else {
    $pdf->Cell(array_sum($w), 8, 'Tidak ada data riwayat peminjaman', 1, 1, 'C');
}

// Tanda tangan kanan rata tengah
$pdf->Ln(10);
$ttdWidth = 70;
$posX = 195 - $ttdWidth;

$pdf->SetX($posX);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell($ttdWidth, 6, 'Banjarbaru, ' . date('d F Y'), 0, 1, 'C');
$pdf->SetX($posX);
$pdf->Cell($ttdWidth, 6, 'Mengetahui,', 0, 1, 'C');
$pdf->Ln(8);

$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetX($posX);
$pdf->Cell($ttdWidth, 6, 'Drs. KRISMAN', 0, 1, 'C');

$pdf->SetFont('helvetica', '', 9);
$pdf->SetX($posX);
$pdf->Cell($ttdWidth, 5, 'Pembina IV/a', 0, 1, 'C');

$pdf->SetX($posX);
$pdf->Cell($ttdWidth, 5, 'NIP 19730303 200003 1 009', 0, 1, 'C');

// Footer info cetak
// $pdf->SetY(-25);
// $pdf->SetFont('helvetica', 'I', 8);
// $pdf->Cell(0, 5, 'Dicetak oleh: ' . $_SESSION['user']['nama'] . ' (' . ucfirst($_SESSION['user']['role']) . ')', 0, 1, 'L');

$pdf->Output('Laporan_Riwayat_Peminjaman_' . $user_name . '_' . date('Y-m-d') . '.pdf', 'I');
exit;
