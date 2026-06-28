-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 28, 2026 at 07:45 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_peminjaman_mobil`
--

-- --------------------------------------------------------

--
-- Table structure for table `bidang`
--

CREATE TABLE `bidang` (
  `id` int(11) NOT NULL,
  `nama_bidang` varchar(100) NOT NULL,
  `kode_bidang` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bidang`
--

INSERT INTO `bidang` (`id`, `nama_bidang`, `kode_bidang`) VALUES
(1, 'Statistik & Persandian', 'SP');

-- --------------------------------------------------------

--
-- Table structure for table `mobil`
--

CREATE TABLE `mobil` (
  `id` int(11) NOT NULL,
  `nama_mobil` varchar(100) DEFAULT NULL,
  `nomor_plat` varchar(20) DEFAULT NULL,
  `tahun` year(4) DEFAULT NULL,
  `warna` varchar(30) DEFAULT NULL,
  `kapasitas` int(2) DEFAULT NULL,
  `status` enum('Tersedia','Dipinjam') DEFAULT 'Tersedia',
  `foto` varchar(255) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `dipakai_oleh` varchar(255) DEFAULT '-'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mobil`
--

INSERT INTO `mobil` (`id`, `nama_mobil`, `nomor_plat`, `tahun`, `warna`, `kapasitas`, `status`, `foto`, `keterangan`, `dipakai_oleh`) VALUES
(1, 'Toyota Avanza', 'DA 1234 AB', '2006', 'Silver', 7, 'Dipinjam', NULL, '-', '2'),
(2, 'Mitsubishi Xpander', 'DA 9876 CD', '2021', 'Hitam', 9, 'Tersedia', NULL, '-', NULL),
(3, 'Toyota Innova', 'DA 5555 EF', '2010', 'Putih', 8, 'Tersedia', NULL, '-', NULL),
(4, 'Honda Civic', 'DA 8137 PCH', '2018', 'PINK', 5, 'Tersedia', NULL, 'Mobil baru', NULL),
(5, 'TOYOTA RUSH', 'DA 2472 PCS', '2024', 'Putih', 8, 'Tersedia', '1770210541_TOYOTA RUSH 1_5L GR M_T 2021.jpg', 'Mobil Second', NULL),
(6, 'BMW', 'DA 9739 PSJ', '2021', 'Biru', 6, 'Tersedia', '', '', NULL),
(7, 'Brio', 'DA 2841 PCM', '2025', 'Silver', 5, 'Tersedia', '', 'Ini Mobil Second', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `peminjaman`
--

CREATE TABLE `peminjaman` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `mobil_id` int(11) DEFAULT NULL,
  `tanggal_pinjam` date DEFAULT NULL,
  `jam_mulai` time DEFAULT NULL,
  `jam_selesai` time DEFAULT NULL,
  `keperluan` text DEFAULT NULL,
  `tanggal_kembali` date DEFAULT NULL,
  `status` enum('Menunggu ACC','Dipinjam','Dikembalikan','Ditolak','Dibatalkan') DEFAULT 'Menunggu ACC'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `peminjaman`
--

INSERT INTO `peminjaman` (`id`, `user_id`, `mobil_id`, `tanggal_pinjam`, `jam_mulai`, `jam_selesai`, `keperluan`, `tanggal_kembali`, `status`) VALUES
(32, 2, 1, '2025-12-19', '07:25:00', '09:25:00', 'wqwfwegf', '2025-12-19', 'Dikembalikan'),
(33, 2, 1, '2025-12-20', '07:42:00', '09:42:00', 'rapat', '2025-12-20', 'Dikembalikan'),
(34, 2, 1, '2025-12-21', '07:49:00', '09:53:00', 'jalan2', '2025-12-21', 'Dikembalikan'),
(36, 2, 2, '2025-12-24', '07:10:00', '08:24:00', 'jalan2', '2025-12-24', 'Dikembalikan'),
(39, 2, 1, '2026-01-07', '07:29:00', '08:30:00', 'fasefa', '2026-01-10', 'Dikembalikan'),
(40, 2, 2, '2026-01-12', '07:05:00', '10:05:00', 'jalan duls', '2026-01-12', 'Dikembalikan'),
(41, 4, 3, '2026-01-14', '14:39:00', '16:39:00', 'makan makan', '2026-01-14', 'Dikembalikan'),
(42, 4, 2, '2026-01-15', '08:00:00', '14:15:00', 'bejalanan', '2026-01-20', 'Dikembalikan'),
(43, 3, 1, '2026-01-15', '10:02:00', '15:02:00', 'jabdbqwfuvwuef', '2026-01-18', 'Dikembalikan'),
(44, 2, 4, '2026-01-20', '15:41:00', '16:43:00', 'fwhfuiwehfiwebfhjwe', '2026-01-20', 'Dikembalikan'),
(45, 4, 4, '2026-01-23', '12:00:00', '15:55:00', 'sholat jumat', '2026-01-23', 'Dikembalikan'),
(46, 2, 1, '2026-01-27', '08:47:00', '10:43:00', 'belalah', '2026-01-27', 'Dikembalikan'),
(47, 3, 3, '2026-01-28', '08:01:00', '14:08:00', 'terserah', '2026-01-28', 'Ditolak'),
(48, 3, 1, '2026-01-28', '07:10:00', '08:05:00', 'joging', '2026-01-29', 'Dikembalikan'),
(49, 3, 3, '2026-01-30', '07:01:00', '07:11:00', 'makan bubur', NULL, 'Ditolak'),
(50, 2, 1, '2026-02-06', '13:55:00', '16:00:00', 'sholat jumat', '2026-02-18', 'Dikembalikan'),
(51, 3, 5, '2026-02-06', '15:40:00', '16:55:00', 'Untuk Rapat Di DPR', '2026-02-18', 'Dikembalikan'),
(52, 5, 6, '2026-02-06', '16:05:00', '17:00:00', 'Rapat Dibalaikota', '2026-02-06', 'Dikembalikan'),
(53, 3, 6, '2026-02-19', '07:00:00', '09:00:00', 'presentasi', NULL, 'Dibatalkan'),
(54, 2, 1, '2026-02-19', '10:25:00', '14:12:00', 'rapat', NULL, 'Dipinjam');

-- --------------------------------------------------------

--
-- Table structure for table `seksi`
--

CREATE TABLE `seksi` (
  `id` int(11) NOT NULL,
  `bidang_id` int(11) NOT NULL,
  `nama_seksi` varchar(100) NOT NULL,
  `kode_seksi` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `seksi`
--

INSERT INTO `seksi` (`id`, `bidang_id`, `nama_seksi`, `kode_seksi`) VALUES
(1, 1, 'Statistik', 'SP-S'),
(2, 1, 'Persandian', 'SP-P');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) DEFAULT NULL,
  `nip` varchar(30) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('pegawai','admin') DEFAULT 'pegawai',
  `jabatan` varchar(100) DEFAULT NULL,
  `bidang_id` int(11) DEFAULT NULL,
  `seksi_id` int(11) DEFAULT NULL,
  `no_hp` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `nama`, `nip`, `password`, `role`, `jabatan`, `bidang_id`, `seksi_id`, `no_hp`) VALUES
(1, 'Administrator Diskominfo', 'admin123', 'admin123', 'admin', NULL, NULL, NULL, NULL),
(2, 'yusfi', '111222333', '12345', 'pegawai', 'Staff', 1, 2, '08123456789'),
(3, 'Ahmad', '111333222', '12345', 'pegawai', 'Staff', 1, 1, '08124719475'),
(4, 'Roby', '111222', '12345', 'pegawai', 'staff', 1, 1, '08163176326'),
(5, 'Julio', '333222111', '12345', 'pegawai', 'Staff', 1, 2, '08163176312'),
(6, 'MUHAMMAD HENDRA', '444333222', '$2y$10$c8boqdVlnSP5eWqu3/6ci.UySJA2c5lgS.RCDOzaaIqsS5vkcgqpa', 'pegawai', 'staff', 1, 1, '08163176331');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bidang`
--
ALTER TABLE `bidang`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_bidang` (`kode_bidang`);

--
-- Indexes for table `mobil`
--
ALTER TABLE `mobil`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `peminjaman`
--
ALTER TABLE `peminjaman`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `mobil_id` (`mobil_id`);

--
-- Indexes for table `seksi`
--
ALTER TABLE `seksi`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_seksi` (`kode_seksi`),
  ADD KEY `bidang_id` (`bidang_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nip` (`nip`),
  ADD KEY `bidang_id` (`bidang_id`),
  ADD KEY `seksi_id` (`seksi_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bidang`
--
ALTER TABLE `bidang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `mobil`
--
ALTER TABLE `mobil`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `peminjaman`
--
ALTER TABLE `peminjaman`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `seksi`
--
ALTER TABLE `seksi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `peminjaman`
--
ALTER TABLE `peminjaman`
  ADD CONSTRAINT `peminjaman_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `peminjaman_ibfk_2` FOREIGN KEY (`mobil_id`) REFERENCES `mobil` (`id`);

--
-- Constraints for table `seksi`
--
ALTER TABLE `seksi`
  ADD CONSTRAINT `seksi_ibfk_1` FOREIGN KEY (`bidang_id`) REFERENCES `bidang` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`bidang_id`) REFERENCES `bidang` (`id`),
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`seksi_id`) REFERENCES `seksi` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
