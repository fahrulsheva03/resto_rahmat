<?php
session_start();

// Sertakan file koneksi.php
include '../koneksi.php';
// Sertakan file konfigurasi global
include 'config.php'; // Pastikan path ini benar ke lokasi config.php
include 'phpqrcode/qrlib.php'; // Sertakan library QR Code di sini, karena dibutuhkan untuk update

// Periksa apakah pengguna sudah login
if (!isset($_SESSION['username'])) {
    header("Location: ../auth/index.php?login_required=true"); // Redirect ke login dengan pemberitahuan
    exit();
}

// Ambil informasi pengguna dari sesi
$username = $_SESSION['username'];
$role = $_SESSION['role']; // Ambil juga role pengguna

// --- Logika Perbarui Semua QR Code ---
// Periksa apakah ada permintaan untuk memperbarui semua QR Code
if (isset($_GET['action']) && $_GET['action'] == 'update_all_qr') {

    // Folder untuk menyimpan QR Code: Relatif ke index.php (di /admin/meja/)
    $folder_qr = "qr_code/";
    if (!file_exists($folder_qr)) {
        mkdir($folder_qr, 0777, true); // Buat folder jika belum ada
    }

    // Ambil semua ID Meja dari database
    $sql_get_meja_ids = "SELECT id_meja, image_kode_qr FROM meja";
    $result_meja_ids = $conn->query($sql_get_meja_ids);

    $updated_count = 0;
    $failed_ids = [];

    if ($result_meja_ids->num_rows > 0) {
        while ($row_id = $result_meja_ids->fetch_assoc()) {
            $id_meja = $row_id['id_meja'];
            $old_qr_filename = $row_id['image_kode_qr']; // Nama file lama dari DB

            // Konten QR Code menggunakan konstanta global dari config.php
            $qr_content = QR_BASE_URL . $id_meja;
            $qr_filename = "meja_" . $id_meja . ".png"; // Nama file QR Code baru (sesuaikan format jika beda)

            $old_qr_filepath = $folder_qr . $old_qr_filename; // Path lengkap file lama
            $new_qr_filepath = $folder_qr . $qr_filename; // Path lengkap file baru yang akan dibuat

            // Logika penghapusan file lama:
            // Jika nama file lama di DB berbeda dari format nama file baru,
            // coba hapus file lama yang ada di folder.
            if ($old_qr_filename && file_exists($old_qr_filepath) && $old_qr_filename !== $qr_filename) {
                 @unlink($old_qr_filepath);
            }
             // Jika nama file lama dan baru sama (ID meja tidak berubah, hanya konten/ukuran yang mungkin update)
             // QRcode::png akan otomatis menimpa, jadi hapus manual sebenarnya opsional,
             // tetapi bisa dilakukan untuk kepastian atau jika ada kasus khusus.
            //  else if ($old_qr_filename && $old_qr_filename === $qr_filename && file_exists($old_qr_filepath)) {
            //      @unlink($old_qr_filepath);
            //  }


            // Hasilkan QR Code baru
            try {
                // QRcode::png akan membuat file baru atau menimpa jika sudah ada dengan nama yang sama
                QRcode::png($qr_content, $new_qr_filepath, QR_ECLEVEL_L, 10);
                $updated_count++;

                // Update nama file di database JIKA nama file hasil generate baru berbeda dengan yang tercatat (misal format penamaan file berubah)
                // Jika nama file formatnya konsisten (meja_ID.png), baris ini bisa dihilangkan atau hanya update jika kolom DB null/kosong
                if ($old_qr_filename !== $qr_filename) {
                     $sql_update_db = "UPDATE meja SET image_kode_qr = ? WHERE id_meja = ?";
                     $stmt_update_db = $conn->prepare($sql_update_db);
                     $stmt_update_db->bind_param("si", $qr_filename, $id_meja);
                     $stmt_update_db->execute();
                     $stmt_update_db->close();
                }


            } catch (Exception $e) {
                // Tangani jika ada error saat membuat QR Code
                $failed_ids[] = $id_meja . ": " . $e->getMessage();
            }
        }
    }

    // Tampilkan SweetAlert hasil pembaruan
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            ";
    if ($updated_count > 0 && count($failed_ids) == 0) {
        echo "Swal.fire({
                    title: 'Berhasil!',
                    text: '" . $updated_count . " QR Code meja berhasil diperbarui.',
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then(() => {
                     window.location.href = 'index.php'; // Refresh halaman
                });";
    } else if ($updated_count > 0 && count($failed_ids) > 0) {
        echo "Swal.fire({
                    title: 'Perhatian!',
                    text: '" . $updated_count . " QR Code meja berhasil diperbarui, tetapi ada beberapa yang gagal: " . implode(", ", $failed_ids) . ". Periksa log server jika perlu.',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.location.href = 'index.php'; // Refresh halaman
                });";
    } else if (count($failed_ids) > 0) {
        echo "Swal.fire({
                    title: 'Gagal Total!',
                    text: 'Gagal memperbarui semua QR Code meja: " . implode(", ", $failed_ids) . ". Periksa log server jika perlu.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.location.href = 'index.php'; // Refresh halaman
                });";
    } else {
        echo "Swal.fire({
                   title: 'Informasi!',
                   text: 'Tidak ada meja ditemukan untuk diperbarui QR Code-nya.',
                   icon: 'info',
                   confirmButtonText: 'OK'
               }).then(() => {
                   window.location.href = 'index.php'; // Refresh halaman
               });";
    }
    echo "
        });
    </script>";

    $conn->close(); // Tutup koneksi setelah selesai memproses pembaruan QR Code
    Echo '<meta http-equiv="refresh" content="0;url=index.php">';
    exit(); // Penting: Keluar dari skrip agar tidak menampilkan konten HTML di bawah saat update_all_qr
    // exit();
}
// --- End Logika Perbarui Semua QR Code ---

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Resto-Rahmat - Meja</title>
    <!-- plugins:css -->
    <link rel="stylesheet" href="../assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../assets/vendors/flag-icon-css/css/flag-icon.min.css">
    <link rel="stylesheet" href="../assets/vendors/css/vendor.bundle.base.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script> <!-- Ubah async defer menjadi biasa -->

    <!-- Font Awesome CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- endinject -->
    <!-- Plugin css for this page -->
    <!-- End plugin css for this page -->
    <!-- inject:css -->
    <link rel="stylesheet" href="../assets/css/demo_2/style.css" />
    <!-- End layout styles -->
    <link rel="shortcut icon" href="../assets/images/logo.png" />
    <style>
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

        .btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #212529;
            /* Text color for contrast */
        }

        .btn-warning:hover {
            background-color: #e0a800;
            border-color: #d39e00;
            color: #212529;
        }

         .btn-info {
            background-color: #17a2b8;
            border-color: #17a2b8;
            color: #fff; /* Text color for contrast */
        }
        .btn-info:hover {
            background-color: #138496;
            border-color: #117a8b;
            color: #fff;
        }
    </style>
