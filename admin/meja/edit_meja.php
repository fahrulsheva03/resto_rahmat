<?php
session_start();

// Sertakan file koneksi.php, library QR Code, dan konfigurasi global
include '../koneksi.php'; // Sesuaikan path
include 'phpqrcode/qrlib.php'; // Sertakan library QR Code. Sesuaikan path jika berbeda
include 'config.php'; // Sesuaikan path ke config.php

// Periksa apakah pengguna sudah login (opsional, tergantung kebutuhan aplikasi Anda)
// Ini mencegah akses langsung ke halaman edit tanpa login.
if (!isset($_SESSION['username'])) {
    // Redirect ke halaman login dengan pemberitahuan
    header("Location: ../auth/index.php?login_required=true");
    exit();
}

// Ambil informasi pengguna dari sesi (jika diperlukan untuk kontrol akses, dll.)
$username = $_SESSION['username'];
$role = $_SESSION['role'];

// Folder untuk menyimpan file QR Code. Path ini relatif terhadap lokasi file edit_meja.php
// Karena edit_meja.php ada di /admin/meja/, maka "qr_code/" berarti /admin/meja/qr_code/
$folder_qr = "qr_code/";
// Buat folder jika belum ada, dengan izin akses 0777 (memberi izin penuh, perlu penyesuaian izin jika deployment server sesungguhnya)
if (!file_exists($folder_qr)) {
    mkdir($folder_qr, 0777, true);
}

// Pastikan parameter ID meja ada di URL GET
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "ID Meja tidak ditemukan di URL.";
    exit(); // Hentikan eksekusi jika ID tidak ada
}

// ID meja yang ingin diedit (diambil dari URL GET). Ini adalah ID ORIGINAL.
$id_meja_to_edit_original = $_GET['id'];

// --- Ambil data meja saat halaman pertama kali dimuat (sebelum ada POST) ---
// Data ini diperlukan untuk mengisi form di awal dan mengetahui nama file QR code lama
$sql_initial = "SELECT * FROM meja WHERE id_meja = ?";
$stmt_initial = $conn->prepare($sql_initial);
$stmt_initial->bind_param("i", $id_meja_to_edit_original);
$stmt_initial->execute();
$result_initial = $stmt_initial->get_result();

// Jika data meja dengan ID tersebut tidak ditemukan
if ($result_initial->num_rows == 0) {
    echo "Data meja dengan ID " . htmlspecialchars($id_meja_to_edit_original) . " tidak ditemukan.";
    $stmt_initial->close();
    $conn->close();
    exit(); // Hentikan eksekusi
}

// Simpan data awal meja ke variabel. $row_initial['id_meja'] adalah ID meja ASLI.
$row_initial = $result_initial->fetch_assoc();
$stmt_initial->close();

// --- Variabel untuk data yang akan ditampilkan di form dan penanganan pesan ---
// Defaultnya gunakan data initial. Jika POST berhasil, ini akan diperbarui.
$row_to_display = $row_initial;
$success = false; // Status berhasil update
$error_message = ''; // Untuk menampung pesan error

