<?php
include 'config.php'; // Koneksi ke database
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Resto-Rika</title>
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

    <!-- Template Stylesheet edit -->
    <link href="css/style1.css" rel="stylesheet">


</head>

<body>
    <div class="container-xxl bg-white p-0">
        <!-- Navbar & Hero Start -->
        <div class="container-xxl position-relative p-0">

            <nav class="navbar navbar-expand-lg navbar-dark bg-dark px-4 px-lg-5 py-3 py-lg-0">

                <img src="assets/menu/img/logo-rika-orange.svg" alt="Logo">

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
                    <span class="fa fa-bars"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarCollapse">
                    <div class="navbar-nav ms-auto py-0 pe-4">
                        <a href="index.php" class="nav-item nav-link active">Home</a>
                        <a href="about.php" class="nav-item nav-link">About</a>
                        <a href="pesanan.php" class="nav-item nav-link">Pesanan</a>
                        <a href="logout.php" class="nav-item nav-link">Logout</a>
                    </div>
            </nav>

            <div class="container-xxl py-5 bg-dark hero-header mb-5">
                <div class="container my-5 py-5">
                    <div class="row align-items-center g-5">
                        <div class="col-lg-6 text-center text-lg-start">
                            <h1 class="text-primary m-0 animated slideInRight">Selamat Datang<br><?= $nama_pelanggan; ?></h1>
                            <h1 class="display-3 text-white animated slideInLeft"><br>Citarasa Istimewa<br>dalam Setiap Sajian</h1>
                            <p class="text-white animated slideInLeft mb-4 pb-2">Di Resto-Rika, kami mengutamakan kualitas bahan dan cita rasa otentik. Setiap hidangan diracik dengan cinta dan keahlian untuk memberikan pengalaman bersantap yang tak terlupakan</p>
                        </div>
                        <div class="col-lg-6 text-center text-lg-end overflow-hidden">
                            <img class="img-fluid" src="assets/menu/img/hero.png" alt="">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Navbar & Hero End -->



        <!-- Menu Start -->
        <div class="container-xxl py-5">
            <div class="container">
                <div class="text-center wow fadeInUp" data-wow-delay="0.1s">
                    <h5 class="section-title ff-secondary text-center text-primary fw-normal">resto rahmat</h5>
                    <h1 class="mb-5">Katalog Menu - Meja <?= $meja; ?></h1>
                </div>

                <div class="tab-class text-center wow fadeInUp" data-wow-delay="0.1s">
                    <ul class="nav nav-pills d-inline-flex justify-content-center border-bottom mb-5">
                        <li class="nav-item">
                            <a class="d-flex align-items-center text-start mx-3 ms-0 pb-3 active" data-bs-toggle="pill" href="#tab-makanan">
                                <i class="fa fa-utensils fa-2x text-primary"></i>
                                <div class="ps-3">
                                    <h6 class="mt-n1 mb-0">Makanan</h6>
                                </div>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="d-flex align-items-center text-start mx-3 pb-3" data-bs-toggle="pill" href="#tab-minuman">
                                <i class="fa fa-coffee fa-2x text-primary"></i>
                                <div class="ps-3">
                                    <h6 class="mt-n1 mb-0">Minuman</h6>
                                </div>
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content">

                        <!-- Tab Makanan -->
                        <div id="tab-makanan" class="tab-pane fade show p-0 active">
                            <!-- Formulir Pencarian Makanan -->
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3">
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fa fa-search fa-1x"></i>
                                        </span>
                                        <input type="text" class="form-control" id="search-makanan-nama" placeholder="Cari nama makanan...">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fa fa-dollar-sign fa-1x"></i>
                                        </span>
                                        <input type="number" class="form-control" id="search-makanan-harga-max" placeholder="Harga Maksimum">
                                    </div>
                                </div>
                            </div>

                            <div class="row g-4" id="daftar-makanan">
                                <?php
                                // Simpan semua data makanan ke dalam array PHP untuk digunakan oleh JavaScript
                                $data_makanan = array();
                                mysqli_data_seek($result_minuman, 0); // Reset pointer result set
                                while ($menu = mysqli_fetch_assoc(result: $result_makanan)) {
                                    $data_makanan[] = $menu;
                                }
                                ?>
                            </div>
                        </div>

                        <!-- Tab Minuman -->
                        <div id="tab-minuman" class="tab-pane fade show p-0">
                            <!-- Formulir Pencarian Minuman -->
                            <div class="row mb-3">
                                <div class="col-md-6 mb-3">
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fa fa-search fa-1x"></i>
                                        </span>
                                        <input type="text" class="form-control" id="search-minuman-nama" placeholder="Cari nama minuman...">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-dollar-sign"></i>
                                        </span>
                                        <input type="number" class="form-control" id="search-minuman-harga-max" placeholder="Harga Maksimum">
                                    </div>
                                </div>
                            </div>
                            <div class="row g-4" id="daftar-minuman">
                                <?php
                                // Simpan semua data minuman ke dalam array PHP untuk digunakan oleh JavaScript
                                $data_minuman = array();
                                mysqli_data_seek($result_minuman, 0); // Reset pointer result set
                                while ($menu = mysqli_fetch_assoc($result_minuman)) {
                                    $data_minuman[] = $menu;
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Menu End -->

                <!-- Keranjang -->
                <div id="cart">
                    <i class="fa fa-shopping-cart"></i>
                    <span id="cart-count">0</span>
                </div>

                <!-- Modal Keranjang -->
                <div class="modal fade" id="cartModal" tabindex="-1" aria-labelledby="cartModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header modal-header-custom">
                                <h5 class="modal-title" id="cartModalLabel">
                                    Keranjang Belanja - Meja <?= $meja; ?>
                                    <div>
                                        Nama: <?= $nama_pelanggan; ?><br>
                                        No. HP: <?= $notelepon_pelanggan; ?>
                                    </div>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="cart-items-container">
                                    <div id="cart-items">
                                        <!-- Item keranjang akan ditampilkan di sini -->
                                    </div>
                                </div>
                                <div class="cart-total">
                                    <strong>Total:</strong> <span id="cart-total">Rp0</span>
                                </div>
                                <div class="form-group">
                                    <label for="metodePembayaran">Metode Pembayaran:</label>
                                    <select class="form-control" id="metodePembayaran">
                                        <option value="kasir">Bayar di Kasir</option>
                                        <option value="midtrans">Midtrans Payment Gateway</option>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                <button type="button" class="btn btn-primary" id="checkout-button">Pesan Sekarang</button>
                            </div>
                        </div>
                    </div>
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
            <script src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="SB-Mid-client-PpquWhRJmrYAXKQ8"></script>

            <!-- Template Javascript -->
            <script src="assets/menu/js/main.js"></script>

            <?php
            include 'script/script.php'
            ?>
            <?php
            // include 'script/pembayaran.php'
            ?>

</body>

</html>