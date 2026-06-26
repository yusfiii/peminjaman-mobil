<?php
session_start();
include "../config/database.php";

// Proteksi admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

$user = $_SESSION['user'];
$user_name = $user['nama'] ?? 'Admin';
$user_role = $user['role'] ?? 'admin';

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
        $this->Cell(100, 5, 'Dicetak oleh : ' . $_SESSION['user']['nama'] . ' (Admin)', 0, 0, 'L');

        $this->SetX(-60);
        $this->Cell(50, 5, 'Halaman ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, 0, 'R');
    }
}

$pdf = new PDF('P', 'mm', 'A4', true, 'UTF-8'); // Portrait/A4 vertikal
$pdf->SetMargins(15, 42, 15);
$pdf->SetAutoPageBreak(TRUE, 20);
$pdf->AddPage();

// Judul
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 7, ' LAPORAN DAFTAR MOBIL DINAS', 0, 1, 'C');
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(8);

// Info Admin dan Tanggal
// $pdf->SetFont('helvetica', '', 10);
// $pdf->Cell(40, 6, 'Nama Admin', 0, 0);
// $pdf->Cell(5, 6, ':', 0, 0);
// $pdf->Cell(80, 6, $user_name, 0, 0);

// $pdf->Cell(20, 6, 'Tanggal', 0, 0);
// $pdf->Cell(5, 6, ':', 0, 0);
// $pdf->Cell(0, 6, date('d/m/Y H:i'), 0, 1);

// $pdf->Ln(5);

// Query Data Mobil
$mobil_query = mysqli_query($conn, "
    SELECT 
        m.*,
        (SELECT COUNT(*) FROM peminjaman WHERE mobil_id = m.id AND status = 'Dikembalikan') as total_peminjaman,
        (SELECT COUNT(*) FROM peminjaman WHERE mobil_id = m.id AND status = 'Diproses') as total_proses
    FROM mobil m
    ORDER BY m.nama_mobil ASC
");

// Judul Tabel
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, 'DAFTAR MOBIL DINAS', 0, 1, 'C');
$pdf->Ln(3);

// Lebar tabel untuk A4 vertikal (total 180mm)
$w = [10, 45, 25, 20, 20, 30, 30]; // Total: 180mm (No, Nama Mobil, Tahun, Warna, Kapasitas, Plat, Jumlah Dipinjam)

$header = ['No', 'Nama Mobil', 'Tahun', 'Warna', 'Kapasitas', 'Plat', 'Jumlah Dipinjam'];

$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(200, 200, 200);

foreach ($header as $i => $h) {
    $pdf->Cell($w[$i], 7, $h, 1, 0, 'C', true);
}
$pdf->Ln();

$pdf->SetFont('helvetica', '', 8);
$pdf->SetFillColor(245, 245, 245);

$no = 1;
if (mysqli_num_rows($mobil_query) > 0) {
    while ($row = mysqli_fetch_assoc($mobil_query)) {
        $fill = ($no % 2 == 0);

        // No
        $pdf->Cell($w[0], 8, $no, 1, 0, 'C', $fill);

        // Nama Mobil (dengan ID)
        $nama_mobil = $row['nama_mobil'] . "\n(ID: " . $row['id'] . ")";
        $pdf->Cell($w[1], 8, $nama_mobil, 1, 0, 'L', $fill);

        // Tahun
        $pdf->Cell($w[2], 8, $row['tahun'] ?: '-', 1, 0, 'C', $fill);

        // Warna
        $pdf->Cell($w[3], 8, $row['warna'] ?: '-', 1, 0, 'C', $fill);

        // Kapasitas
        $kapasitas = $row['kapasitas'] ? $row['kapasitas'] . ' org' : '-';
        $pdf->Cell($w[4], 8, $kapasitas, 1, 0, 'C', $fill);

        // Plat
        $pdf->Cell($w[5], 8, $row['nomor_plat'], 1, 0, 'C', $fill);

        // Jumlah Dipinjam
        $total_peminjaman = $row['total_peminjaman'] + $row['total_proses'];
        if ($total_peminjaman > 0) {
            $pdf->Cell($w[6], 8, $total_peminjaman . ' kali', 1, 1, 'C', $fill);
        } else {
            $pdf->Cell($w[6], 8, 'Belum', 1, 1, 'C', $fill);
        }

        $no++;
    }
} else {
    $pdf->Cell(array_sum($w), 10, 'Tidak ada data mobil', 1, 1, 'C');
}

$pdf->Ln(10);

// Hitung total mobil
$total_mobil = mysqli_num_rows($mobil_query);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 6, 'Total Mobil Dinas: ' . $total_mobil . ' unit', 0, 1, 'R');

$pdf->Ln(8);

// Catatan
$pdf->SetFont('helvetica', 'I', 8);
$pdf->SetTextColor(100, 100, 100);
$pdf->MultiCell(0, 4, "Keterangan:\n- Data diambil dari sistem pada " . date('d/m/Y H:i:s'), 0, 'L');

// Tanda tangan - DI POJOK KANAN DENGAN RATA TENGAH
$pdf->SetFont('helvetica', '', 10);
$pdf->SetTextColor(0, 0, 0);

$pdf->Ln(15); // Beri jarak dari catatan

// Posisi di pojok kanan dengan lebar tertentu
$ttdWidth = 80; // Lebar area tanda tangan
$posX = 210 - $ttdWidth; // Posisi X mulai dari 195mm (lebar A4) dikurangi lebar area tanda tangan

$pdf->SetX($posX);
$pdf->Cell($ttdWidth, 6, 'Mengetahui,', 0, 1, 'C');
$pdf->Ln(12);

$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetX($posX);
$pdf->Cell($ttdWidth, 6, 'Drs. KRISMAN', 0, 1, 'C');

$pdf->SetFont('helvetica', '', 9);
$pdf->SetX($posX);
$pdf->Cell($ttdWidth, 5, 'Pembina IV/a', 0, 1, 'C');

$pdf->SetX($posX);
$pdf->Cell($ttdWidth, 5, 'NIP 19730303 200003 1 009', 0, 1, 'C');

$pdf->SetX($posX);
$pdf->Cell($ttdWidth, 5, 'Kepala Dinas Kominfo', 0, 1, 'C');

$pdf->Output('Daftar_Mobil_Dinas_' . date('Y-m-d') . '.pdf', 'I');
exit;