// --- Tangani Submit Form Pembaruan ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Ambil ID Meja yang MUNGKIN diubah oleh user dari form POST
    $new_id_meja_from_form = $_POST["id_meja"];

    // --- Validasi ID Meja baru dari form ---
    if (empty($new_id_meja_from_form) || !is_numeric($new_id_meja_from_form) || $new_id_meja_from_form < 1) {
        $error_message = "ID Meja baru tidak valid. Mohon masukkan nomor yang valid.";
    } else {
        // Ubah ID baru dari form menjadi integer
        $new_id_meja = (int) $new_id_meja_from_form;

        // --- START Database Transaction ---
        // Mulai transaksi untuk memastikan atomisitas operasi database dan file system
        $conn->begin_transaction();
        try {
            // 1. Hapus file QR Code lama (yang saat ini terkait dengan data meja ASLI sebelum update)
            // Dapatkan nama file QR lama yang tersimpan di database untuk ID meja original
            $old_qr_filename_from_db = $row_initial["image_kode_qr"];
            // Bentuk path lengkap ke file QR code lama di server
            // $folder_qr sudah relatif ke direktori tempat script ini berjalan (/admin/meja/)
            $old_qr_filepath_on_disk = $folder_qr . $old_qr_filename_from_db;

            // **PERUBAHAN LOGIKA HAPUS:**
            // Jika ada nama file lama tercatat di database, COBA HAPUS file tersebut.
            // Kita tidak perlu lagi cek apakah file itu file_exists() secara ketat.
            // unlink() akan gagal (dengan warning standar PHP jika tidak pakai @) jika file tidak ada,
            // atau gagal karena masalah izin, dll.
            // Dengan @ dan cek nilai return, kita mengubahnya menjadi error yang bisa ditangkap try/catch.
            if (!empty($old_qr_filename_from_db)) {
                 // Coba hapus file lama. Gunakan @unlink untuk menekan warning standar PHP jika file tidak ada,
                 // tapi tetap tangani error kritis jika unlink mengembalikan false (misalnya karena izin file).
                 if (file_exists($old_qr_filepath_on_disk)) { // Optional check here, mainly for logging clarity maybe
                     // Sebaiknya tetap pakai file_exists dulu, karena @unlink di Windows bisa kembalikan true
                     // bahkan jika file tidak ada di beberapa kondisi. Tapi kalau maunya HAPUS DULU BARU CEK...
                     // HAPUS DULU TANPA CEK file_exists():
                     error_reporting(0); // Matikan pelaporan error standar PHP untuk unlink
                     $delete_success = unlink($old_qr_filepath_on_disk);
                     error_reporting(E_ALL); // Aktifkan kembali pelaporan error

                     // Check status hapus
                     if ($delete_success === false && file_exists($old_qr_filepath_on_disk)) {
                        // Jika unlink mengembalikan FALSE DAN file masih ada di disk (menandakan kegagalan serius, misal izin)
                        // Ini adalah error yang ingin kita tangkap
                        throw new Exception("Gagal menghapus file QR Code lama: masalah izin atau error sistem di " . htmlspecialchars($old_qr_filepath_on_disk));
                     }
                     // Jika unlink mengembalikan TRUE (sukses hapus), atau FALSE tapi file sudah tidak ada
                     // (berarti file memang sudah tidak ada/gagal sejak awal, atau berhasil dihapus meskipun @unlink false),
                     // proses dianggap oke untuk bagian penghapusan. Tidak perlu throw exception di sini.

                 }
                 // Jika old_qr_filename_from_db ada, tapi file_exists mengembalikan false, kita biarkan saja.
                 // Tidak perlu Exception. Proses pembuatan QR baru akan menimpanya nanti kalau namanya sama.
            }
            // Jika old_qr_filename_from_db kosong, tidak ada file lama yang perlu dicoba dihapus.

            // 2. Proses Pembuatan QR Code Baru
            // Ini sama seperti sebelumnya
            $qr_content = QR_BASE_URL . $new_id_meja;
            $new_qr_filename = "meja_" . $new_id_meja . ".png";
            $new_qr_filepath_to_create = $folder_qr . $new_qr_filename;

            error_reporting(0); // Nonaktifkan error reporting untuk QRcode::png
            $qr_generation_success = QRcode::png($qr_content, $new_qr_filepath_to_create, QR_ECLEVEL_L, 10, 2);
            error_reporting(E_ALL); // Aktifkan kembali error reporting

            if (!$qr_generation_success) {
                 // Jika pembuatan QR gagal, lempar Exception untuk memicu rollback
                 throw new Exception("Gagal membuat file QR Code baru di: " . htmlspecialchars($new_qr_filepath_to_create));
            }

            // 3. Perbarui data di database
            // Ini sama seperti sebelumnya
            $sql_update = "UPDATE meja SET id_meja = ?, image_kode_qr = ? WHERE id_meja = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("isi", $new_id_meja, $new_qr_filename, $id_meja_to_edit_original);

            if (!$stmt_update->execute()) {
                 throw new Exception("Gagal memperbarui data meja di database: " . $stmt_update->error);
            }
            $stmt_update->close();

            // --- COMMIT Transaction ---
            $conn->commit();

            $success = true;
            $id_meja_to_edit_original = $new_id_meja;


        } catch (Exception $e) {
            // --- ROLLBACK Transaction on Error ---
            $conn->rollback();
            $error_message = 'Terjadi kesalahan saat memperbarui meja: ' . $e->getMessage();
            // Tambahkan logging error yang lebih detail jika perlu
        }

    } // end else (valid input ID dari form)

} // end if $_SERVER["REQUEST_METHOD"] == "POST"


