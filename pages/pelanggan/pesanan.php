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

// Buat string akhir: ?param1=value1¶m2=value2 atau string kosong jika tidak ada parameter
$param_string = count($params) > 0 ? '?' . implode('&', $params) : '';

// --- Database Queries ---

// 1. Get all orders for the logged-in user
$sql_pesanan = "SELECT * FROM pesanan WHERE id_pelanggan = ? ORDER BY waktu_pesan DESC";
$stmt_pesanan = mysqli_prepare($con, $sql_pesanan);

if ($stmt_pesanan) {
    mysqli_stmt_bind_param($stmt_pesanan, "i", $loggedInUserId);
    mysqli_stmt_execute($stmt_pesanan);
    $result_pesanan = mysqli_stmt_get_result($stmt_pesanan);

    $orders = [];
    if ($result_pesanan) {
        while ($row_pesanan = mysqli_fetch_assoc($result_pesanan)) {
            $orders[] = $row_pesanan;
        }
    } else {
        echo "Error fetching orders: " . mysqli_error($con);
    }
    mysqli_stmt_close($stmt_pesanan);
} else {
    echo "Error preparing order statement: " . mysqli_error($con);
}

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Resto-Rahmat - Pesanan Anda</title>
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

    <!-- Template Stylesheet edit (optional) -->
    <link href="css/style1.css" rel="stylesheet">

    <style>
        /* Custom styles */
        .order-card {
            border: 1px solid #e0e0e0;
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 8px;
            background-color: #fff; /* White background for cards */
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); /* Soft shadow */
        }
         .order-card .order-header h5 {
            margin-top: 0;
            margin-bottom: 10px;
            color: #ff6700; /* Your orange logo color */
        }
        .order-details {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed #ccc;
        }
        .order-details ul {
            list-style: none;
            padding: 0;
        }
        .order-details li {
            margin-bottom: 5px;
            font-size: 0.95em;
        }
        .total-price {
            font-weight: bold;
            color: #333;
            margin-top: 15px;
            font-size: 1.1em;
        }
        .status-message {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed #ccc;
            font-style: italic;
            color: #555;
        }
         .page-header {
            padding: 9rem 0 4rem 0;
            background: linear-gradient(rgba(0, 0, 0, .5), rgba(0, 0, 0, .5)), url(assets/menu/img/bg-hero.jpg) no-repeat center center;
            background-size: cover;
        }
         .page-header .breadcrumb-item + .breadcrumb-item::before {
            color: #cccccc;
        }
         /* Warna teks status yang berbeda */
         .status-pending { color: #ffc107; /* Yellow */ }
         .status-diproses { color: #28a745; /* Green */ }
         .status-selesai { color: #17a2b8; /* Cyan/Info */ }
         .status-dibatalkan { color: #dc3545; /* Red */ } /* Assuming 'dibatalkan' exists */
    </style>

</head>

<body>
    <div class="container-xxl bg-white p-0">
        <!-- Navbar & Hero Start -->
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
                        <!-- Tambahkan $param_string ke setiap link -->
                        <a href="index.php<?php echo $param_string; ?>" class="nav-item nav-link">Menu</a>
                        <a href="about.php<?php echo $param_string; ?>" class="nav-item nav-link">Tentang</a>
                        <a href="pesanan.php<?php echo $param_string; ?>" class="nav-item nav-link active">Pesanan</a>
                        <a href="logout.php" class="nav-item nav-link">Logout</a>
                    </div>
                </div>
            </nav>
            <!-- Navbar End -->

            <!-- Page Header Start -->
            <div class="container-xxl py-5 bg-dark hero-header mb-5 page-header">
                <div class="container text-center my-5 pt-4 pb-4">
                    <h1 class="display-4 text-white mb-3 animated slideInDown">Riwayat Pesanan Anda</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center text-uppercase">
                             <!-- Tambahkan $param_string ke link Home di breadcrumb -->
                            <li class="breadcrumb-item"><a href="index.php<?php echo $param_string; ?>" class="text-primary">Home</a></li>
                            <li class="breadcrumb-item text-white active" aria-current="page">Pesanan Anda</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <!-- Page Header End -->


            <!-- Order List Content Start -->
            <div class="container mt-4 mb-5">
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <?php if (empty($orders)): ?>
                            <div class="text-center wow fadeInUp" data-wow-delay="0.1s">
                                <p class="lead">Anda belum memiliki pesanan.</p>
                                <!-- Tambahkan $param_string ke link Pesan Sekarang -->
                                <a href="menu.html<?php echo $param_string; ?>" class="btn btn-primary py-2 px-4 mt-3">Pesan Sekarang</a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <div class="order-card wow fadeInUp" data-wow-delay="0.2s">
                                    <div class="order-header">
                                        <h5>Pesanan #<?php echo htmlspecialchars($order['id_pesanan']); ?></h5>
                                        <p>
                                            Tanggal & Waktu: <?php echo htmlspecialchars($order['waktu_pesan']); ?><br>
                                            Status: <?php echo htmlspecialchars($order['status']); ?><br>
                                            Metode Pembayaran: <?php echo htmlspecialchars($order['metode_pembayaran']); ?>
                                        </p>
                                    </div>

                                    <div class="order-details">
                                        <h6>Detail Items:</h6>
                                        <ul>
                                            <?php
                                            // 2. Get details for the current order
                                            $sql_detail = "SELECT dp.*, m.nama_menu FROM detail_pesanan dp JOIN menu m ON dp.id_menu = m.id_menu WHERE dp.id_pesanan = ?";
                                            $stmt_detail = mysqli_prepare($con, $sql_detail);

                                            if ($stmt_detail) {
                                                mysqli_stmt_bind_param($stmt_detail, "i", $order['id_pesanan']);
                                                mysqli_stmt_execute($stmt_detail);
                                                $result_detail = mysqli_stmt_get_result($stmt_detail);

                                                if ($result_detail) {
                                                    while ($row_detail = mysqli_fetch_assoc($result_detail)) {
                                                        ?>
                                                        <li>
                                                            <?php echo htmlspecialchars($row_detail['jumlah']); ?>x <?php echo htmlspecialchars($row_detail['nama_menu']); ?>
                                                            - Rp <?php echo number_format($row_detail['subtotal'], 0, ',', '.'); ?>
                                                        </li>
                                                        <?php
                                                    }
                                                } else {
                                                     echo "<li>Error fetching details: " . mysqli_error($con) . "</li>";
                                                }
                                                mysqli_stmt_close($stmt_detail);
                                            } else {
                                                 echo "<li>Error preparing detail statement: " . mysqli_error($con) . "</li>";
                                            }
                                            ?>
                                        </ul>
                                    </div>

                                    <div class="total-price">
                                        Total Harga: Rp <?php echo number_format($order['total_harga'], 0, ',', '.'); ?>
                                    </div>

                                     <!-- Add Status Message and Button Area -->
                                    <div class="status-message mt-2 pt-2">
                                        <?php
                                        $status = $order['status'];
                                        $method = $order['metode_pembayaran'];
                                        $message_text = '';
                                        $button_html = '';
                                        $message_class = ''; // Optional class for coloring message

                                        if ($status == 'pending' && $method == 'midtrans') {
                                            $message_text = 'Status: Menunggu Pembayaran. Mohon selesaikan pembayaran.';
                                            $button_html = '<button class="btn btn-sm btn-warning mt-2 bayar-midtrans" data-order-id="' . htmlspecialchars($order['id_pesanan']) . '" data-total-price="' . htmlspecialchars($order['total_harga']) . '">Bayar Sekarang (Midtrans)</button>';
                                            $message_class = 'status-pending';
                                        } elseif ($status == 'pending' && $method == 'kasir') {
                                             $message_text = 'Status: Menunggu Pembayaran di Kasir.';
                                             $button_html = '<button class="btn btn-sm btn-secondary mt-2 disabled">Bayar di Kasir</button>';
                                             $message_class = 'status-pending';
                                        } elseif ($status == 'diproses') {
                                            $message_text = 'Status: Diproses. Pembayaran berhasil dan pesanan sedang dimasak di dapur.';
                                            $button_html = '<button class="btn btn-sm btn-success mt-2 disabled">Pesanan Diproses</button>';
                                            $message_class = 'status-diproses';
                                        } elseif ($status == 'selesai') {
                                            $message_text = 'Status: Selesai. Pesanan telah disajikan atau akan segera diantar.';
                                            $button_html = '<button class="btn btn-sm btn-info mt-2 disabled">Pesanan Selesai</button>';
                                             $message_class = 'status-selesai';
                                        } elseif ($status == 'dibatalkan') { // Jika ada status 'dibatalkan'
                                            $message_text = 'Status: Pesanan Dibatalkan.';
                                            $button_html = '<button class="btn btn-sm btn-danger mt-2 disabled">Dibatalkan</button>';
                                             $message_class = 'status-dibatalkan';
                                        }
                                        else {
                                            // Fallback for any other status
                                            $message_text = 'Status: ' . htmlspecialchars($status);
                                            $button_html = '<button class="btn btn-sm btn-secondary mt-2 disabled">Status: ' . htmlspecialchars($status) . '</button>';
                                        }
                                        ?>
                                        <p class="mb-2 <?php echo $message_class; ?>"><?php echo $message_text; ?></p>
                                        <?php echo $button_html; // Display the generated button (or empty string if no button) ?>
                                    </div>
                                    <!-- End Status Message and Button Area -->

                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <!-- Order List Content End -->


            <!-- Footer Start -->
            <!-- Asumsi ada file footer.php atau Anda paste kode footer di sini -->
            <?php // include 'footer.php'; ?>
            <!-- Footer End -->

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
    <!-- Midtrans Snap Script -->
    <script src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="SB-Mid-client-PpquWhRJmrYAXKQ8"></script>


    <!-- Template Javascript -->
    <script src="assets/menu/js/main.js"></script>

    <?php
    // include 'script/script.php'; // Sertakan script kustom Anda jika diperlukan
    ?>

     <script type="text/javascript">
    // --- Midtrans Payment Script Integration ---
    $(document).ready(function() {
        $('.bayar-midtrans').click(function() {
            var orderId = $(this).data('order-id');
            var totalPrice = $(this).data('total-price');

            $.ajax({
                url: 'midtrans_transaction.php', // Pastikan file ini ada
                method: 'POST',
                data: {
                    order_id: orderId,
                    total_price: totalPrice,
                    user_id: <?php echo $loggedInUserId; ?>
                },
                success: function(response) {
                    try {
                        var result = JSON.parse(response);

                        if (result.snap_token) {
                            snap.pay(result.snap_token, {
                                onSuccess: function(res){
                                    alert("Pembayaran berhasil!");
                                    location.reload(); // Reload untuk update status
                                },
                                onPending: function(res){
                                    alert("Menunggu pembayaran...");
                                    // Mungkin tidak perlu reload langsung, status bisa diupdate via webhook
                                    // location.reload();
                                },
                                onError: function(res){
                                    alert("Pembayaran gagal!");
                                    // location.reload();
                                },
                                onClose: function(){
                                    alert('Popup pembayaran ditutup tanpa menyelesaikan pembayaran');
                                }
                            });
                        } else if (result.error) {
                             alert("Error creating transaction: " + result.error);
                             console.error(result);
                        } else {
                             alert("Unexpected response from server.");
                             console.error(response); // Log full response for debugging
                        }
                    } catch (e) {
                        alert("Error parsing server response.");
                        console.error("Response:", response, "Error:", e);
                    }
                },
                error: function(xhr, status, error) {
                    alert("Error communicating with payment server.");
                    console.error(xhr.responseText);
                }
            });
        });
    });
    </script>

</body>

</html>

<?php
// Penutupan koneksi opsional tergantung config.php
// mysqli_close($con);
?>