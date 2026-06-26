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

// Filter parameter
$filter_month = $_GET['bulan'] ?? 'all';
$filter_year = $_GET['tahun'] ?? 'all';

// DEFINE VARIABLE PERIODE_TEXT SEBELUM DIGUNAKAN
// Buat teks periode
if ($filter_month === 'all' && $filter_year === 'all') {
    $periode_text = "Semua Periode";
} elseif ($filter_month === 'all' && $filter_year !== 'all') {
    $periode_text = "Tahun " . $filter_year;
} elseif ($filter_month !== 'all' && $filter_year === 'all') {
    $periode_text = "Bulan " . date('F', mktime(0, 0, 0, $filter_month, 1));
} else {
    $periode_text = date('F', mktime(0, 0, 0, $filter_month, 1)) . " " . $filter_year;
}

// Build WHERE clause
$where_clause = "";
if ($filter_month !== 'all' && $filter_year !== 'all') {
    $where_clause = "WHERE MONTH(tanggal_pinjam) = '$filter_month' AND YEAR(tanggal_pinjam) = '$filter_year'";
} elseif ($filter_month !== 'all') {
    $where_clause = "WHERE MONTH(tanggal_pinjam) = '$filter_month'";
} elseif ($filter_year !== 'all') {
    $where_clause = "WHERE YEAR(tanggal_pinjam) = '$filter_year'";
}

// QUERY DATA UNTUK LAPORAN PEGAWAI
// 1. Pegawai Paling Aktif (TOP 10)
$pegawai_aktif_query = "
    SELECT u.nama, u.nip, b.nama_bidang, COUNT(p.id) as jumlah_peminjaman,
           SUM(TIMESTAMPDIFF(HOUR, p.jam_mulai, p.jam_selesai)) as total_jam,
           ROUND(AVG(TIMESTAMPDIFF(HOUR, p.jam_mulai, p.jam_selesai)), 1) as rata_jam
    FROM peminjaman p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN bidang b ON u.bidang_id = b.id
    WHERE p.jam_mulai IS NOT NULL 
    AND p.jam_selesai IS NOT NULL";

if ($filter_month !== 'all' && $filter_year !== 'all') {
    $pegawai_aktif_query .= " AND MONTH(p.tanggal_pinjam) = '$filter_month' AND YEAR(p.tanggal_pinjam) = '$filter_year'";
} elseif ($filter_month !== 'all') {
    $pegawai_aktif_query .= " AND MONTH(p.tanggal_pinjam) = '$filter_month'";
} elseif ($filter_year !== 'all') {
    $pegawai_aktif_query .= " AND YEAR(p.tanggal_pinjam) = '$filter_year'";
}

$pegawai_aktif_query .= " GROUP BY p.user_id ORDER BY jumlah_peminjaman DESC LIMIT 10";
$pegawai_aktif = mysqli_query($conn, $pegawai_aktif_query);

// 2. Distribusi Peminjam per Unit Kerja
$distribusi_unit_query = "
    SELECT 
        COALESCE(b.nama_bidang, 'Tidak Ada Bidang') as nama_bidang,
        COALESCE(b.kode_bidang, '-') as kode_bidang,
        COUNT(DISTINCT p.user_id) as jumlah_pegawai,
        COUNT(p.id) as total_peminjaman,
        SUM(TIMESTAMPDIFF(HOUR, p.jam_mulai, p.jam_selesai)) as total_jam,
        ROUND(AVG(TIMESTAMPDIFF(HOUR, p.jam_mulai, p.jam_selesai)), 1) as rata_rata_durasi
    FROM peminjaman p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN bidang b ON u.bidang_id = b.id
    WHERE p.jam_mulai IS NOT NULL 
    AND p.jam_selesai IS NOT NULL";

if ($filter_month !== 'all' && $filter_year !== 'all') {
    $distribusi_unit_query .= " AND MONTH(p.tanggal_pinjam) = '$filter_month' AND YEAR(p.tanggal_pinjam) = '$filter_year'";
} elseif ($filter_month !== 'all') {
    $distribusi_unit_query .= " AND MONTH(p.tanggal_pinjam) = '$filter_month'";
} elseif ($filter_year !== 'all') {
    $distribusi_unit_query .= " AND YEAR(p.tanggal_pinjam) = '$filter_year'";
}