// --- Fetch data terbaru meja UNTUK DITAMPILKAN DI FORM ---
// Lakukan SELECT ulang berdasarkan ID meja yang 'terbaru' (baik itu ID original jika POST gagal/tidak terjadi,
// atau ID baru dari POST jika POST berhasil)
$sql_fetch_latest = "SELECT * FROM meja WHERE id_meja = ?";
$stmt_fetch_latest = $conn->prepare($sql_fetch_latest);
// Bind parameter menggunakan variabel ID yang terakhir sukses di-update atau original
$stmt_fetch_latest->bind_param("i", $id_meja_to_edit_original);
$stmt_fetch_latest->execute();
$result_fetch_latest = $stmt_fetch_latest->get_result();

// Cek apakah data terbaru ditemukan
if ($result_fetch_latest->num_rows > 0) {
    // Jika ditemukan, gunakan data ini untuk mengisi form
    $row_to_display = $result_fetch_latest->fetch_assoc();
} else {
    // Jika data meja tidak ditemukan SETELAH (atau bahkan sebelum) upaya update.
    // Ini bisa terjadi jika user mengutak-atik URL atau ID di database hilang.
    // Jika belum ada pesan error lain dari POST, set pesan error.
    if (empty($error_message)) {
        $error_message = "Data meja tidak ditemukan setelah operasi.";
        // Mungkin redirect user kembali ke halaman daftar meja?
        // header("Location: index.php?msg=" . urlencode($error_message));
        // exit(); // Uncomment untuk melakukan redirect otomatis
    }
    // Jika data tidak ditemukan, $row_to_display akan tetap pada nilai awal (atau kosong jika tidak ada initial data)
    // dan form mungkin tampil kosong atau dengan ID yang lama.
}

$stmt_fetch_latest->close();

