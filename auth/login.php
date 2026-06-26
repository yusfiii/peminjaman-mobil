<?php
ob_start();
session_start();
include "../config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $nip = mysqli_real_escape_string($conn, $_POST['nip']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);

    // Ambil user berdasarkan NIP
    $query = "SELECT * FROM users WHERE nip='$nip' LIMIT 1";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) === 1) {
        $row = mysqli_fetch_assoc($result);

        // Password plain (tanpa hash)
        if ($password === $row['password']) {

            $_SESSION['user'] = $row;
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['nama'] = $row['nama'];
            $_SESSION['role'] = $row['role'];

            if ($row['role'] == "admin") {
                header("Location: ../admin/admin-dashboard.php");
                exit;
            } elseif ($row['role'] == "pegawai") {
                header("Location: ../pegawai/dashboard.php");
                exit;
            }
        } else {
            $error = "Password salah!";
        }
    } else {
        $error = "NIP tidak ditemukan!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Peminjaman Mobil Dinas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            width: 100%;
            max-width: 450px;
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
            color: white;
        }

        .login-header h1 {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .login-header p {
            font-size: 1rem;
            opacity: 0.9;
            font-weight: 300;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px 35px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: transform 0.3s ease;
        }

        .login-card:hover {
            transform: translateY(-5px);
        }

        .logo-container {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .logo i {
            font-size: 2.5rem;
            color: white;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2d3748;
            font-size: 0.95rem;
        }

        .input-group {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #667eea;
            font-size: 1.1rem;
        }

        .form-control {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: #f8fafc;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            background-color: white;
        }

        .form-control::placeholder {
            color: #a0aec0;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #a0aec0;
            cursor: pointer;
            font-size: 1.1rem;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: #667eea;
        }

        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.5);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .alert-custom {
            border-radius: 12px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 25px;
            animation: slideIn 0.5s ease-out;
            display: flex;
            align-items: center;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .alert-custom i {
            margin-right: 10px;
            font-size: 1.2rem;
        }

        .alert-danger {
            background-color: #fee2e2;
            color: #dc2626;
            border-left: 4px solid #dc2626;
        }

        .alert-success {
            background-color: #d1fae5;
            color: #059669;
            border-left: 4px solid #059669;
        }

        .login-footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            color: #718096;
            font-size: 0.9rem;
        }

        .login-footer a {
            color: #667eea;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .login-footer a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .system-info {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border-radius: 12px;
            padding: 15px;
            margin-top: 25px;
            text-align: center;
            border: 1px solid rgba(102, 126, 234, 0.2);
        }

        .system-info p {
            margin: 0;
            color: #4a5568;
            font-size: 0.9rem;
        }

        .system-info i {
            color: #667eea;
            margin-right: 8px;
        }

        /* Responsive Design */
        @media (max-width: 576px) {
            .login-card {
                padding: 30px 25px;
            }

            .login-header h1 {
                font-size: 1.8rem;
            }

            .logo {
                width: 70px;
                height: 70px;
            }

            .logo i {
                font-size: 2rem;
            }
        }

        @media (max-width: 400px) {
            .login-card {
                padding: 25px 20px;
            }

            .login-header h1 {
                font-size: 1.5rem;
            }
        }

        /* Loading animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, .3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>
    <div class="login-container">
        <!-- Header -->
        <div class="login-header">
            <h1>Sistem Peminjaman Mobil Dinas</h1>
            <p>Dinas Komunikasi dan Informatika</p>
        </div>

        <!-- Login Card -->
        <div class="login-card">
            <!-- Logo -->
            <div class="logo-container">
                <div class="logo">
                    <i class="fas fa-car"></i>
                </div>
                <h3 class="text-center mb-0">Masuk ke Sistem</h3>
                <p class="text-muted text-center mt-2">Silakan login dengan akun Anda</p>
            </div>

            <!-- Error Message -->
            <?php if ($error != ""): ?>
                <div class="alert-custom alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error); ?></span>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Login Form -->
            <form method="POST" id="loginForm">
                <!-- NIP Field -->
                <div class="form-group">
                    <label class="form-label">Nomor Induk Pegawai (NIP)</label>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text"
                            name="nip"
                            class="form-control"
                            placeholder="Masukkan NIP Anda"
                            required
                            autocomplete="username"
                            autofocus>
                    </div>
                </div>

                <!-- Password Field -->
                <div class="form-group">
                    <div class="d-flex justify-content-between align-items-center">
                        <label class="form-label">Password</label>
                        <!-- <a href="forgot-password.php" class="text-decoration-none" style="font-size: 0.9rem; color: #667eea;">
                            Lupa Password?
                        </a> -->
                    </div>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password"
                            name="password"
                            id="password"
                            class="form-control"
                            placeholder="Masukkan password Anda"
                            required
                            autocomplete="current-password">
                        <button type="button" class="password-toggle" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn-login" id="loginButton">
                    <span id="buttonText">Masuk</span>
                    <span id="buttonLoading" style="display: none;">
                        <div class="loading"></div>
                    </span>
                </button>
            </form>

            <!-- System Info -->
            <div class="system-info">
                <p>
                    <i class="fas fa-info-circle"></i>
                    Gunakan NIP dan password yang telah diberikan oleh admin
                </p>
            </div>

            <!-- Footer -->
            <div class="login-footer">
                <p>
                    &copy; <?= date("Y"); ?> Dinas Komunikasi dan Informatika
                    <br>
                    <small>Versi 1.0 - Sistem Peminjaman Mobil Dinas</small>
                </p>
                <!--
                <p class="mt-2">
                    Belum punya akun? 
                    <a href="register.php">Hubungi Admin</a>
                </p>
                -->
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const passwordIcon = togglePassword.querySelector('i');

        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);

            // Toggle icon
            if (type === 'password') {
                passwordIcon.classList.remove('fa-eye-slash');
                passwordIcon.classList.add('fa-eye');
            } else {
                passwordIcon.classList.remove('fa-eye');
                passwordIcon.classList.add('fa-eye-slash');
            }
        });

        // Form submission with loading state
        const loginForm = document.getElementById('loginForm');
        const loginButton = document.getElementById('loginButton');
        const buttonText = document.getElementById('buttonText');
        const buttonLoading = document.getElementById('buttonLoading');

        loginForm.addEventListener('submit', function(e) {
            // Basic validation
            const nip = document.querySelector('input[name="nip"]').value.trim();
            const password = document.querySelector('input[name="password"]').value.trim();

            if (!nip || !password) {
                e.preventDefault();
                return;
            }

            // Show loading state
            buttonText.style.display = 'none';
            buttonLoading.style.display = 'inline-block';
            loginButton.disabled = true;

            // Add loading text
            loginButton.innerHTML = '<span class="loading"></span> Memproses...';

            // Prevent double submission
            loginButton.disabled = true;
        });

        // Input validation on blur
        const inputs = document.querySelectorAll('input[required]');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                if (this.value.trim() === '') {
                    this.style.borderColor = '#e53e3e';
                } else {
                    this.style.borderColor = '#e2e8f0';
                }
            });

            input.addEventListener('input', function() {
                if (this.value.trim() !== '') {
                    this.style.borderColor = '#e2e8f0';
                }
            });
        });

        // Auto focus on NIP field
        document.querySelector('input[name="nip"]').focus();

        // Enter key to submit form
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !loginButton.disabled) {
                const activeElement = document.activeElement;
                if (activeElement.tagName === 'INPUT') {
                    loginForm.requestSubmit();
                }
            }
        });

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>

</html>