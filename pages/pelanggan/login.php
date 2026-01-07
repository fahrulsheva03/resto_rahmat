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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nama = isset($_POST['nama']) ? htmlspecialchars($_POST['nama']) : '';
    $notelepon = isset($_POST['notelepon']) ? htmlspecialchars($_POST['notelepon']) : '';
    $meja = isset($_POST['meja']) ? intval($_POST['meja']) : 0;

    if (!empty($nama) && !empty($notelepon)) {
        $insert_query = "INSERT INTO pelanggan (nama, notelepon, waktu_masuk) VALUES ('$nama', '$notelepon', NOW())";
        if (mysqli_query($con, $insert_query)) {
            $id_pelanggan = mysqli_insert_id($con);
            $_SESSION['id_pelanggan'] = $id_pelanggan;
            header("Location: index.php?meja=" . $meja . "&nama=" . urlencode($nama) . "&notelepon=" . urlencode($notelepon));
            exit;
        } else {
            echo "Error: " . $insert_query . "<br>" . mysqli_error($con);
        }
    } else {
        echo "Nama dan Nomor Telepon harus diisi.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Resto-Rahmat - Login</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">

    <!-- Favicon -->
    <link href="assets/menu/img/logo.png" rel="icon">

    <!-- Google Web Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Heebo:wght@400;500;600&family=Nunito:wght@600;700;800&family=Pacifico&display=swap" rel="stylesheet">

    <!-- Icon Font Stylesheet -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Libraries Stylesheet -->
    <link href="assets/menu/lib/animate/animate.min.css" rel="stylesheet">
    <link href="assets/menu/lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">
    <link href="assets/menu/lib/tempusdominus/css/tempusdominus-bootstrap-4.min.css" rel="stylesheet" />

    <!-- Customized Bootstrap Stylesheet -->
    <link href="assets/menu/css/bootstrap.min.css" rel="stylesheet">

    <!-- Template Stylesheet -->
    <link href="assets/menu/css/style.css" rel="stylesheet">

    <style>
        body {
            background: url('../../images/bg2.jpg') center/cover no-repeat fixed;
            height: 100vh;
            margin: 0;
            overflow: hidden;
            /* Hide scrollbars */
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            padding: 20px;
        }

        .login-form {
            background-color: rgba(255, 255, 255, 0.9);
            /* Semi-transparent white */
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            width: 400px;
            max-width: 100%;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 600;
        }

        .form-control {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 16px;
            width: 100%;
            box-sizing: border-box;
            transition: border-color 0.2s;
            background-color: rgba(255, 255, 255, 0.8);
            /* Semi-transparent input */
        }

        .form-control:focus {
            border-color: #a8dadc;
            outline: none;
            box-shadow: none;
        }

        .btn-login {
            background-color: #FEA116;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 12px 20px;
            font-size: 17px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .btn-login:hover {
            background-color: #FEA116;
        }
        .section-title{
            color:#a5b611;
        }

    </style>
</head>

<body>
     <!-- Spinner Start -->
     <div id="spinner" class="show bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center">
        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
            <span class="sr-only">Loading...</span>
        </div>
    </div>
    <!-- Spinner End -->

    <div class="container login-container">
        <div class="login-form">
            <div class="text-center wow fadeInUp" data-wow-delay="0.1s">
                <h5 class="section-title ff-secondary text-center text-primary fw-normal">Resto Rahmat</h5>
            </div>
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" autocomplete="off">
                <div class="form-group">
                    <label for="nama">Nama:</label>
                    <input type="text" class="form-control" id="nama" name="nama" required autocomplete="off" placeholder="Nama Anda">
                </div>
                <div class="form-group">
                    <label for="notelepon">Nomor Telepon:</label>
                    <input type="text" class="form-control" id="notelepon" name="notelepon" required autocomplete="off" placeholder="Contoh: 081234567890" maxlength="12" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                </div>
                <input type="hidden" name="meja" value="<?php echo isset($_GET['meja']) ? intval($_GET['meja']) : 0; ?>">
                <button type="submit" class="btn btn-login w-100">Login</button>
            </form>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/menu/lib/wow/wow.min.js"></script>
    <script src="assets/menu/lib/easing/easing.min.js"></script>
    <script src="assets/menu/lib/waypoints/waypoints.min.js"></script>
    <script src="assets/menu/lib/counterup/counterup.min.js"></script>
    <script src="assets/menu/lib/owlcarousel/owl.carousel.min.js"></script>
    <script src="assets/menu/lib/tempusdominus/js/moment.min.js"></script>
    <script src="assets/menu/lib/tempusdominus/js/tempusdominus-bootstrap-4.min.js"></script>

    <!-- Template Javascript -->
    <script src="assets/menu/js/main.js"></script>
</body>

</html>