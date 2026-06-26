<?php
include "../config/database.php";

if (isset($_POST['register'])) {
    $nama = $_POST['nama'];
    $nip = $_POST['nip'];
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);

    mysqli_query($conn, "INSERT INTO users (nama,nip,password) VALUES ('$nama','$nip','$pass')");
    header("Location: login.php");
}
?>

<form method="POST">
    <h2>Register Pegawai</h2>
    Nama: <input type="text" name="nama"><br>
    NIP: <input type="text" name="nip"><br>
    Password: <input type="password" name="password"><br>
    <button type="submit" name="register">Daftar</button>
</form>