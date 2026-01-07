<?php
// Mulai sesi dan cek login sudah dilakukan oleh config.php
include 'config.php'; // Koneksi ke database, mulai sesi, dan cek login

// Karena config.php sudah memastikan user login, kita bisa langsung ambil ID
// dan yakin $_SESSION['id_pelanggan'] sudah terisi.
$loggedInUserId = $_SESSION['id_pelanggan'];

// --- Ambil data dari Sesi untuk membuat parameter URL ---
// Config.php Anda menyimpan ini setelah user login/identifikasi
$sess_meja = isset($_SESSION['meja']) ? $_SESSION['meja'] : '';
$sess_nama = isset($_SESSION['nama_pelanggan']) ? $_SESSION['nama_pelanggan'] : '';
$sess_notelepon = isset($_SESSION['notelepon_pelanggan']) ? $_SESSION['notelepon_pelanggan'] : '';

// Bangun string parameter URL
$params = [];
if ($sess_meja !== '') {
    $params[] = 'meja=' . urlencode($sess_meja); // Gunakan urlencode
}
if ($sess_nama !== '') {
    $params[] = 'nama=' . urlencode($sess_nama); // Gunakan urlencode
}
if ($sess_notelepon !== '') {
    $params[] = 'notelepon=' . urlencode($sess_notelepon); // Gunakan urlencode
}

$param_string = count($params) > 0 ? '?' . implode('&', $params) : '';

// Tidak ada query database yang diperlukan untuk halaman "About Us" yang statis ini.
// Logic fetching order dihapus.

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Resto-Rahmat - About Us</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">

    <link href="assets/menu/img/logo.png" rel="icon">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Heebo:wght@400;500;600&family=Nunito:wght@600;700;800&family=Pacifico&display=swap" rel="stylesheet">

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">

    <link href="assets/menu/lib/animate/animate.min.css" rel="stylesheet">
    <link href="assets/menu/lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">
    <link href="assets/menu/lib/tempusdominus/css/tempusdominus-bootstrap-4.min.css" rel="stylesheet" />

    <link href="assets/menu/css/bootstrap.min.css" rel="stylesheet">

    <link href="assets/menu/css/style.css" rel="stylesheet">

    <link href="css/style1.css" rel="stylesheet">

    <style>
        /* Styles from original template */
        .page-header {
            padding: 9rem 0 4rem 0;
            background: linear-gradient(rgba(0, 0, 0, .5), rgba(0, 0, 0, .5)), url(assets/menu/img/bg-hero.jpg) no-repeat center center;
            background-size: cover;
        }
        .page-header .breadcrumb-item + .breadcrumb-item::before {
            color: #cccccc;
        }

        /* Custom styles for About Us content (Filosofi Kami section) */
        .about-content {
            padding: 60px 0;
            background-color: #fef8f5;
            margin-bottom: 50px;
        }
        .about-content h2 {
            color: #ff6700;
            margin-bottom: 20px;
        }
        .about-content p {
            line-height: 1.8;
            margin-bottom: 15px;
            color: #555;
        }
        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 8px;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
        }
        .feature-item:hover {
            transform: translateY(-5px);
        }
        .feature-item i {
            font-size: 36px;
            color: #ff6700;
            margin-right: 20px;
        }
        .feature-item h5 {
            margin-bottom: 5px;
            color: #333;
        }

        /* NEW CSS for About Us section with Image */
        .about-section-with-image {
            background-color: #f8f9fa;
            padding: 80px 0;
            margin-bottom: 50px;
            border-radius: 10px;
            overflow: hidden;
        }

        .about-section-with-image .text-content {
            padding: 20px 40px;
        }

        .about-section-with-image .text-content h2 {
            color: #dc3545;
            font-size: 2.8rem;
            margin-bottom: 25px;
            font-weight: 700;
        }

        .about-section-with-image .text-content p {
            font-size: 1.15rem;
            line-height: 1.8;
            color: #444;
            margin-bottom: 20px;
        }

        .about-section-with-image .img-col { /* Class for the column containing image and social icons */
            display: flex;
            flex-direction: column; /* Stack image and social icons vertically */
            align-items: center; /* Center horizontally */
            justify-content: center; /* Center vertically */
            padding: 20px; /* Padding around the content in this column */
        }

        .about-section-with-image .img-container {
            text-align: center;
            margin-bottom: 30px; /* Jarak antara gambar dan ikon media sosial */
        }

        .about-section-with-image .img-container img {
            max-width: 100%;
            height: auto;
            border-radius: 12px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
            transition: transform 0.3s ease;
        }
        .about-section-with-image .img-container img:hover {
            transform: scale(1.02);
        }

        /* Social Media Icons specifically under the image */
        .about-section-with-image .social-icons-under-image {
            display: flex;
            justify-content: center; /* Rata tengah ikon */
            gap: 20px; /* Jarak antar ikon */
            margin-top: 20px; /* Jarak dari gambar di atasnya */
        }

        .about-section-with-image .social-icons-under-image a {
            display: inline-flex; /* Use flexbox for centering icon */
            align-items: center;
            justify-content: center;
            width: 55px; /* Ukuran lingkaran */
            height: 55px;
            border-radius: 50%;
            background-color: #7242F5; /* Warna ungu seperti contoh gambar */
            color: #fff;
            font-size: 26px; /* Ukuran ikon */
            transition: background-color 0.3s ease, transform 0.3s ease;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1); /* Bayangan untuk ikon */
        }
        .about-section-with-image .social-icons-under-image a:hover {
            background-color: #5a2ab3; /* Ungu lebih gelap saat hover */
            transform: translateY(-3px);
        }

        /* Responsive adjustments */
        @media (max-width: 991.98px) {
            .about-section-with-image .text-content {
                padding: 30px 20px;
                text-align: center;
            }
            .about-section-with-image .img-col {
                padding: 20px 0;
            }
            .about-section-with-image .text-content .btn {
                width: 100%;
            }
            .about-section-with-image .social-icons-under-image {
                flex-wrap: wrap; /* Izinkan wrap pada layar kecil */
                gap: 15px; /* Sesuaikan jarak */
            }
        }
    </style>