$distribusi_unit_query .= " GROUP BY COALESCE(b.id, 0), COALESCE(b.nama_bidang, 'Tidak Ada Bidang'), COALESCE(b.kode_bidang, '-')
    ORDER BY total_peminjaman DESC, jumlah_pegawai DESC";
$distribusi_unit = mysqli_query($conn, $distribusi_unit_query);

// 3. Statistik Total
$total_peminjaman_query = "SELECT COUNT(*) as total FROM peminjaman";
if ($where_clause) {
    $total_peminjaman_query .= " " . $where_clause;
}
$total_peminjaman = mysqli_fetch_assoc(mysqli_query($conn, $total_peminjaman_query));

$total_pegawai_query = "SELECT COUNT(DISTINCT user_id) as total FROM peminjaman";
if ($where_clause) {
    $total_pegawai_query .= " " . $where_clause;
}
$total_pegawai = mysqli_fetch_assoc(mysqli_query($conn, $total_pegawai_query));

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

$pdf = new PDF('P', 'mm', 'A4', true, 'UTF-8');
$pdf->SetMargins(15, 42, 15);
$pdf->SetAutoPageBreak(TRUE, 20);
$pdf->AddPage();

// Judul
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 7, 'LAPORAN ANALISIS PEGAWAI', 0, 1, 'C');
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(6);

// Statistik Ringkasan
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 6, 'RINGKASAN STATISTIK', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 9);

// Buat tabel ringkasan - LEBAR PENUH
$pdf->SetFillColor(240, 240, 240); // Warna abu-abu muda untuk header tabel

// Lebar kolom disamakan ke samping (total 180mm)
$w_summary = [50, 40, 50, 40]; // Total: 180mm
$pdf->Cell($w_summary[0], 7, 'Total Peminjaman', 1, 0, 'L', true);
$pdf->Cell($w_summary[1], 7, ($total_peminjaman['total'] ?? 0) . ' transaksi', 1, 0, 'C');
$pdf->Cell($w_summary[2], 7, 'Total Pegawai Aktif', 1, 0, 'L', true);
$pdf->Cell($w_summary[3], 7, ($total_pegawai['total'] ?? 0) . ' orang', 1, 1, 'C');

$pdf->Ln(8);

// BAGIAN 1: TOP 10 PEGAWAI TERAKTIF
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 6, '1. TOP 10 PEGAWAI TERAKTIF', 0, 1, 'L');
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(0, 4, 'Pegawai dengan jumlah peminjaman terbanyak ' . strtolower($periode_text), 0, 1, 'L');
$pdf->Ln(1);

// Tabel Pegawai Teraktif - LEBAR PENUH
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetFillColor(240, 240, 240); // Abu-abu muda untuk header

// SESUAIKAN LEBAR KOLOM: PERKECIL NAMA, BESARKAN JML PEMINJAMAN
$w = [10, 55, 40, 25, 25, 25]; // Total: 180mm (No(10), Nama(55), Unit(40), Jml Peminjaman(25), Total Jam(25), Rata Jam(25))

$header = ['No', 'Nama Pegawai', 'Unit Kerja', 'Jml Peminjaman', 'Total Jam', 'Rata Jam'];

foreach ($header as $i => $h) {
    $pdf->Cell($w[$i], 6, $h, 1, 0, 'C', true);
}
$pdf->Ln();

$pdf->SetFont('helvetica', '', 8);
$no = 1;

