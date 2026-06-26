<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek login
if (!isset($_SESSION['user'])) {
    header("Location: ../auth/login.php");
    exit;
}

$role = $_SESSION['user']['role'];
$user_name = $_SESSION['user']['nama'] ?? 'User';
$current_page = basename($_SERVER['PHP_SELF']);
?>

<style>
    :root {
        --sidebar-width: 260px;
        --sidebar-collapsed-width: 70px;
        --sidebar-bg: #2C3E50;
        --accent-color: #7B1FA2;
        --hover-color: rgba(123, 31, 162, 0.2);
        --text-primary: #FFFFFF;
        --text-secondary: #B0BEC5;
    }

    /* Sidebar Base */
    .sidebar {
        width: var(--sidebar-width);
        height: 100vh;
        position: fixed;
        left: 0;
        top: 0;
        background: var(--sidebar-bg);
        color: var(--text-primary);
        z-index: 1000;
        display: flex;
        flex-direction: column;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 5px 0 15px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }

    /* Logo & Header */
    .sidebar-header {
        padding: 20px 15px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        position: relative;
    }

    .logo {
        display: flex;
        align-items: center;
        gap: 12px;
        transition: all 0.3s ease;
    }

    .logo-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 12px rgba(156, 39, 176, 0.3);
    }

    .logo-icon i {
        font-size: 1.2rem;
    }

    .logo-text h4 {
        font-size: 1rem;
        font-weight: 700;
        margin: 0;
        color: white;
        letter-spacing: 0.5px;
    }

    .logo-text p {
        font-size: 0.7rem;
        margin: 2px 0 0;
        opacity: 0.8;
        color: var(--text-secondary);
    }

    /* User Box */
    .user-info {
        padding: 15px;
        background: rgba(255, 255, 255, 0.05);
        margin: 15px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        gap: 12px;
        border: 1px solid rgba(255, 255, 255, 0.05);
        transition: all 0.3s ease;
    }

    .user-info:hover {
        background: rgba(123, 31, 162, 0.25);
        transform: translateY(-2px);
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px solid rgba(255, 255, 255, 0.1);
    }

    .user-avatar i {
        font-size: 1rem;
    }

    .user-details {
        flex: 1;
        min-width: 0;
    }

    .user-name {
        font-size: 0.85rem;
        font-weight: 600;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        color: white;
    }

    .user-role {
        font-size: 0.65rem;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 3px 10px;
        border-radius: 20px;
        color: #ffffff;
        font-weight: 500;
        display: inline-block;
        margin-top: 3px;
        border: 1px solid rgba(156, 39, 176, 0.3);
    }

    /* Nav Links */
    .nav-container {
        flex: 1;
        overflow-y: auto;
        padding: 10px 15px;
        scrollbar-width: thin;
        scrollbar-color: rgba(255, 255, 255, 0.2) transparent;
    }

    .nav-container::-webkit-scrollbar {
        width: 4px;
    }

    .nav-container::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.2);
        border-radius: 10px;
    }

    .nav-group {
        margin-bottom: 20px;
    }

    .nav-group-title {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #D1C4E9;
        padding: 0 15px 8px;
        margin-bottom: 8px;
        border-bottom: 1px solid rgba(156, 39, 176, 0.2);
    }

    .nav-link {
        display: flex;
        align-items: center;
        padding: 12px 15px;
        color: #E1BEE7;
        text-decoration: none;
        border-radius: 10px;
        margin-bottom: 5px;
        transition: all 0.3s ease;
        position: relative;
    }

    .nav-link:hover {
        background: rgba(123, 31, 162, 0.15);
        color: white;
        transform: translateX(5px);
    }

    .nav-link.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        box-shadow: 0 4px 12px rgba(156, 39, 176, 0.3);
    }

    .nav-link.active::before {
        content: '';
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        width: 4px;
        height: 60%;
        background: #E1BEE7;
        border-radius: 0 4px 4px 0;
    }

    .nav-icon {
        width: 25px;
        margin-right: 12px;
        font-size: 1.1rem;
        text-align: center;
        color: #CE93D8;
    }

    .nav-link:hover .nav-icon {
        color: white;
    }

    .nav-link.active .nav-icon {
        color: white;
    }

    .nav-text {
        font-size: 0.85rem;
        font-weight: 500;
        flex: 1;
    }

    .sidebar-footer {
        padding: 15px;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
    }

    .logout-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 12px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        text-decoration: none;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        border: none;
        width: 100%;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(156, 39, 176, 0.3);
    }

    .logout-btn:hover {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(156, 39, 176, 0.4);
    }

    /* Mobile Toggle Button */
    .sidebar-toggle {
        position: fixed;
        top: 20px;
        left: 20px;
        width: 45px;
        height: 45px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1001;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(156, 39, 176, 0.3);
        transition: all 0.3s ease;
    }

    .sidebar-toggle:hover {
        transform: scale(1.1);
    }

    .sidebar-toggle i {
        font-size: 1.2rem;
    }

    /* Overlay */
    .sidebar-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        z-index: 999;
        backdrop-filter: blur(3px);
    }

    /* Responsive */
    @media (max-width: 992px) {
        .sidebar {
            transform: translateX(-100%);
        }

        .sidebar.active {
            transform: translateX(0);
            box-shadow: 5px 0 25px rgba(0, 0, 0, 0.3);
        }

        .sidebar-toggle {
            display: flex;
        }
    }

    @media (min-width: 993px) {
        .sidebar-toggle {
            display: none;
        }
    }

    /* Badge for Notifications */
    .nav-badge {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        font-size: 0.7rem;
        padding: 2px 8px;
        border-radius: 10px;
        margin-left: auto;
        font-weight: 600;
        box-shadow: 0 2px 6px rgba(255, 64, 129, 0.3);
    }

    /*pop up konfirmasi logout*/
    .confirm-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        align-items: center;
        justify-content: center;
        z-index: 9999;
    }

    .confirm-content {
        background: white;
        padding: 25px;
        border-radius: 12px;
        text-align: center;
        max-width: 300px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        animation: popup 0.3s ease;
    }

    @keyframes popup {
        from {
            transform: scale(0.8);
            opacity: 0;
        }

        to {
            transform: scale(1);
            opacity: 1;
        }
    }

    .confirm-icon {
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        font-weight: bold;
        margin: 0 auto 15px;
    }

    .confirm-content h3 {
        margin: 0 0 10px;
        color: #333;
    }

    .confirm-content p {
        color: #666;
        margin-bottom: 20px;
    }

    .confirm-buttons {
        display: flex;
        gap: 10px;
        justify-content: center;
    }

    .confirm-buttons button {
        padding: 10px 25px;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        min-width: 80px;
    }

    .btn-cancel {
        background: #f0f0f0;
        color: #666;
    }

    .btn-ok {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
</style>

<!-- Toggle Button -->
<button class="sidebar-toggle" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<!-- Overlay -->
<div class="sidebar-overlay" onclick="toggleSidebar()" id="sidebarOverlay"></div>

<!-- Sidebar -->
<div class="sidebar" id="mainSidebar">
    <div class="sidebar-header">
        <div class="logo">
            <div class="logo-icon"><i class="fas fa-car"></i></div>
            <div class="logo-text">
                <h4>SI PINJAM MODIS</h4>
                <p>Diskominfo Kota Banjarbaru</p>
            </div>
        </div>
    </div>

    <div class="user-info">
        <div class="user-avatar"><i class="fas fa-user"></i></div>
        <div class="user-details">
            <div class="user-name"><?= htmlspecialchars($user_name) ?></div>
            <span class="user-role"><?= strtoupper($role) ?></span>
        </div>
    </div>

    <nav class="nav-container">
        <?php if ($role === 'admin'): ?>
            <div class="nav-group">
                <div class="nav-group-title">Admin Menu</div>
                <a href="admin-dashboard.php" class="nav-link <?= $current_page == 'admin-dashboard.php' ? 'active' : '' ?>">
                    <i class="fas fa-tachometer-alt nav-icon"></i>
                    <span class="nav-text">Dashboard Admin</span>
                </a>
                <a href="kelola-mobil.php" class="nav-link <?= $current_page == 'kelola-mobil.php' ? 'active' : '' ?>">
                    <i class="fas fa-car nav-icon"></i>
                    <span class="nav-text">Kelola Mobil</span>
                </a>
                <a href="kelola-pegawai.php" class="nav-link <?= $current_page == 'kelola-pegawai.php' ? 'active' : '' ?>">
                    <i class="fas fa-users nav-icon"></i>
                    <span class="nav-text">Kelola Pegawai</span>
                </a>
                <a href="riwayat.php" class="nav-link <?= $current_page == 'riwayat.php' ? 'active' : '' ?>">
                    <i class="fas fa-history nav-icon"></i>
                    <span class="nav-text">Riwayat Semua</span>
                </a>
                <a href="laporan.php" class="nav-link <?= $current_page == 'laporan.php' ? 'active' : '' ?>">
                    <i class="fas fa-chart-bar nav-icon"></i>
                    <span class="nav-text">Laporan</span>
                </a>
            </div>
        <?php else: ?>
            <div class="nav-group">
                <div class="nav-group-title">Main Menu</div>
                <a href="dashboard.php" class="nav-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                    <i class="fas fa-tachometer-alt nav-icon"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
                <a href="mobil.php" class="nav-link <?= $current_page == 'mobil.php' ? 'active' : '' ?>">
                    <i class="fas fa-car-side nav-icon"></i>
                    <span class="nav-text">Status Mobil</span>
                </a>
                <a href="peminjaman.php" class="nav-link <?= $current_page == 'peminjaman.php' ? 'active' : '' ?>">
                    <i class="fas fa-edit nav-icon"></i>
                    <span class="nav-text">Ajukan Pinjam</span>
                </a>
            </div>

            <div class="nav-group">
                <div class="nav-group-title">Riwayat</div>
                <a href="struk.php" class="nav-link <?= $current_page == 'struk.php' ? 'active' : '' ?>">
                    <i class="fas fa-receipt nav-icon"></i>
                    <span class="nav-text">Struk Saya</span>
                </a>
                <a href="riwayat_pegawai.php" class="nav-link <?= $current_page == 'riwayat_pegawai.php' ? 'active' : '' ?>">
                    <i class="fas fa-list nav-icon"></i>
                    <span class="nav-text">Riwayat Saya</span>
                </a>
            </div>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <a href="#" class="logout-btn" onclick="showCustomConfirm(event)">
            <i class="fas fa-sign-out-alt"></i>
            <span>KELUAR</span>
        </a>
    </div>

    <div id="confirmModal" class="confirm-modal">
        <div class="confirm-content">
            <div class="confirm-icon">?</div>
            <h3>Konfirmasi</h3>
            <p>Yakin ingin keluar dari sistem?</p>
            <div class="confirm-buttons">
                <button onclick="closeConfirm()" class="btn-cancel">Batal</button>
                <button onclick="confirmLogout()" class="btn-ok">OK</button>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('mainSidebar');
        const overlay = document.getElementById('sidebarOverlay');

        sidebar.classList.toggle('active');

        if (sidebar.classList.contains('active')) {
            overlay.style.display = 'block';
            setTimeout(() => {
                overlay.style.opacity = '1';
            }, 10);
        } else {
            overlay.style.opacity = '0';
            setTimeout(() => {
                overlay.style.display = 'none';
            }, 300);
        }
    }

    // Close sidebar when clicking on overlay
    document.getElementById('sidebarOverlay').addEventListener('click', toggleSidebar);

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('mainSidebar');
        const toggleBtn = document.querySelector('.sidebar-toggle');

        if (window.innerWidth <= 992 &&
            !sidebar.contains(event.target) &&
            !toggleBtn.contains(event.target) &&
            sidebar.classList.contains('active')) {
            toggleSidebar();
        }
    });

    // Close sidebar when pressing Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const sidebar = document.getElementById('mainSidebar');
            if (sidebar.classList.contains('active')) {
                toggleSidebar();
            }
        }
    });

    // pop up konfir log out
    let logoutConfirmed = false;

    function showCustomConfirm(e) {
        e.preventDefault();
        document.getElementById('confirmModal').style.display = 'flex';
    }

    function closeConfirm() {
        document.getElementById('confirmModal').style.display = 'none';
    }

    function confirmLogout() {
        logoutConfirmed = true;
        window.location.href = '../auth/logout.php';
    }

    // Fallback untuk jika JS disabled
    document.querySelector('.logout-btn').addEventListener('click', function(e) {
        if (logoutConfirmed) return;
        e.preventDefault();
        showCustomConfirm(e);
    });
</script>