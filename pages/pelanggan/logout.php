<?php
session_start(); // Mulai sesi

// Ambil nomor meja dari sesi
$meja = isset($_SESSION['meja']) ? $_SESSION['meja'] : 0;

// Hapus semua data sesi
session_unset();

// Hancurkan sesi
session_destroy();

// Redirect ke halaman login dengan membawa nomor meja
header("Location: index.php?meja=" . $meja);
exit;
?>