</head>

<body>
    <div class="container-xxl bg-white p-0">
        <?php // include 'spinner.php'; ?>
        <div class="container-xxl position-relative p-0">

            <nav class="navbar navbar-expand-lg navbar-dark bg-dark px-4 px-lg-5 py-3 py-lg-0">
                <a href="index.php<?php echo $param_string; ?>" class="navbar-brand p-0">
                    <img src="assets/menu/img/logo-rika-orange.svg" alt="Logo">
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
                    <span class="fa fa-bars"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarCollapse">
                    <div class="navbar-nav ms-auto py-0 pe-4">
                        <a href="index.php<?php echo $param_string; ?>" class="nav-item nav-link">Menu</a>
                        <a href="about.php<?php echo $param_string; ?>" class="nav-item nav-link active">Tentang</a>
                        <a href="pesanan.php<?php echo $param_string; ?>" class="nav-item nav-link">Pesanan</a>
                        <a href="logout.php" class="nav-item nav-link">Logout</a>
                    </div>
                </div>
            </nav>
            <div class="container-xxl py-5 bg-dark hero-header mb-5 page-header">
                <div class="container text-center my-5 pt-4 pb-4">
                    <h1 class="display-4 text-white mb-3 animated slideInDown">Tentang Kami</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center text-uppercase">
                            <li class="breadcrumb-item"><a href="index.php<?php echo $param_string; ?>" class="text-primary">Home</a></li>
                            <li class="breadcrumb-item text-white active" aria-current="page">Tentang Kami</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="container-xxl py-5">
                <div class="container about-section-with-image">
                    <div class="row g-5 align-items-center">
                        <div class="col-lg-6 wow fadeIn" data-wow-delay="0.1s">
                            <div class="text-content">
                                <h2>Resto-Rahmat: Cita Rasa, Kenyamanan, Kenangan.</h2>
                                <p>
                                    Didirikan pada tahun 2023 di jantung kota Makassar, Resto-Rahmat bukan sekadar tempat makan, melainkan sebuah destinasi kuliner. Kami berkomitmen menyajikan pengalaman bersantap yang tak terlupakan melalui hidangan lezat dan suasana yang hangat.
                                </p>
                                <p>
                                    Setiap sajian kami dibuat dengan teliti oleh tim koki profesional, menggunakan bahan-bahan segar pilihan dari pemasok lokal terpercaya. Kami percaya bahwa kualitas bahan adalah kunci utama kelezatan, dan setiap hidangan adalah karya seni yang kami banggakan.
                                </p>
                                <p>
                                    Datang dan rasakan perpaduan sempurna antara tradisi dan inovasi dalam setiap gigitan. Kami nantikan kedatangan Anda untuk menciptakan kenangan kuliner bersama!
                                </p>
                                <a href="contact.php<?php echo $param_string; ?>" class="btn btn-primary py-3 px-5 mt-3" style="background-color: #dc3545; border-color: #dc3545; font-size: 1.1rem; border-radius: 5px;">HUBUNGI KAMI</a>
                            </div>
                        </div>
                        <div class="col-lg-6 wow fadeIn" data-wow-delay="0.5s">
                            <div class="img-col">
                                <div class="img-container">
                                    <img class="img-fluid" src="assets/menu/img/resto.png" alt="Tentang Resto-Rahmat">
                                </div>
                                <div class="social-icons-under-image">
                                    <a href="https://www.instagram.com/rhmt.matt/" target="_blank" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                                    <a href="https://wa.me/+6285213976352" target="_blank" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="container mt-4 about-content">
                <div class="row justify-content-center">
                    <div class="col-lg-8 text-center wow fadeInUp" data-wow-delay="0.1s">
                        <h2 class="section-title ff-secondary text-center text-primary fw-normal">Filosofi Kami</h2>
                        <p class="mb-4">
                            Di Resto-Rahmat, kami percaya bahwa pengalaman bersantap adalah lebih dari sekadar makanan. Ini tentang menciptakan momen kebahagiaan, kebersamaan, dan kepuasan yang mendalam. Filosofi kami berakar pada tiga pilar utama:
                        </p>
                    </div>
                </div>
                <div class="row g-4 mt-4"> <div class="col-lg-4 col-md-6 wow fadeInUp" data-wow-delay="0.2s">
                        <div class="feature-item">
                            <i class="fa fa-utensils"></i>
                            <div>
                                <h5>Koki Berpengalaman</h5>
                                <p class="mb-0">Tim koki kami adalah seniman kuliner dengan keahlian yang terbukti, siap menghadirkan cita rasa yang tak terlupakan.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6 wow fadeInUp" data-wow-delay="0.4s">
                        <div class="feature-item">
                            <i class="fa fa-leaf"></i>
                            <div>
                                <h5>Bahan Baku Segar</h5>
                                <p class="mb-0">Kami berkomitmen pada kualitas, menggunakan hanya bahan-bahan segar pilihan untuk setiap hidangan yang kami sajikan.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6 wow fadeInUp" data-wow-delay="0.6s">
                        <div class="feature-item">
                            <i class="fa fa-mug-hot"></i>
                            <div>
                                <h5>Suasana Nyaman</h5>
                                <p class="mb-0">Nikmati hidangan Anda dalam lingkungan yang hangat, santai, dan mengundang, sempurna untuk segala momen.</p>
                            </div>
                        </div>
                    </div>
                    </div>
            </div>
            <?php // include 'footer.php'; ?>
            </div>

    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/menu/lib/wow/wow.min.js"></script>
    <script src="assets/menu/lib/easing/easing.min.js"></script>
    <script src="assets/menu/lib/waypoints/waypoints.min.js"></script>
    <script src="assets/menu/lib/counterup/counterup.min.js"></script>
    <script src="assets/menu/lib/owlcarousel/owl.carousel.min.js"></script>
    <script src="assets/menu/lib/tempusdominus/js/moment.min.js"></script>
    <script src="assets/menu/lib/tempusdominus/js/tempusdominus-bootstrap-4.min.js"></script>
    <script src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="SB-Mid-client-PpquWhRJmrYAXKQ8"></script>

    <script src="assets/menu/js/main.js"></script>

    <?php
    // include 'script/script.php'; // Sertakan script kustom Anda jika diperlukan
    ?>

</body>

</html>

<?php
// Penutupan koneksi opsional tergantung config.php
// mysqli_close($con);
?>