if (mysqli_num_rows($pegawai_aktif) > 0) {
    while ($pegawai = mysqli_fetch_assoc($pegawai_aktif)) {
        $fill = ($no % 2 == 0) ? 1 : 0; // Alternating row colors

        $pdf->SetFillColor($fill ? 250 : 255, $fill ? 250 : 255, $fill ? 250 : 255); // Putih dan abu-abu sangat muda

        // Format nama dengan strip: yusfi - 111222333
        $nama_dengan_nip = $pegawai['nama'] . ' - ' . $pegawai['nip'];

        $pdf->Cell($w[0], 6, $no, 1, 0, 'C', $fill);
        $pdf->Cell($w[1], 6, $nama_dengan_nip, 1, 0, 'L', $fill);
        $pdf->Cell($w[2], 6, $pegawai['nama_bidang'] ?? '-', 1, 0, 'L', $fill);
        $pdf->Cell($w[3], 6, $pegawai['jumlah_peminjaman'], 1, 0, 'C', $fill);
        $pdf->Cell($w[4], 6, $pegawai['total_jam'] . ' jam', 1, 0, 'C', $fill);
        $pdf->Cell($w[5], 6, $pegawai['rata_jam'] . ' jam', 1, 1, 'C', $fill);

        $no++;
    }
} else {
    $pdf->Cell(array_sum($w), 6, 'Tidak ada data pegawai', 1, 1, 'C');
}

$pdf->Ln(8);

// BAGIAN 2: DISTRIBUSI PER UNIT KERJA
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 6, '2. DISTRIBUSI PEMINJAMAN PER UNIT KERJA', 0, 1, 'L');
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(0, 4, 'Analisis aktivitas peminjaman berdasarkan bidang/seksi', 0, 1, 'L');
$pdf->Ln(1);

// Tabel Distribusi Unit Kerja - LEBAR PENUH
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetFillColor(240, 240, 240); // Abu-abu muda untuk header

// SESUAIKAN LEBAR KOLOM AGAR MELEBAR PENUH (total 180mm)
$w2 = [10, 70, 25, 25, 25, 25]; // Total: 180mm (No, Unit, Kode, Jml Peg, Total Peminj, Total Jam)

$header2 = ['No', 'Unit Kerja', 'Kode', 'Jml Pegawai', 'Total Peminjaman', 'Total Jam'];

foreach ($header2 as $i => $h) {
    $pdf->Cell($w2[$i], 6, $h, 1, 0, 'C', true);
}
$pdf->Ln();

$pdf->SetFont('helvetica', '', 8);
$no2 = 1;
$total_all_pegawai = 0;
$total_all_peminjaman = 0;
$total_all_jam = 0;

if (mysqli_num_rows($distribusi_unit) > 0) {
    mysqli_data_seek($distribusi_unit, 0);
    while ($unit = mysqli_fetch_assoc($distribusi_unit)) {
        $fill = ($no2 % 2 == 0) ? 1 : 0; // Alternating row colors

        $pdf->SetFillColor($fill ? 250 : 255, $fill ? 250 : 255, $fill ? 250 : 255); // Putih dan abu-abu sangat muda

        $pdf->Cell($w2[0], 6, $no2, 1, 0, 'C', $fill);
        $pdf->Cell($w2[1], 6, $unit['nama_bidang'], 1, 0, 'L', $fill);
        $pdf->Cell($w2[2], 6, $unit['kode_bidang'], 1, 0, 'C', $fill);
        $pdf->Cell($w2[3], 6, $unit['jumlah_pegawai'] . ' org', 1, 0, 'C', $fill);
        $pdf->Cell($w2[4], 6, $unit['total_peminjaman'], 1, 0, 'C', $fill);
        $pdf->Cell($w2[5], 6, $unit['total_jam'] . ' jam', 1, 1, 'C', $fill);

        $total_all_pegawai += $unit['jumlah_pegawai'];
        $total_all_peminjaman += $unit['total_peminjaman'];
        $total_all_jam += $unit['total_jam'];
        $no2++;
    }

    // Total baris - tebal
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetFillColor(220, 220, 220); // Abu-abu sedikit lebih gelap
    $pdf->Cell($w2[0], 6, '', 1, 0, 'C', true);
    $pdf->Cell($w2[1], 6, 'TOTAL', 1, 0, 'C', true);
    $pdf->Cell($w2[2], 6, '', 1, 0, 'C', true);
    $pdf->Cell($w2[3], 6, $total_all_pegawai . ' org', 1, 0, 'C', true);
    $pdf->Cell($w2[4], 6, $total_all_peminjaman, 1, 0, 'C', true);
    $pdf->Cell($w2[5], 6, $total_all_jam . ' jam', 1, 1, 'C', true);
} else {
    $pdf->Cell(array_sum($w2), 6, 'Tidak ada data distribusi unit', 1, 1, 'C');
}