</head>

<body>
    <div class="container-scroller">

        <?php include '../partials/_navbar.php' ?>

        <div class="container-fluid page-body-wrapper">
            <div class="main-panel">
                <div class="content-wrapper">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                        <div class="header-left">
                            <a href="tambah_meja.php" class="btn btn-primary mb-2 mb-md-0 mr-2">Tambah Meja Baru</a>
                            <!-- Tombol untuk memperbarui semua QR Code -->
                            <button class="btn btn-warning mb-2 mb-md-0 mr-2" onclick="confirmUpdateAllQr()">Perbarui Semua QR</button>
                             <!-- Tombol untuk mencetak semua QR Code -->
                            <a href="print_qr.php?all=true" target="_blank" class="btn btn-info mb-2 mb-md-0"> Print All QR</a>

                        </div>
                    </div>

                    <?php
                    // Include koneksi lagi jika skrip update semua QR dieksekusi di atas dan koneksi ditutup
                    if (!isset($conn) || !$conn) {
                        include '../koneksi.php';
                    }

                    // Delete Meja Item (if requested)
                    if (isset($_GET['delete_id'])) {
                        $delete_id = $_GET['delete_id'];

                        // Ambil nama file QR Code sebelum menghapus data meja
                        $sql_get_qr_file = "SELECT image_kode_qr FROM meja WHERE id_meja = ?";
                        $stmt_get_qr = $conn->prepare($sql_get_qr_file);
                        $stmt_get_qr->bind_param("i", $delete_id);
                        $stmt_get_qr->execute();
                        $result_qr = $stmt_get_qr->get_result();
                        $qr_file_to_delete = null;
                        if ($result_qr->num_rows > 0) {
                            $row_qr = $result_qr->fetch_assoc();
                            $qr_file_to_delete = $row_qr['image_kode_qr'];
                        }
                        $stmt_get_qr->close();

                        // Hapus data meja dari database
                        $sql_delete = "DELETE FROM meja WHERE id_meja = ?";
                        $stmt_delete = $conn->prepare($sql_delete);
                        $stmt_delete->bind_param("i", $delete_id);

                        if ($stmt_delete->execute()) {
                             // Jika data meja berhasil dihapus, hapus juga file QR Code terkait
                             if ($qr_file_to_delete) {
                                 // Path ini relatif ke lokasi index.php (/admin/meja/)
                                $qr_filepath_to_delete = "qr_code/" . $qr_file_to_delete;
                                if (file_exists($qr_filepath_to_delete)) {
                                    @unlink($qr_filepath_to_delete); // Gunakan @ untuk menekan error jika file tidak ada atau tidak bisa dihapus
                                }
                             }

                            echo "<script>
                            document.addEventListener('DOMContentLoaded', function() {
                                function showDeleteAlert() {
                                    Swal.fire({
                                        title: 'Berhasil!',
                                        text: 'Meja berhasil dihapus!',
                                        icon: 'success',
                                        confirmButtonText: 'OK'
                                    }).then(() => {
                                        window.location.href = 'index.php'; // Refresh the page
                                    });
                                }

                                if (typeof Swal !== 'undefined') {
                                    showDeleteAlert();
                                } else {
                                    setTimeout(showDeleteAlert, 50);
                                }
                            });
                            </script>";
                        } else {
                            echo "<script>
                            document.addEventListener('DOMContentLoaded', function() {
                                function showErrorAlert() {
                                    Swal.fire({
                                        title: 'Error!',
                                        text: 'Gagal menghapus meja: " . $stmt_delete->error . "',
                                        icon: 'error',
                                        confirmButtonText: 'OK'
                                    });
                                }

                                if (typeof Swal !== 'undefined') {
                                    showErrorAlert();
                                } else {
                                    setTimeout(showErrorAlert, 50);
                                }
                            });
                            </script>";
                        }
                    }

                    // Function to display table data (Meja)
                    function displayTableData($conn)
                    {
                        echo "<h3>Data Meja</h3>";
                        echo "<div class='grid-margin stretch-card'>";
                        echo "<div class='card'>";
                        echo "<div class='card-body'>";
                        echo "<div class='table-responsive'>";
                        echo "<table class='table table-hover'>";
                        echo "<thead class='text-center'>";
                        echo "<tr><th>ID Meja</th><th>QR Code Image</th><th>Action</th></tr>";
                        echo "</thead>";
                        echo "<tbody class='text-center'>";

                        $sql = "SELECT * FROM meja ORDER BY id_meja ASC"; // Optional: order by ID
                        $result = $conn->query($sql);
               
                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row["id_meja"]) . "</td>";
                                // Path gambar QR relative ke direktori 'pages/admin/meja'
                                // Sesuaikan path ini agar sesuai dengan lokasi di server
                                echo "<td><a href='#' onclick='showImage(\"qr_code/" . htmlspecialchars($row["image_kode_qr"]) . "\", \"QR Code for Table " . htmlspecialchars($row["id_meja"]) . "\"); return false;'><img src='qr_code/" . htmlspecialchars($row["image_kode_qr"]) . "' alt='QR Code for Table " . htmlspecialchars($row["id_meja"]) . "' style='width: 100px; height: 100px;'></a></td>";
                                echo "<td>";
                                // // Tombol Edit
                                // echo "<a href='edit_meja.php?id=" . htmlspecialchars($row["id_meja"]) . "' class='btn btn-sm btn-warning mr-1'><i class='fas fa-refresh'></i> Perbarui</a>";
                                // Tombol Print
                                // Mengirim ID meja ke print_qr.php di jendela/tab baru
                                echo "<a href='print_qr.php?id=" . htmlspecialchars($row["id_meja"]) . "' target='_blank' class='btn btn-sm btn-info mr-1'><i class='fas fa-print'></i> Print</a>";
                                // Tombol Delete
                                echo "<button onclick='confirmDelete(" . htmlspecialchars($row["id_meja"]) . ")' class='btn btn-sm btn-danger'><i class='fas fa-trash'></i> Delete</button>";
                                echo "</td>";
                                echo "</tr>";               
                            }
                        } else {
                            echo "<tr><td colspan='4'>Tidak ada data meja ditemukan.</td></tr>";
                        }

                        echo "</tbody>";
                        echo "</table>";
                        echo "</div>";
                        echo "</div>";
                        echo "</div>";
                        echo "</div>";
                    }

                    // Tampilkan data dari tabel 'meja' hanya jika skrip update semua QR tidak dieksekusi
                    if (!isset($_GET['action']) || $_GET['action'] != 'update_all_qr') {
                        displayTableData($conn);
                        // Tutup koneksi di sini jika tidak ditutup sebelumnya
                        if (isset($conn) && $conn) {
                            $conn->close();
                        }
                    }

                    ?>
                </div>
                <div class="image-popup" id="imagePopup">
                    <img src="" alt="Full Size Image" id="popupImage">
                </div>
            </div>
            <!-- content-wrapper ends -->
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
        function showImage(imageSrc, imageName) {
            document.getElementById('popupImage').src = imageSrc;
            document.getElementById('popupImage').alt = imageName;
            document.getElementById('imagePopup').style.display = 'flex';
        }

        document.getElementById('imagePopup').addEventListener('click', function() {
            this.style.display = 'none';
        });

        function confirmDelete(id) {
            Swal.fire({
                title: 'Apakah Anda yakin?',
                text: "Data meja ini akan dihapus secara permanen!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33', // Ubah warna tombol hapus
                cancelButtonColor: '#6c757d', // Warna abu-abu untuk batal
                confirmButtonText: 'Ya, hapus!'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'index.php?delete_id=' + id;
                }
            });
        }

        // Fungsi untuk konfirmasi update semua QR Code
        function confirmUpdateAllQr() {
            Swal.fire({
                title: 'Perbarui Semua QR Code?',
                text: "Ini akan membuat ulang semua QR Code meja berdasarkan URL konfigurasi. Pastikan URL konfigurasi Anda sudah benar.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#ffc107', // Warna kuning untuk update
                cancelButtonColor: '#6c757d', // Warna abu-abu untuk batal
                confirmButtonText: 'Ya, Perbarui!'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Menambahkan SweetAlert loading sebelum redirect
                     Swal.fire({
                        title: 'Memperbarui QR Codes...',
                        text: 'Mohon tunggu sebentar.',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading()
                        }
                    });
                    window.location.href = 'index.php?action=update_all_qr'; // Kirim permintaan update
                }
            });
        }
    </script>

</body>

</html>