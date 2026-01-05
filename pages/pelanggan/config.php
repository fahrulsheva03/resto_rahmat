<?php
session_start(); // Mulai sesi

$host = "localhost";
$user = "root";
$pass = "";
$db = "restoran";

$con = mysqli_connect($host, $user, $pass, $db);
if (!$con) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// Ambil nomor meja dari URL
$meja = isset($_GET['meja']) ? intval($_GET['meja']) : 0;
$nama = isset($_GET['nama']) ? htmlspecialchars($_GET['nama']) : '';
$notelepon = isset($_GET['notelepon']) ? htmlspecialchars($_GET['notelepon']) : '';

// --- PERUBAHAN DIMULAI DI SINI ---

// Cek jika nomor meja tidak valid HANYA JIKA user BELUM login
// Jika user sudah login, diasumsikan nomor meja sudah disimpan di sesi saat pertama kali masuk
if ($meja == 0 && !isset($_SESSION['id_pelanggan'])) {
    echo "Nomor meja tidak valid";
    exit;
}

// Simpan nomor meja ke dalam sesi HANYA JIKA diambil dari URL dan > 0
// Ini mencegah sesi meja ditimpa dengan 0 jika param 'meja' tidak ada di URL
if ($meja > 0) {
   $_SESSION['meja'] = $meja;
}


// Periksa apakah user sudah login (kode ini tetap sama)
if (isset($_SESSION['id_pelanggan'])) {
    $id_pelanggan = $_SESSION['id_pelanggan']; // Ambil ID dari sesi

    // Ambil nama pengguna dari database
    $query_pelanggan = "SELECT nama, notelepon, waktu_masuk FROM pelanggan WHERE id_pelanggan = '$id_pelanggan'";
    $result_pelanggan = mysqli_query($con, $query_pelanggan);

    if ($result_pelanggan && mysqli_num_rows($result_pelanggan) > 0) {
        $row_pelanggan = mysqli_fetch_assoc($result_pelanggan);
        $nama_pelanggan = $row_pelanggan['nama'];
        $notelepon_pelanggan = $row_pelanggan['notelepon'];
        $waktu_masuk_pelanggan = $row_pelanggan['waktu_masuk'];
         // Simpan nama dan notelepon ke dalam sesi (jika belum ada)
        if (!isset($_SESSION['nama_pelanggan'])) {
            $_SESSION['nama_pelanggan'] = $nama_pelanggan;
        }
        if (!isset($_SESSION['notelepon_pelanggan'])) {
            $_SESSION['notelepon_pelanggan'] = $notelepon_pelanggan;
        }
    } else {
        // Handle jika ID pelanggan di sesi tidak ditemukan di database (kasus jarang terjadi)
        // Mungkin perlu clear sesi dan redirect ke login
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit;
    }
} else {
    // Jika belum login, redirect ke halaman login.
    // $meja di sini bisa 0 jika tidak ada di URL.
    // login.php harus bisa menangani kasus ini (misalnya, minta user scan QR lagi atau pilih meja)
    header("Location: login.php?meja=" . $meja);
    exit;
}

// --- PERUBAHAN SELESAI DI SINI ---

// Ambil daftar makanan dan minuman dari database (kode ini tetap sama)
$query_makanan = "SELECT * FROM menu WHERE kategori = 'makanan'";
$query_minuman = "SELECT * FROM menu WHERE kategori = 'minuman'";

$result_makanan = mysqli_query($con, $query_makanan);
$result_minuman = mysqli_query($con, $query_minuman);

// ... sisa kode config.php jika ada ...

?>