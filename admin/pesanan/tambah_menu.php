<?php
include '../koneksi.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nama_menu = $_POST['nama_menu'];
    $kategori = $_POST['kategori'];
    $harga = $_POST['harga'];
    $likes = 0; // Default awal likes

    // Upload gambar
    $gambar = $_FILES['gambar']['name'];
    $tmp_name = $_FILES['gambar']['tmp_name'];
    $upload_path = "../../images/" . $gambar;

    if (move_uploaded_file($tmp_name, $upload_path)) {
        // Use prepared statement to prevent SQL injection
        $sql = "INSERT INTO menu (nama_menu, kategori, harga, likes, gambar) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssdis", $nama_menu, $kategori, $harga, $likes, $gambar); // s=string, d=decimal, i=integer

        if ($stmt->execute()) {
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() { // Wait for the DOM to load
                    Swal.fire({
                        title: 'Berhasil!',
                        text: 'Menu berhasil ditambahkan!',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = 'index.php';
                        }
                    });
                });
            </script>";
        } else {
            echo "Error: " . $stmt->error; // Display specific error from the prepared statement
        }
        $stmt->close();
    } else {
        echo "<script>
                document.addEventListener('DOMContentLoaded', function() { // Wait for the DOM to load
                    Swal.fire({
                        title: 'Gagal!',
                        text: 'Upload gambar gagal. Coba lagi.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                });
            </script>";
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Resto-Rahmat - Tambah Menu</title>
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
    <link rel="shortcut icon" href="../assets/images/favicon.png" />


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

        .image-popup {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .image-popup img {
            max-width: 90%;
            max-height: 90%;
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
                                    <form method="POST" enctype="multipart/form-data" class="forms-sample">
                                        <div class="form-group">
                                            <label for="nama_menu">Nama Menu</label>
                                            <input type="text" name="nama_menu" class="form-control" id="nama_menu" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="kategori">Kategori</label>
                                            <select name="kategori" class="form-control" id="kategori" required>
                                                <option value="makanan">Makanan</option>
                                                <option value="minuman">Minuman</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label for="harga">Harga</label>
                                            <input type="number" name="harga" class="form-control" id="harga" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="gambar">Gambar</label>
                                            <input type="file" name="gambar" class="form-control" id="gambar" accept="image/*" onchange="previewImage()" required>
                                            <div id="preview-container" class="mt-3" style="display:none;">
                                                <p><strong>Preview:</strong></p>
                                                <img id="preview-img" src="" alt="Preview" class="img-fluid rounded shadow" style="max-width: 250px; height: auto;">
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-primary mr-2">Tambah</button>
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

    <script>
        function previewImage() {
            const input = document.getElementById('gambar');
            const previewContainer = document.getElementById('preview-container');
            const previewImage = document.getElementById('preview-img');

            const file = input.files[0];

            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    previewContainer.style.display = 'block';
                }
                reader.readAsDataURL(file);
            } else {
                previewContainer.style.display = 'none';
            }
        }
    </script>
</body>

</html>