$pdf->Ln(8);

// ANALISIS DAN KESIMPULAN
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 6, 'ANALISIS DAN KESIMPULAN', 0, 1, 'L');

$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(0, 5, 'A. PEGAWAI TERAKTIF:', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 8);

// Ambil data pegawai teraktif untuk kesimpulan
mysqli_data_seek($pegawai_aktif, 0);
if ($top_pegawai = mysqli_fetch_assoc($pegawai_aktif)) {
    // Format nama tanpa NIP untuk kesimpulan
    $pdf->MultiCell(0, 4, "1. Pegawai paling aktif: " . $top_pegawai['nama'] . " (" . ($top_pegawai['nama_bidang'] ?? '-') . ") dengan " . $top_pegawai['jumlah_peminjaman'] . " peminjaman dan total " . $top_pegawai['total_jam'] . " jam penggunaan.", 0, 'L');
}

$pdf->MultiCell(0, 4, "2. Rata-rata durasi peminjaman: " . ($top_pegawai['rata_jam'] ?? 0) . " jam per peminjaman.", 0, 'L');
$pdf->MultiCell(0, 4, "3. Total " . ($total_pegawai['total'] ?? 0) . " pegawai aktif melakukan peminjaman " . strtolower($periode_text) . ".", 0, 'L');

$pdf->Ln(2);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(0, 5, 'B. DISTRIBUSI UNIT KERJA:', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 8);

// Ambil unit kerja dengan aktivitas tertinggi
mysqli_data_seek($distribusi_unit, 0);
if ($top_unit = mysqli_fetch_assoc($distribusi_unit)) {
    $pdf->MultiCell(0, 4, "1. Unit kerja paling aktif: " . $top_unit['nama_bidang'] . " dengan " . $top_unit['total_peminjaman'] . " peminjaman oleh " . $top_unit['jumlah_pegawai'] . " pegawai.", 0, 'L');
}

$pdf->MultiCell(0, 4, "2. Rata-rata peminjaman per unit: " . ($total_all_peminjaman > 0 ? round($total_all_peminjaman / max(1, $no2 - 1), 1) : 0) . " peminjaman.", 0, 'L');
$pdf->MultiCell(0, 4, "3. Distribusi menunjukkan variasi aktivitas antar unit kerja yang perlu diperhatikan untuk optimalisasi.", 0, 'L');

// TANDA TANGAN - LEBIH KE KANAN DAN TAMBAH BANJARBARU, TANGGAL
$pdf->Ln(10);

// Hitung posisi untuk tanda tangan (lebih ke kanan lagi)
$ttdWidth = 70; // Lebar area tanda tangan
$posX = 215 - $ttdWidth - 15; // 15mm dari kanan (lebih ke kanan lagi)

// Tambahkan "Banjarbaru, tanggal hari ini"
$pdf->SetX($posX);
$pdf->SetFont('helvetica', '', 9);
$tanggal_hari_ini = date('d F Y'); // Format: 04 February 2026
$pdf->Cell($ttdWidth, 5, 'Banjarbaru, ' . $tanggal_hari_ini, 0, 1, 'C');

$pdf->SetX($posX);
$pdf->Cell($ttdWidth, 5, 'Mengetahui,', 0, 1, 'C');
$pdf->Ln(8);

$pdf->SetX($posX);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell($ttdWidth, 5, 'Drs. KRISMAN', 0, 1, 'C');

$pdf->SetX($posX);
$pdf->SetFont('helvetica', '', 8);
$pdf->Cell($ttdWidth, 4, 'Pembina IV/a', 0, 1, 'C');

$pdf->SetX($posX);
$pdf->Cell($ttdWidth, 4, 'NIP 19730303 200003 1 009', 0, 1, 'C');

$pdf->SetX($posX);
$pdf->Cell($ttdWidth, 4, 'Kepala Dinas Kominfo', 0, 1, 'C');

$pdf->Output('Laporan_Analisis_Pegawai_' . date('Y-m-d') . '.pdf', 'I');
exit;
