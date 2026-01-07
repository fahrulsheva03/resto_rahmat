<?php
session_start();

// Koneksi ke Database
$host = "localhost";
$username = "root";
$password = "";
$database = "restoran";

$koneksi = mysqli_connect($host, $username, $password, $database);

if (mysqli_connect_errno()) {
    echo "Koneksi database gagal: " . mysqli_connect_error();
    exit();
}

// Proses Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php"); // Atau halaman login utama jika bukan index
    exit();
}

// Proses Login
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = mysqli_real_escape_string($koneksi, $_POST['username']);
    // *KEAMANAN: Password saat ini tidak di-hash. Ini sangat tidak aman!
    // Anda HARUS menggunakan password hashing (misalnya bcrypt) di database
    // dan memverifikasi hash di sini, BUKAN membandingkan plain text.
    $password = mysqli_real_escape_string($koneksi, $_POST['password']); // Gunakan password hash dari database

    // Query untuk mengambil data pengguna, termasuk 'role'
    // *KEAMANAN: Query ini rentan terhadap SQL Injection jika password tidak di-hash.
    // Bahkan dengan mysqli_real_escape_string, membandingkan plain text password di DB tidak aman.
    // Jika password di-hash di DB, query akan mengambil hash password, lalu
    // menggunakan password_verify($password_plain, $hash_dari_db).
    $query = "SELECT id, username, role FROM users WHERE username='$username' AND password='$password'"; // Jika password di-hash, query ambil hash password juga
    $result = mysqli_query($koneksi, $query);

    if (mysqli_num_rows($result) == 1) {
        $row = mysqli_fetch_assoc($result);

        // Jika password di-hash:
        // $hashed_password_from_db = $row['password']; // Ambil kolom password hash dari row
        // if (password_verify($password_plain, $hashed_password_from_db)) {
        // Lanjutkan proses login...

        $_SESSION['id'] = $row['id'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['role'] = $row['role'];

        // Redirect berdasarkan peran
        $role = $_SESSION['role'];
        switch ($role) {
            case 'admin':
                header("Location: ../dashboard/"); // Periksa path ini relative terhadap lokasi file index.php ini
                break;
            case 'pegawai':
                header("Location: pegawai/dashboard.php"); // Periksa path ini
                break;
            case 'kasir':
                header("Location: kasir/dashboard.php"); // Periksa path ini
                break;
            case 'chef':
                header("Location: ../../dapur/index.php"); // Periksa path ini
                break;
            default:
                // Redirect default jika peran tidak dikenali atau password hash salah
                header("Location: index.php");
                $error_message = "Role pengguna tidak valid."; // Atau pesan error password hash
        }
        exit();

        // Jika password di-hash dan password_verify gagal:
        // } else {
        //    $error_message = "Username atau password salah.";
        // }

    } else {
        $error_message = "Username atau password salah.";
    }
}

// Tampilan
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <style>
        /* CSS untuk Body dan Container */
        body {
            font-family: Arial, sans-serif;
            /*background: #f9f9f9; /* Hapus atau komentari warna background solid */
            
            /* --- Tambahkan CSS untuk Background Gambar --- */
            background-image: url('../assets/images/bg.jpg'); /* <--- GANTI DENGAN PATH GAMBAR ANDA */
            background-size: cover; /* Skalakan gambar agar menutupi seluruh area body tanpa distorsi */
            background-position: center center; /* Pusatkan gambar */
            background-repeat: no-repeat; /* Jangan ulangi gambar */
            background-attachment: fixed; /* Gambar tetap di tempat saat scrolling (opsional, tapi bagus untuk full-page background) */
            background-color: #cccccc; /* Warna fallback jika gambar gagal dimuat */
            /* ------------------------------------------ */

            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }

        .login-container {
            background-color: rgba(255, 255, 255, 0.9);
            /* Semi-transparent white */
            border-radius: 10px;
            box-shadow: 0px 0px 15px rgba(0, 0, 0, 0.1);
            padding: 30px;
            width: 350px;
            text-align: center;
            /* Tambahkan sedikit background transparan jika diinginkan */
            /* background-color: rgba(255, 255, 255, 0.9); */
        }

        h2 {
            color: #0033c4;
            margin-bottom: 20px;
        }

        /* CSS Tambahan untuk Avatar (Gambar SVG) */
        .avatar {
            margin-bottom: 20px; /* Memberi jarak di bawah gambar */
        }

        .avatar img {
            max-width: 200px; /* Atur lebar maksimum gambar */
            height: auto;      /* Tinggi otomatis untuk menjaga aspek rasio */
            display: block;    /* Membuat gambar menjadi blok level element */
            margin: 0 auto;    /* Pusatkan gambar secara horizontal */
        }

        /* CSS untuk Input Group dan Input Fields */
        .input-group {
            margin-bottom: 20px;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            /* margin-bottom: 10px; /* Dihapus karena sudah ada di .input-group */
            box-sizing: border-box; /* Penting agar padding termasuk dalam lebar total */
        }

        /* CSS untuk Remember Me & Forgot Password (opsional) */
        /* Jika tidak digunakan, bagian ini bisa dihapus */
        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            font-size: 0.9em; /* Ukuran teks lebih kecil */
            color: #555;
        }

        .remember-me input[type="checkbox"] {
            margin-right: 5px;
        }

        a {
            color: #0033c4;
            text-decoration: none;
            font-size: 0.9em; /* Ukuran teks lebih kecil */
        }

        a:hover {
            text-decoration: underline;
        }

        /* CSS untuk Tombol Login */
        button {
            background-color: #0033c4;
            color: #fff;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
            transition: background-color 0.3s ease; /* Efek transisi saat hover */
        }

        button:hover {
            background-color: #002299; /* Sedikit lebih gelap saat hover */
        }

        /* CSS untuk Pesan Error */
         .error-message {
             color: red;
             margin-bottom: 15px;
             font-size: 0.9em;
         }

    </style>
</head>
<body>

    <!-- Form Login -->
    <div class="login-container">
        <div class="avatar">
            <!-- Pastikan path ini benar relatif terhadap lokasi file PHP ini -->
            <!-- Jika file login di root, dan assets di root, gunakan 'assets/...' -->
            <!-- Jika file login di root/folder/, dan assets di root, gunakan '../assets/...' -->
             <img src="../assets/images/logo-rahmat.svg" alt="Logo Restoran Rika">
        </div>

        <?php if (isset($error_message)): ?>
            <p class="error-message"><?php echo $error_message; ?></p>
        <?php endif; ?>

        <form method="post">
            <div class="input-group">
                <input type="text" name="username" placeholder="Username" required autocomplete="username">
            </div>
            <div class="input-group">
                <input type="password" name="password" placeholder="Password" required autocomplete="current-password">
            </div>

            <!-- Anda bisa tambahkan Remember Me atau Forgot Password di sini jika desain membutuhkannya -->
            <!--
            <div class="remember-forgot">
                <div class="remember-me">
                    <input type="checkbox" id="remember">
                    <label for="remember">Ingat Saya</label>
                </div>
                <a href="#">Lupa Password?</a>
            </div>
            -->

            <button type="submit">LOGIN</button>
        </form>
    </div>


</body>
</html>

<?php mysqli_close($koneksi); ?>