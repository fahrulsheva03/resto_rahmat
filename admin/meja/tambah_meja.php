<?php
session_start();

// Sertakan file koneksi.php
include '../koneksi.php';
include 'phpqrcode/qrlib.php'; // Sertakan library QR Code
include 'config.php'; // Sesuaikan path ke config.php


// Periksa apakah pengguna sudah login (opsional, tergantung kebutuhan Anda)
if (!isset($_SESSION['username'])) {
  // Jika tidak login, mungkin redirect atau tampilkan pesan
  header("Location: ../auth/index.php?login_required=true");
  exit();
}
// Ambil informasi pengguna dari sesi
$username = $_SESSION['username'];
$role = $_SESSION['role']; // Ambil juga role pengguna

// Folder untuk menyimpan QR Code (pastikan folder ini bisa diakses dan ditulis)
$folder_qr = "qr_code/"; // Sesuaikan path ke folder QR
if (!file_exists($folder_qr)) {
    mkdir($folder_qr, 0777, true); // Buat folder jika belum ada
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ambil ID Meja dari form
    // Perhatikan bahwa kita asumsikan ID meja diisi secara manual pada form
    // atau Anda memiliki logika untuk mendapatkan ID meja selanjutnya secara otomatis.
    // Di sini, kita mengambil dari input 'id_meja'.
    $id_meja = $_POST['id_meja'];

    // --- Generasi QR Code ---
    // URL katalog menu dengan parameter ID meja
    // PERHATIAN: Anda perlu menyesuaikan base URL sesuai dengan setup server Anda
    // Gunakan domain atau IP server yang benar
    $qr_content = QR_BASE_URL . $id_meja; // Gunakan konstanta global
    $qr_filename = "meja_" . $id_meja . ".png"; // Nama file QR Code
    $qr_filepath = $folder_qr . $qr_filename;

    // Hasilkan QR Code
    // Sesuaikan level error correction dan ukuran (10 adalah skala)
    QRcode::png($qr_content, $qr_filepath, QR_ECLEVEL_L, 10);
    // --- End Generasi QR Code ---

    // Simpan data meja ke database
    // Di sini kita hanya menyimpan id_meja dan nama file QR Code
    $sql = "INSERT INTO meja (id_meja, image_kode_qr) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    // Parameter binding: i untuk integer (id_meja), s untuk string (qr_filename)
    $stmt->bind_param("is", $id_meja, $qr_filename);

    if ($stmt->execute()) {
        // Meja berhasil ditambahkan, tampilkan SweetAlert sukses
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: 'Berhasil!',
                    text: 'Meja dengan ID " . $id_meja . " dan QR Code berhasil ditambahkan!',
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'index.php'; // Redirect kembali ke halaman daftar meja
                    }
                });
            });
        </script>";
    } else {
        // Error saat menambahkan ke database
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: 'Error!',
                    text: 'Gagal menambahkan meja: " . $stmt->error . "',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            });
        </script>";
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Resto-Rika - Tambah Meja</title>
    <!-- plugins:css -->
    <link rel="stylesheet" href="../assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../assets/vendors/flag-icon-css/css/flag-icon.min.css">
    <link rel="stylesheet" href="../assets/vendors/css/vendor.bundle.base.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- endinject -->
    <!-- Plugin css for this page -->
    <!-- End plugin css for this page -->
    <!-- inject:css -->
    <link rel="stylesheet" href="../assets/css/demo_2/style.css" />
    <!-- End layout styles -->
    <link rel="shortcut icon" href="../assets/images/logo.png" />


    <style>
        .content-wrapper {
            background: #f8f9fa;
            /* Light background */
            padding: 2rem;
        }

        .page-header {
            margin-bottom: 2rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #dee2e6;
            /* Add a subtle border */
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            font-weight: bold;
        }

        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }

        .btn-primary:hover {
            background-color: #0069d9;
            border-color: #0062cc;
        }

        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #545b62;
        }
    </style>
</head>

<body>
    <div class="container-scroller">

        <?php include '../partials/_navbar.php'; ?>

        <div class="container-fluid page-body-wrapper">
            <div class="main-panel">
                <div class="content-wrapper">
                    <div class="row justify-content-center">
                        <div class="grid-margin stretch-card col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h4 class="card-title">Tambah Meja Baru</h4>
                                    <p class="card-description">Tambahkan data meja baru beserta QR Code-nya</p>
                                    <form method="POST" enctype="multipart/form-data" class="forms-sample">
                                        <div class="form-group">
                                            <label for="id_meja">ID Meja</label>
                                            <!-- Input untuk ID Meja -->
                                            <!-- Jika Anda menggunakan auto-increment di database, input ini tidak diperlukan
                                                 dan Anda perlu mengambil ID yang baru di-insert setelah query insert.
                                                 Namun, dari struktur tabel yang Anda berikan (tidak auto-increment),
                                                 input ID Meja diperlukan. Pastikan ID yang dimasukkan unik. -->
                                            <input type="number" name="id_meja" class="form-control" id="id_meja" required min="1">
                                        </div>
                                        <!-- Input gambar dihapus karena QR Code di-generate -->
                                        <button type="submit" class="btn btn-primary mr-2">Tambah Meja</button>
                                        <a href="index.php" class="btn btn-secondary">Kembali</a>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- main-panel ends -->
        </div>
        <!-- page-body-wrapper ends -->
    </div>
    <!-- container-scroller -->
    <!-- plugins:js -->
    <script src="../assets/vendors/js/vendor.bundle.base.js"></script>
    <!-- endinject -->
    <!-- Plugin js for this page -->
    <!-- End plugin js for this page -->
    <!-- inject:js -->
    <script src="../assets/js/off-canvas.js"></script>
    <script src="../assets/js/hoverable-collapse.js"></script>
    <script src="../assets/js/misc.js"></script>
    <script src="../assets/js/settings.js"></script>
    <script src="../assets/js/todolist.js"></script>
    <!-- endinject -->
    <!-- Custom js for this page -->
    <!-- End custom js for this page -->

</body>

</html>