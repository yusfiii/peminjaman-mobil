<?php
session_start();
require "../config/database.php";

$id = $_GET['id'];

mysqli_query($conn, "
    UPDATE notifikasi
    SET status='dibaca'
    WHERE id='$id'
");

header("Location: " . $_SERVER['HTTP_REFERER']);