// --- TUTUP KONEKSI DATABASE ---
// Penting: Tutup koneksi SETELAH semua operasi database (FETCH dan/atau POST update) selesai.
$conn->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Edit Meja - resto rahmat</title>
    <!-- Plugins CSS -->
    <link rel="stylesheet" href="../assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../assets/vendors/flag-icon-css/css/flag-icon.min.css">
    <link rel="stylesheet" href="../assets/vendors/css/vendor.bundle.base.css">
    <!-- Layout styles CSS -->
    <link rel="stylesheet" href="../assets/css/demo_2/style.css" />
    <!-- Favicon -->
    <link rel="shortcut icon" href="../assets/images/logo.png" />
    <!-- SweetAlert2 -->
    <!-- Penting: Tempatkan sebelum script JS kustom yang memanggil Swal -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Custom styles -->
    <style>
        /* Gaya tambahan untuk form dan konten */
        .content-wrapper { background: #f8f9fa; padding: 2rem; }
        .page-header { margin-bottom: 2rem; padding-bottom: 0.5rem; border-bottom: 1px solid #dee2e6; }
        .form-group { margin-bottom: 1.5rem; }
        label { font-weight: bold; }
        .btn-primary { background-color: #007bff; border-color: #007bff; }
        .btn-primary:hover { background-color: #0069d9; border-color: #0062cc; }
        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #545b62;
        }
        /* Gaya untuk pratinjau gambar QR */
        .qr-preview {
            display: block; /* Memulai baris baru */
            margin-top: 15px; /* Ruang di atas gambar */
            border: 1px solid #ccc; /* Opsional: border */
            padding: 5px; /* Opsional: padding */
            max-width: 150px; /* Ukuran maksimum gambar */
            height: auto; /* Menjaga rasio aspek */
        }
    </style>
</head>
<body>
<div class="container-scroller">
    <!-- Sertakan komponen navbar -->
    <?php include '../partials/_navbar.php'; ?>

    <div class="container-fluid page-body-wrapper">
        <!-- Panel utama konten -->
        <div class="main-panel">
            <div class="content-wrapper">
                <div class="row justify-content-center">
                    <div class="col-md-6 grid-margin stretch-card">
                        <div class="card">
                            <div class="card-body">
                                <h4 class="card-title">Edit Data Meja</h4>
                                <p class="card-description">Ubah detail Meja dan perbarui QR Code.</p>

                                <!-- Tampilkan pesan error di bagian atas form jika ada -->
                                <?php if (!empty($error_message)): ?>
                                     <div class="alert alert-danger" role="alert">
                                        <?= htmlspecialchars($error_message); ?>
                                     </div>
                                <?php endif; ?>

                                <!-- Formulir Edit Meja -->
                                <!-- Arahkan form POST kembali ke halaman ini sendiri -->
                                <form method="POST">
                                    <div class="form-group">
                                        <label for="id_meja">ID Meja:</label>
                                        <!-- Input field untuk ID Meja -->
                                        <!-- Gunakan $row_to_display untuk nilai form -->
                                        <input type="number" class="form-control" id="id_meja" name="id_meja" value="<?= htmlspecialchars($row_to_display['id_meja'] ?? ''); ?>" required min="1">

                                         <!-- Menampilkan QR Code yang terkait dengan data yang SEKARANG ada (setelah fetch terbaru) -->
                                         <div class="mt-3">
                                             <p><strong>QR Code Terkini:</strong></p>
                                             <?php
                                                 // Dapatkan nama file QR terkini dari $row_to_display
                                                 $current_qr_filename = $row_to_display['image_kode_qr'] ?? null;
                                                 // Bentuk path lengkap ke file QR code terkini di server
                                                 $current_qr_filepath_on_disk = $folder_qr . $current_qr_filename;

                                                 // Bentuk URL relatif untuk ditampilkan di browser.
                                                 // Karena edit_meja.php ada di admin/meja/, kita butuh path yang naik satu tingkat(../)
                                                 // lalu masuk ke folder meja/, baru folder qr_code/
                                                 $display_qr_url_base = "../meja/" . $folder_qr; // Output: ../meja/qr_code/
                                                 $display_qr_url_full = $display_qr_url_base . htmlspecialchars($current_qr_filename ?? '');


                                                 // Cek jika nama file QR ada DAN file-nya ada di disk
                                                 if (!empty($current_qr_filename) && file_exists($current_qr_filepath_on_disk)) {
                                                     // Tampilkan gambar QR Code terkini
                                                     echo '<img src="' . htmlspecialchars($display_qr_url_full) . '" alt="Current QR Code for Table ' . htmlspecialchars($row_to_display['id_meja'] ?? 'N/A') . '" class="qr-preview">';
                                                 } else {
                                                      // Kasus jika QR Code tidak ditemukan di disk atau nama filenya kosong di DB
                                                      // Berikan keterangan visual atau pesan teks
                                                      echo '<p class="text-warning">File QR Code tidak ditemukan di server atau belum ada.</p>';

                                                      // Opsi: Buat dan tampilkan QR Code sementara dengan pesan error
                                                      // Agar tampilan tidak kosong, bisa buat QR 'dummy'
                                                      $dummy_qr_filepath_temp = ''; // Untuk melacak file temp
                                                      try {
                                                           // Konten QR untuk pesan error sementara
                                                          $dummy_qr_content = "Meja ID " . htmlspecialchars($row_to_display['id_meja'] ?? 'N/A') . ": QR Tidak Ada/Gagal";
                                                          // Buat nama file unik agar tidak menimpa file lain
                                                          $dummy_qr_filename_temp = "dummy_qr_" . md5($row_to_display['id_meja'] ?? '' . time() . uniqid()) . ".png";
                                                          $dummy_qr_filepath_temp = $folder_qr . $dummy_qr_filename_temp;

                                                          // Generate QR Code error (error_reporting off sementara)
                                                          error_reporting(0);
                                                           // Menggunakan QR_ECLEVEL_L dan ukuran kecil (8)
                                                          if (QRcode::png($dummy_qr_content, $dummy_qr_filepath_temp, QR_ECLEVEL_L, 8)) {
                                                              // Tampilkan dummy QR menggunakan URL relatif
                                                              $display_dummy_qr_url = $display_qr_url_base . htmlspecialchars($dummy_qr_filename_temp);
                                                               echo '<img src="' . htmlspecialchars($display_dummy_qr_url) . '" alt="QR Code Dummy Error" class="qr-preview">';
                                                               // Note: File dummy_qr_filepath_temp mungkin akan tertinggal di server
                                                          }
                                                          error_reporting(E_ALL);
                                                      } catch (Exception $e) {
                                                          // Jika gagal juga membuat QR dummy
                                                          echo '<p class="text-danger">Tidak dapat menampilkan pratinjau QR Code.</p>';
                                                      }
                                                 }
                                             ?>
                                             <p class="text-info mt-2"><small>QR Code akan diperbarui saat Anda klik "Update Meja".</small></p>
                                         </div>
                                    </div>
                                    <!-- Tidak ada input file gambar karena QR code di-generate -->

                                    <!-- Tombol Update dan Kembali -->
                                    <button type="submit" class="btn btn-primary mr-2">Update Meja</button>
                                    <a href="index.php" class="btn btn-secondary">Kembali ke Daftar Meja</a>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- content-wrapper ends -->

            <!-- Optional: Footer -->
            <!-- Anda bisa memasukkan footer di sini jika diperlukan -->
            <!-- include '../partials/_footer.php'; -->

        </div>
        <!-- main-panel ends -->
    </div>
    <!-- page-body-wrapper ends -->
</div>
<!-- container-scroller ends -->

<!-- plugins:js -->
<script src="../assets/vendors/js/vendor.bundle.base.js"></script>
<!-- endinject -->
<!-- Custom JS for this page -->
<script src="../assets/js/off-canvas.js"></script>
<script src="../assets/js/hoverable-collapse.js"></script>
<script src="../assets/js/misc.js"></script>
<script src="../assets/js/settings.js"></script>
<script src="../assets/js/todolist.js"></script>
<!-- endinject -->
<!-- Custom scripts other pages (optional) -->

<!-- Script SweetAlert2 untuk pesan Sukses -->
<?php if ($success): ?>
<script>
    // Jalankan script SweetAlert setelah seluruh dokumen DOM dimuat
    document.addEventListener("DOMContentLoaded", function () {
        Swal.fire({
            title: 'Berhasil!',
            text: 'Data meja dan QR Code berhasil diperbarui!',
            icon: 'success',
            confirmButtonText: 'OK'
        }).then((result) => {
            // Setelah pengguna menekan OK di SweetAlert, redirect kembali ke halaman daftar meja (index.php)
            window.location.href = 'index.php';
        });
    });
</script>
<?php endif; ?>

<?php
// Script SweetAlert2 untuk pesan Error
// Jika Anda tidak ingin menggunakan div alert, gunakan ini
/*
if (!empty($error_message) && !$success) {
     echo "<script>
     document.addEventListener('DOMContentLoaded', function() {
         Swal.fire({
             title: 'Error!',
             text: '" . htmlspecialchars($error_message, ENT_QUOTES) . "',
             icon: 'error',
             confirmButtonText: 'OK'
         });
     });
     </script>";
 }
*/
?>

</body>
</html>