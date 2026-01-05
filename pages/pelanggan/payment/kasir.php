
<?php
// kasir.php - Halaman Tampilan Customer untuk Pesanan Kasir & API Endpoint untuk Batal/Cek Status
// Script ini berfungsi GANDA: Merender halaman customer view kasir (jika GET request)
// DAN merespons permintaan API untuk Batal/Cek Status (jika POST request AJAX dari halaman ini).

// ===============================================================================================
// --- DEBUGGING BANTUAN: Tampilkan semua error/warning PHP ---
// MATIKAN INI (beri komentar) DI LINGKUNGAN PRODUKSI! Ini HANYA untuk membantu menemukan sumber
// Notice/Warning PHP yang menyebabkan error JSON atau output tak diinginkan lainnya.
ini_set('display_errors', 1);
error_reporting(E_ALL);
// PASTIKAN TIDAK ADA KARAKTER (spasi, newline) SEBELUM TAG PEMBUKA <?php
// ===============================================================================================


// session_start(); // Aktifkan jika perlu sesi untuk customer view kasir.

// Set timezone untuk format tanggal/jam (opsional, pastikan sesuai server/lokasi)
date_default_timezone_set('Asia/Jakarta');

// --- Konfigurasi dan Koneksi Database ---
// Kode koneksi database di sini (sesuai permintaan Anda untuk mengelola koneksi di file ini).
// Pastikan kredensial di bawah sudah benar.
$host = "localhost";
$user = "root"; // Ganti dengan username DB Anda jika berbeda
$pass = "";     // Ganti dengan password DB Anda jika berbeda
$db = "restoran"; // Ganti dengan nama DB Anda

$con = mysqli_connect($host, $user, $pass, $db);

// Cek koneksi dan tangani kegagalan
if (!$con) {
    // Log error koneksi di server side log
    error_log("Database connection failed in kasir.php: " . mysqli_connect_error());
    // Jika request adalah POST (dari AJAX), kirim respons JSON error koneksi dan exit.
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
         header('Content-Type: application/json');
         echo json_encode(['success' => false, 'message' => 'Kesalahan koneksi database. Mohon coba lagi atau hubungi staf.']);
         http_response_code(500); // Internal Server Error
         exit(); // Hentikan pemrosesan POST request jika koneksi DB gagal
    }
    // Jika request adalah GET (render halaman), koneksi DB mungkin tidak langsung dibutuhkan,
    // jadi pesan error tidak dikirimkan langsung, tapi logic POST sudah dijaga.
}


// ========================================================================================
// --- LOGIKA PEMROSESAN PERMINTAAN AJAX (POST REQUEST) ---
// Ini adalah API endpoint untuk actions (cancel, getStatus)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Penting: Set header Content-Type JSON sebelum output apapun.
    header('Content-Type: application/json');

    // Pastikan koneksi DB valid sebelum mencoba operasi database apapun.
    if (!isset($con) || !$con instanceof mysqli || $con->connect_error) {
        // Error koneksi sudah dilog di bagian atas, kirim respons JSON error lagi jika ini POST request.
        echo json_encode(['success' => false, 'message' => 'Koneksi database tidak tersedia.']);
        http_response_code(500);
        exit();
    }


    // Ambil dan decode data JSON dari body request
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);

    // Validasi jika data yang diterima BUKAN JSON valid
    if ($data === null && $json_data !== '' && json_last_error() !== JSON_ERROR_NONE) {
        error_log("Invalid JSON received in kasir.php POST: " . $json_data);
        echo json_encode(['success' => false, 'message' => 'Data permintaan POST tidak valid (bukan JSON).']);
        http_response_code(400);
        exit();
    }

    // Ambil action dan order_id dari data yang sudah didecode
    $orderId = $data['order_id'] ?? null; // Ambil Order ID atau null jika tidak ada
    $action = $data['action'] ?? null;     // Ambil Aksi atau null jika tidak ada

    // --- Validasi Parameter action dan order_id ---
    // Periksa apakah $orderId dan $action ada, dan $action nilainya diizinkan
    if ($orderId === null || $action === null || !in_array($action, ['cancel', 'getStatus'])) {
        echo json_encode(['success' => false, 'message' => 'Permintaan tidak lengkap atau aksi tidak valid.']);
        http_response_code(400); // Bad Request
        exit();
    }

    // Sanitasi Order ID dan pastikan formatnya angka positif
    $orderId_clean = filter_var($orderId, FILTER_SANITIZE_NUMBER_INT); // Hapus non-angka
    if (!is_numeric($orderId_clean) || (int)$orderId_clean <= 0) {
        echo json_encode(['success' => false, 'message' => 'Format Order ID tidak valid (harus angka).']);
        http_response_code(400);
        exit();
    }
    $orderId = (int)$orderId_clean; // Gunakan Order ID yang sudah divalidasi sebagai integer

    // --- Proses Aksi Berdasarkan $action ---

    // Aksi Membatalkan Pesanan ('cancel')
    if ($action === 'cancel') {
        $newStatus = 'dibatalkan'; // Status yang diinginkan di DB
        $currentStatusRequired = 'pending'; // Pesanan harus pending untuk dibatalkan

        // Gunakan prepared statement untuk UPDATE
        $sql = "UPDATE pesanan SET status = ? WHERE id_pesanan = ? AND status = ? AND metode_pembayaran = 'kasir'";
        $stmt = $con->prepare($sql);

        if ($stmt) {
             // Bind parameter: string, integer, string
             $stmt->bind_param("sis", $newStatus, $orderId, $currentStatusRequired);

            if ($stmt->execute()) {
                // mysqli_stmt_affected_rows > 0 artinya update berhasil mengubah setidaknya satu baris.
                // Ini terjadi jika pesanan ditemukan DENGAN status 'pending'.
                if ($stmt->affected_rows > 0) {
                    // Sukses: Status berhasil diubah dari pending ke dibatalkan
                    echo json_encode(['success' => true, 'message' => 'Pesanan berhasil dibatalkan.', 'order_id' => $orderId, 'new_status' => $newStatus]);
                } else {
                    // Tidak ada baris yang terpengaruh. Kemungkinan ID salah, status bukan 'pending', atau metode bukan 'kasir'.
                    // Lakukan cek tambahan untuk memberikan feedback yang lebih spesifik.
                    $stmtCheck = $con->prepare("SELECT status, metode_pembayaran FROM pesanan WHERE id_pesanan = ?");
                    if ($stmtCheck) {
                         $stmtCheck->bind_param("i", $orderId);
                         $stmtCheck->execute();
                         $resultCheck = $stmtCheck->get_result();
                         if ($resultCheck->num_rows > 0) {
                             $rowCheck = $resultCheck->fetch_assoc();
                             $current_status = $rowCheck['status'];
                             $metode_bayar = $rowCheck['metode_pembayaran'];

                             if ($metode_bayar !== 'kasir') {
                                  // Ditemukan, tapi metodenya bukan kasir
                                 echo json_encode(['success' => false, 'message' => "Tidak dapat membatalkan. Pesanan #{$orderId} bukan pembayaran Kasir.", 'order_id' => $orderId]);
                             } else {
                                 // Ditemukan, metode kasir, tapi status bukan 'pending'
                                 echo json_encode(['success' => false, 'message' => "Tidak dapat membatalkan pesanan #{$orderId}. Status saat ini: '" . htmlspecialchars(ucwords($current_status)) . "'. Hanya pesanan 'pending' yang bisa dibatalkan.", 'order_id' => $orderId, 'current_status' => $current_status]);
                             }
                         } else {
                             // Tidak ditemukan sama sekali dengan ID ini di tabel 'pesanan'.
                             echo json_encode(['success' => false, 'message' => "Pesanan dengan ID #{$orderId} tidak ditemukan.", 'order_id' => $orderId]);
                         }
                         $stmtCheck->close(); // Tutup statement cek status
                    } else {
                         // Gagal menyiapkan statement cek status sekunder
                         error_log("Kasir cancel failed secondary status check prepare: " . $con->error);
                         echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan saat validasi status pesanan.']);
                    }
                }
            } else {
                // Gagal mengeksekusi statement UPDATE (masalah DB lain, bukan sekadar 0 affected rows)
                error_log("Kasir cancel failed execution for Order ID {$orderId}: " . $stmt->error);
                echo json_encode(['success' => false, 'message' => 'Gagal mengeksekusi perintah batal pesanan. Error DB Execute: ' . $stmt->error]);
            }
            $stmt->close(); // Tutup statement UPDATE

        } else { // Gagal menyiapkan statement UPDATE
             error_log("Kasir cancel failed prepare: " . $con->error . " SQL: " . $sql);
            echo json_encode(['success' => false, 'message' => 'Gagal menyiapkan query batal pesanan. Error DB Prepare: ' . $con->error]);
        }

    // Aksi Memeriksa Status Pesanan ('getStatus')
    } elseif ($action === 'getStatus') {
        // Gunakan prepared statement untuk SELECT
        // Ambil kolom 'status' untuk pesanan dengan ID dan metode 'kasir'
        $sql = "SELECT status FROM pesanan WHERE id_pesanan = ? AND metode_pembayaran = 'kasir'";
        $stmt = $con->prepare($sql);

        if ($stmt) {
            $stmt->bind_param("i", $orderId); // bind order ID
            $stmt->execute(); // Jalankan query
            $result = $stmt->get_result(); // Ambil result set

            if ($result->num_rows > 0) {
                // Pesanan ditemukan dan metodenya kasir
                $order_status = $result->fetch_assoc()['status']; // Ambil nilai status (misal: pending, diproses, selesai, dibatalkan)
                echo json_encode(['success' => true, 'message' => 'Status pesanan ditemukan.', 'order_id' => $orderId, 'current_status' => $order_status]);
            } else {
                // Pesanan tidak ditemukan dengan ID dan metode 'kasir' yang cocok
                echo json_encode(['success' => false, 'message' => "Pesanan dengan ID #{$orderId} tidak ditemukan atau metode pembayaran bukan Kasir.", 'order_id' => $orderId]);
            }
            $stmt->close(); // Tutup statement SELECT

        } else { // Gagal menyiapkan statement SELECT
             error_log("Kasir getStatus failed prepare: " . $con->error);
            echo json_encode(['success' => false, 'message' => 'Gagal menyiapkan query cek status. Error DB Prepare: ' . $con->error]);
        }

    } // Semua action yang diizinkan sudah dihandle. Action lain di luar 'cancel' dan 'getStatus' akan dianggap invalid oleh validasi awal.

    // Setelah selesai memproses permintaan POST (berhasil atau gagal di level logic)
    // dan mengirimkan respons JSON, tutup koneksi DB jika masih terbuka dan hentikan eksekusi.
    if (isset($con) && $con instanceof mysqli && !$con->connect_error) {
        $con->close();
    }
    exit(); // <<< KRUSIAL: Hentikan eksekusi script setelah mengirim respons POST!

} // --- AKHIR LOGIKA PEMROSESAN POST REQUEST ---
// =====================================================================


// =====================================================================
// --- LOGIKA RENDERING HALAMAN CUSTOMER VIEW (GET REQUEST) ---
// Bagian ini akan dieksekusi jika request bukan POST (yaitu, request GET dari browser untuk menampilkan halaman)
// =====================================================================

// Proses Order ID dari URL untuk ditampilkan dan digunakan di JavaScript
$order_id_for_display = null; // Variabel untuk menyimpan ID pesanan (numeric, valid) untuk ditampilkan/diproses
$is_order_id_valid = false; // Flag boolean untuk menunjukkan apakah ID dari URL valid formatnya

// Cek apakah parameter 'order_id' ada dan tidak kosong di URL ($_GET)
if (isset($_GET['order_id']) && !empty($_GET['order_id'])) {
    $order_id_raw_get = $_GET['order_id']; // Ambil nilai string asli dari URL
    // Sanitasi string raw untuk membersihkan karakter selain angka
    $order_id_clean_get = filter_var($order_id_raw_get, FILTER_SANITIZE_NUMBER_INT);

    // Validasi hasil sanitasi: pastikan berupa angka dan lebih besar dari 0
    if (is_numeric($order_id_clean_get) && (int)$order_id_clean_get > 0) {
         $order_id_for_display = (int)$order_id_clean_get; // Konversi ke integer dan simpan ID yang valid
         $is_order_id_valid = true; // Tandai bahwa ID valid
    } else {
        // Jika hasil sanitasi bukan angka positif, ID tidak valid
         $order_id_for_display = null; // Pastikan ID null
         $is_order_id_valid = false; // Tandai ID tidak valid
    }
     // Simpan nilai raw (bisa jadi invalid) setelah HTML-encoding untuk ditampilkan jika perlu di pesan error
    $order_id_received_html = htmlspecialchars($order_id_raw_get);
} else {
    // Parameter 'order_id' tidak ada di URL sama sekali
     $order_id_for_display = null; // ID null
     $is_order_id_valid = false; // ID tidak valid
     $order_id_received_html = 'tidak ada'; // Teks untuk ditampilkan
}

// --- Ambil parameter meja, nama, dan telepon dari URL GET ---
// Ini akan digunakan untuk link "Kembali ke Menu"
$meja_from_url = $_GET['meja'] ?? null; // Ambil meja dari URL GET
$nama_from_url = $_GET['nama'] ?? null; // Ambil nama dari URL GET
$notelepon_from_url = $_GET['notelepon'] ?? null; // Ambil notelepon dari URL GET

// Opsi: Lakukan sanitasi tambahan jika Anda menampilkannya langsung di halaman kasir.php (selain di link kembali)
// $meja_for_display = htmlspecialchars($meja_from_url ?? '');
// $nama_for_display = htmlspecialchars($nama_from_url ?? '');
// $notelepon_for_display = htmlspecialchars($notelepon_from_url ?? '');


?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Kasir - #<?php echo $is_order_id_valid ? htmlspecialchars($order_id_for_display) : 'Invalid'; ?></title>
    <!-- Sertakan Tailwind CSS via CDN (Untuk DEVELOPMENT Saja, TIDAK PRODUKSI) -->
    <script src="https://cdn.tailwindcss.com"></script>
     <!-- Jika sudah pakai build process, link ke CSS build Anda di sini -->
     <!-- <link href="path/to/your/tailwind.css" rel="stylesheet"> -->
    <style>
        /* Optional: Tambahkan gaya kustom di sini jika perlu */
         /* Body and container layout ditangani Tailwind: min-h-screen, p-4, flex justify-center items-center */
         .payment-box { /* Tambahan shadow-xl kustom? shadow-lg sudah ada utility. */ }
         .order-id { color: #dc3545; /* Warna merah mirip Bootstrap */ }

         /* Styles for buttons, managed by Tailwind utility classes */
         .btn {
            transition: background-color 0.2s ease-in-out; /* Tambahkan sedikit animasi hover */
         }
         .btn:disabled { /* Disabled state ditangani utility class disabled:opacity-50, disabled:cursor-not-allowed */
             cursor: not-allowed;
             opacity: 0.5;
         }

         /* Styling for the status display area, managed by JS via utility classes:
            bg-blue-100, text-blue-800 (info), bg-green-100, text-green-800 (success), etc. */
         #statusDisplay {
              white-space: pre-wrap; /* Agar newline <br> bekerja */
         }

    </style>
</head>
<body class="bg-gray-100 flex flex-col justify-center items-center min-h-screen p-4">
    <!-- Gunakan flex-col agar elemen di dalam body tersusun vertikal -->

    <div class="payment-box bg-white p-8 rounded-lg shadow-xl text-center max-w-sm w-full mx-auto">
        <?php if ($is_order_id_valid): // Render konten utama hanya jika Order ID valid ?>

            <h3 class="text-2xl font-semibold text-blue-600 mb-4">Status Pesanan</h3> <!-- Diubah teksnya -->
            <p class="text-gray-700 text-lg mb-3">Pesanan Anda dengan nomor:</p> <!-- Diubah teksnya -->
            <!-- Tampilkan Order ID yang sudah divalidasi dan disanitasi -->
            <p class="order-id text-4xl font-bold mb-5">#<?php echo htmlspecialchars($order_id_for_display); ?></p>

            <?php
                // Tampilkan info meja jika ada di URL
                if (!empty($meja_from_url)) {
                     echo "<p class='text-sm text-gray-600 mt-2'>Meja: <strong>" . htmlspecialchars($meja_from_url) . "</strong></p>";
                }
                // Jika nama atau telepon juga ada di URL dan ingin ditampilkan
                 /*
                if (!empty($nama_from_url)) {
                     echo "<p class='text-sm text-gray-600 mt-1'>Pemesan: <strong>" . htmlspecialchars($nama_from_url) . "</strong></p>";
                }
                if (!empty($notelepon_from_url)) {
                     echo "<p class='text-sm text-gray-600 mt-1'>Telepon: <strong>" . htmlspecialchars($notelepon_from_url) . "</strong></p>";
                }
                */
            ?>

            <!-- Div grup tombol aksi -->
            <div class="button-group mt-6 flex flex-wrap justify-center gap-3">
                 <!-- Button Batalkan Pesanan -->
                 <!-- Awalnya disabled -->
                <button id="cancel-order-btn"
                        class="btn bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                        data-order-id="<?php echo htmlspecialchars($order_id_for_display); ?>">
                    Batalkan Pesanan
                </button>

                 <!-- Button Cek Status -->
                 <!-- Selalu terlihat -->
                 <button id="check-status-btn"
                         class="btn bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded text-sm disabled:opacity-50 disabled:cursor-not-allowed">
                    Cek Status
                 </button>

                 <!-- Button Cetak Struk -->
                 <!-- Awalnya disembunyikan dan disabled -->
                 <button id="print-receipt-btn"
                         class="btn bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded text-sm disabled:opacity-50 disabled:cursor-not-allowed hidden"
                         data-order-id="<?php echo htmlspecialchars($order_id_for_display); ?>">
                     Cetak Struk
                 </button>
            </div>

            <!-- Area untuk menampilkan status pesanan dari API -->
            <div id="statusDisplay" class="mt-5 p-3 rounded text-sm text-left" role="alert">
                <!-- Teks placeholder sebelum cek status pertama kali -->
                 Memuat status pesanan...
            </div>

        <?php else: // Tampilkan pesan error jika Order ID invalid di URL ?>
             <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative text-left" role="alert">
                 <strong class="font-bold">Error!</strong>
                 <span class="block sm:inline">Order ID tidak ditemukan atau tidak valid di URL.</span>
                 <?php if (isset($_GET['order_id'])): ?>
                      <span class="block text-sm">Order ID diterima: "<?php echo htmlspecialchars($_GET['order_id']); ?>"</span>
                 <?php endif; ?>
                 <span class="block mt-2">Mohon pastikan URL sudah benar atau hubungi staf restoran.</span>
            </div>
        <?php endif; ?>
    </div>

    <!-- === TOMBOL KEMBALI KE MENU (DITAMBAHKAN DI BAWAH KOTAK UTAMA) === -->
    <div class="mt-6 text-center w-full max-w-sm mx-auto"> <!-- Margin top, centering, set max-width -->
        <?php
         // === Persiapan Link Kembali ke Menu dari parameters URL KASIR.PHP ===
         // Mengambil parameter meja, nama, telepon DARI URL saat ini ($_GET)
         // Menggunakan variabel $meja_from_url, $nama_from_url, $notelepon_from_url yang sudah diambil di bagian PHP atas.

         // !!! SANGAT PENTING: SESUAIKAN PATH kembali ke index.php ini !!!
         // Hitung path relatif dari lokasi file kasir.php Anda ke file index.php halaman menu utama.
         // Contoh: kasir.php di /pages/pelanggan/, index.php di /pages/
         $base_back_url = "../index.php"; // <<< SESUAIKAN PATH KEMBALI INI !!!

         // Bentuk string parameter URL jika nilai tidak kosong
         $back_params = [];
         // Tambahkan parameter 'meja' jika $meja_from_url tidak kosong
         if (!empty($meja_from_url)) {
             // urlencode() penting untuk memastikan nilai parameter aman dalam URL
             $back_params[] = "meja=" . urlencode($meja_from_url);
         }
         // Tambahkan parameter 'nama' jika $nama_from_url tidak kosong
         if (!empty($nama_from_url)) {
             $back_params[] = "nama=" . urlencode($nama_from_url);
         }
         // Tambahkan parameter 'notelepon' jika $notelepon_from_url tidak kosong
         if (!empty($notelepon_from_url)) {
             $back_params[] = "notelepon=" . urlencode($notelepon_from_url);
         }


         // Gabungkan base URL dengan parameter
         $back_url = $base_back_url;
         // Tambahkan '?' hanya jika ada parameter
         if (!empty($back_params)) {
             $back_url .= "?" . implode("&", $back_params); // Implode parameters dengan '&'
         }
        ?>
        <!-- Tombol Kembali ke Menu -->
         <!-- Attribute href menggunakan URL yang sudah disusun dari parameter URL KASIR.PHP -->
        <a href="<?php echo htmlspecialchars($back_url); ?>" class="btn bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded text-sm">
            Kembali ke Menu
        </a>
    </div>


    <!-- ===================================================================== -->
    <!-- --- SCRIPT JAVASCRIPT untuk AJAX Request dan Interaksi UI --- -->
    <!-- ===================================================================== -->
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // Ambil referensi elemen
        const cancelBtn = document.getElementById('cancel-order-btn');
        const checkStatusBtn = document.getElementById('check-status-btn');
        const printBtn = document.getElementById('print-receipt-btn');
        const statusDisplay = document.getElementById('statusDisplay');

        // Jalankan JS logika AJAX hanya jika elemen utama untuk fungsionalitas Order ID ada
        // Ini true hanya jika $is_order_id_valid di PHP adalah true, karena tombol render berdasarkan itu
        if (cancelBtn && checkStatusBtn && printBtn && statusDisplay) {

            // Ambil Order ID dari data attribute pada salah satu tombol aksi
            const orderId = cancelBtn.dataset.orderId;
            // Redundansi check (meski PHP di atas harusnya mencegah ini)
            if (!orderId) {
                console.error('JavaScript Error: Order ID data attribute is missing from button.');
                updateStatusDisplay('Kesalahan internal: Order ID tidak ditemukan pada elemen halaman.', 'danger');
                 // Nonaktifkan semua tombol aksi jika Order ID tidak tersedia di JS
                 cancelBtn.disabled = true; checkStatusBtn.disabled = true; printBtn.disabled = true; printBtn.classList.add('hidden');
                return; // Hentikan eksekusi sisa script JS ini
            }

            // URL endpoint API (file kasir.php itu sendiri)
            const API_URL = 'kasir.php';

            // --- Fungsi Bantu: Update tampilan area status (teks & warna) ---
            function updateStatusDisplay(message, type) {
                statusDisplay.innerHTML = message;
                // Hapus semua kelas warna styling yang mungkin sudah ada sebelumnya
                statusDisplay.classList.remove(
                    'bg-blue-100', 'text-blue-800',    // info
                    'bg-green-100', 'text-green-800',  // success
                    'bg-red-100', 'text-red-800',      // danger
                    'bg-yellow-100', 'text-yellow-800',// warning
                    'bg-gray-100', 'text-gray-800'     // default
                );

                // Tambahkan kelas warna berdasarkan tipe pesan (info, success, danger, warning, default)
                switch (type) {
                    case 'info':     statusDisplay.classList.add('bg-blue-100', 'text-blue-800'); break;
                    case 'success':  statusDisplay.classList.add('bg-green-100', 'text-green-800'); break;
                    case 'danger':   statusDisplay.classList.add('bg-red-100', 'text-red-800'); break;
                    case 'warning':  statusDisplay.classList.add('bg-yellow-100', 'text-yellow-800'); break;
                    default:         statusDisplay.classList.add('bg-gray-100', 'text-gray-800'); break; // Warna abu-abu jika type tidak spesifik
                }
            }

             // --- Fungsi Bantu: Mengatur state (disabled/visibility) tombol berdasarkan status pesanan ---
             function updateButtonStates(currentStatus) {
                  // Selalu aktifkan tombol Cek Status (kecuali saat fetch berlangsung - ini dihandle di fetch logic)
                 checkStatusBtn.disabled = false; // Set default ke enabled, lalu fetch akan matikan sementara

                  // Set state default untuk tombol aksi (disabled, dan sembunyikan print)
                 cancelBtn.disabled = true;
                 printBtn.disabled = true;
                 printBtn.classList.add('hidden'); // Sembunyikan tombol print


                  // Normalisasi status dari API menjadi lowercase untuk perbandingan yang konsisten
                  const status = currentStatus ? currentStatus.toLowerCase() : '';

                  // Logika pengaturan state tombol berdasarkan status pesanan
                  if (status === 'pending') {
                      // Jika status 'pending', user bisa membatalkan
                      cancelBtn.disabled = false; // Aktifkan tombol batal
                      cancelBtn.innerText = 'Batalkan Pesanan'; // Pastikan teksnya benar
                  } else if (status === 'diproses' || status === 'selesai') {
                       // Jika status 'diproses' atau 'selesai', user tidak bisa membatalkan lagi.
                       // Tapi mereka bisa mencetak struk.
                      cancelBtn.innerText = 'Tidak Dapat Dibatalkan'; // Nonaktifkan tombol batal, ganti teksnya
                      // Aktifkan dan tampilkan tombol cetak struk
                      printBtn.disabled = false;
                      printBtn.classList.remove('hidden'); // Hilangkan kelas 'hidden'
                      printBtn.innerText = 'Cetak Struk'; // Pastikan teks tombol cetak

                       // Untuk status 'selesai', bisa ada teks spesifik di tombol batal jika perlu
                       if (status === 'selesai') {
                            cancelBtn.innerText = 'Sudah Selesai';
                       }

                  } else if (status === 'dibatalkan') {
                       // Jika status 'dibatalkan', tombol batal sudah tidak relevan. Print juga tidak relevan.
                       cancelBtn.innerText = 'Dibatalkan'; // Nonaktifkan batal, ganti teks
                       printBtn.classList.add('hidden'); // Sembunyikan cetak
                       // printBtn.disabled = true; // sudah diset default di atas
                  } else {
                       // Jika status tidak dikenali, nonaktifkan tombol aksi kecuali 'Cek Status'.
                       cancelBtn.innerText = 'Status Tidak Dikenal'; // Nonaktifkan batal, ganti teks
                       printBtn.classList.add('hidden'); // Sembunyikan cetak
                       // printBtn.disabled = true; // sudah diset default di atas
                  }
                  // Catatan: checkStatusBtn di-disabled/enabled di logic fetch itu sendiri
             }


            // --- Function untuk Cek Status Pesanan via API (Melakukan fetch POST ke kasir.php sendiri) ---
            function checkOrderStatus() {
                updateStatusDisplay('Mengecek status pesanan...', 'info'); // Tampilkan pesan loading dengan styling 'info'

                // Nonaktifkan tombol aksi sementara fetch API berjalan untuk mencegah multiple requests
                 cancelBtn.disabled = true; // Nonaktifkan batal
                 checkStatusBtn.disabled = true; // Nonaktifkan cek status sendiri
                 printBtn.disabled = true; printBtn.classList.add('hidden'); // Sembunyikan print

                fetch(API_URL, { // Kirim request POST ke kasir.php
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }, // Informasikan server body adalah JSON
                    body: JSON.stringify({ action: 'getStatus', order_id: orderId }) // Kirim aksi dan Order ID dalam format JSON
                })
                // Proses response dari server
                .then(response => {
                    // Periksa status HTTP response. Jika bukan 2xx OK, lemparkan error.
                    if (!response.ok) {
                         // Jika error HTTP (400, 500, dll), coba baca body respons sebagai teks
                         return response.text().then(text => {
                              // Log detail error di console browser
                              console.error('HTTP error on check status:', response.status, response.statusText, text);
                              // Tampilkan pesan error komunikasi di UI dengan detail dari respons mentah (limit 150 chars)
                               updateStatusDisplay(`Error ${response.status}: ${response.statusText}. Respons: ${text.substring(0, 150)}...<br>Silakan cek tab Network > Response di Developer Tools.`, 'danger'); // Styling 'danger' (merah)

                               // Reset state tombol dasar setelah error HTTP
                               checkStatusBtn.disabled = false; // Tombol cek status bisa dicoba lagi
                               // Cek apakah Order ID valid di PHP saat halaman pertama dimuat untuk memutuskan state tombol batal
                               if ('<?php echo $is_order_id_valid ? "true" : "false"; ?>' === 'true') {
                                   cancelBtn.disabled = false; // Aktifkan batal jika error HTTP (asumsi error sementara, bisa dicoba lagi)
                                   cancelBtn.innerText = 'Batalkan Pesanan'; // Reset teks batal
                                } else {
                                    // Kondisi ini seharusnya jarang terjadi jika tombol render dengan benar hanya untuk ID valid
                                    cancelBtn.disabled = true;
                                     cancelBtn.innerText = 'Pesanan Tidak Valid';
                                }
                                // Print tetap nonaktif dan hidden jika ada error komunikasi
                                printBtn.disabled = true; printBtn.classList.add('hidden');

                              // Lemparkan error agar ditangkap oleh blok .catch() di bawah
                              throw new Error(`HTTP error ${response.status} on status check`);
                         });
                    }
                    // Jika status HTTP OK (2xx), coba parse body respons sebagai JSON.
                    // Jika body respons BUKAN JSON, ini akan melempar SyntaxError dan ditangkap di .catch().
                    return response.json();
                })
                // Proses data JSON yang berhasil di-parse
                .then(data => {
                    console.log('Check Status API Response:', data); // Log data JSON respons dari server

                    // Cek properti 'success' dari respons JSON (ini logika server PHP)
                    if (data.success) {
                        // Server melaporkan sukses (success: true)
                        const status = data.current_status; // Ambil nilai status pesanan dari data

                        // Tentukan pesan yang akan ditampilkan di area status berdasarkan status dari API
                         if (status === 'pending') {
                              // Pesan untuk status 'pending' dengan format list/bullet
                              statusMessage = `<ul><li>Silakan Lakukan Pembayaran di Kasir.</li><li>Tunjukkan Nomor Pesanan ini kepada Kasir.</li></ul>`; // Menggunakan UL/LI
                              updateStatusDisplay(statusMessage, 'warning'); // Styling 'warning' (kuning)

                         } else if (status === 'diproses') {
                              // Pesan untuk status 'diproses'
                              statusMessage = `<strong>Pembayaran telah selesai</strong>.<br>Pesanan sedang disiapkan.<br>Struk dapat dicetak.`; // Styling 'success'
                              updateStatusDisplay(statusMessage, 'success');

                         } else if (status === 'selesai') {
                              // Pesan untuk status 'selesai'
                              statusMessage = `Pesanan telah <strong>SELESAI dan lunas</strong>.<br>Terima kasih atas kunjungan Anda!`; // Styling 'success'
                              updateStatusDisplay(statusMessage, 'success');

                         } else if (status === 'dibatalkan') {
                              // Pesan untuk status 'dibatalkan'
                              statusMessage = `Pesanan ini sudah <strong>DIBATALKAN</strong>.`; // Styling 'danger'
                              updateStatusDisplay(statusMessage, 'danger');

                         } else {
                              // Pesan fallback jika status dari API tidak dikenali
                              const displayStatus = status ? status.toUpperCase() : 'STATUS TIDAK DIKETAHUI';
                              statusMessage = `Status pesanan: ${displayStatus}.<br>Silakan hubungi staf.`;
                              updateStatusDisplay(statusMessage, 'default'); // Styling 'default' (abu-abu)
                         }

                        // Panggil fungsi untuk memperbarui state (enabled/disabled/visibility) tombol berdasarkan status ACTUAL dari API
                        updateButtonStates(status);

                    } else {
                        // Server melaporkan kegagalan logika (success: false)
                        // Contoh: Order ID tidak ditemukan di DB dengan metode kasir, dll.
                        console.error('API Error Check Status:', data.message); // Log pesan error dari server
                        // Tampilkan pesan error dari server di area status
                        updateStatusDisplay(data.message || 'Gagal mendapatkan status pesanan dari server.', 'danger'); // Styling 'danger'

                        // Karena server bilang gagal, set state tombol sesuai kondisi error/invalid.
                        // Biasanya tidak bisa batal/print, tapi Cek Status tetap bisa dicoba lagi.
                         cancelBtn.disabled = true; // Batal dinonaktifkan
                         printBtn.disabled = true; printBtn.classList.add('hidden'); // Print dinonaktifkan
                         cancelBtn.innerText = 'Pesanan Tidak Valid'; // Teks batal

                         checkStatusBtn.disabled = false; // Cek Status bisa dicoba lagi
                    }
                })
                // Tangkap error selama fetch (jaringan, timeout) atau selama .then() (misal SyntaxError karena JSON invalid)
                .catch(error => {
                    // Ini yang akan menangkap error "Unexpected token..." jika respons bukan JSON valid
                    console.error('Fetch or JSON Parse Error Check Status:', error); // Log error

                    let errorMessage = 'Terjadi kesalahan komunikasi atau format data server tidak valid: ';
                    // Cek spesifik apakah error adalah SyntaxError yang mengindikasikan masalah parsing JSON
                    if (error instanceof SyntaxError && error.message.includes('Unexpected token')) {
                         errorMessage += 'Respons server bukan JSON.';
                         // Log peringatan kritis jika kemungkinan PHP output lain sebelum/sebagai ganti JSON
                         console.error("-> Critical Error Likely PHP Output! Check Network tab > Response for raw data.");
                    } else {
                        // Error lainnya (jaringan, dll.)
                        errorMessage += error.message || 'Server tidak merespons.';
                    }
                    errorMessage += "<br>Mohon coba 'Cek Status' kembali atau refresh halaman.<br>Jika berlanjut, hubungi staf restoran."; // Tambahkan petunjuk untuk debugging & user
                    updateStatusDisplay(errorMessage, 'danger'); // Styling 'danger'

                    // Setelah error komunikasi/parse, aktifkan kembali Cek Status.
                    // Aktifkan juga Batalkan JIKA Order ID dianggap valid saat halaman pertama dimuat (menggunakan trick PHP variable inject).
                     checkStatusBtn.disabled = false;
                     // Logika cek validitas Order ID di PHP saat load (untuk re-enabling Batalkan)
                     if ('<?php echo $is_order_id_valid ? "true" : "false"; ?>' === 'true') {
                       cancelBtn.disabled = false; // Batalkan bisa dicoba lagi jika error dianggap temporary
                       cancelBtn.innerText = 'Batalkan Pesanan'; // Reset teks
                    } else {
                         // Ini seharusnya tidak tercapai karena elemen batal tidak dirender jika ID invalid
                         cancelBtn.disabled = true;
                         cancelBtn.innerText = 'Pesanan Tidak Valid';
                    }
                    // Print tetap nonaktif dan hidden
                     printBtn.disabled = true; printBtn.classList.add('hidden');
                });
            } // End checkOrderStatus()


            // --- Function untuk Batalkan Pesanan via API (Melakukan fetch POST) ---
            function cancelOrder() {
                // Tampilkan konfirmasi ke pengguna sebelum membatalkan
                if (!confirm("Anda yakin ingin membatalkan pesanan ini? Aksi ini tidak dapat diulang.")) {
                    return; // Jika user menekan 'Cancel' di dialog konfirmasi, hentikan proses
                }

                updateStatusDisplay('Memproses pembatalan...', 'warning'); // Tampilkan pesan loading dengan styling 'warning'
                // Nonaktifkan tombol aksi sementara proses fetch API berlangsung
                cancelBtn.disabled = true; // Nonaktifkan tombol batal
                checkStatusBtn.disabled = true; // Nonaktifkan tombol cek status
                printBtn.disabled = true; printBtn.classList.add('hidden'); // Nonaktifkan & sembunyikan tombol print

                fetch(API_URL, { // Kirim request POST ke kasir.php
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'cancel', order_id: orderId }) // Kirim aksi 'cancel' dan Order ID
                })
                // Proses response dari server
                .then(response => {
                     // Periksa status HTTP response
                     if (!response.ok) {
                         // Jika error HTTP, coba baca body respons sebagai teks
                         return response.text().then(text => {
                              // Log detail error
                              console.error('HTTP error on cancel order:', response.status, response.statusText, text);
                              // Tampilkan pesan error komunikasi di UI
                              updateStatusDisplay(`Error ${response.status} saat membatalkan: ${response.statusText}. Respons: ${text.substring(0, 150)}...<br>Cek tab Network > Response.`, 'danger');

                              // Reset state tombol dasar setelah error HTTP (Cek Status aktif)
                               checkStatusBtn.disabled = false;
                              // Batal bisa dicoba lagi JIKA Order ID valid di PHP saat load
                              if ('<?php echo $is_order_id_valid ? "true" : "false"; ?>' === 'true') {
                                 cancelBtn.disabled = false; // Aktifkan batal untuk retry
                                 cancelBtn.innerText = 'Batalkan Pesanan'; // Reset teks
                              } else {
                                   // Seharusnya tidak tercapai jika elemen batal render dgn benar
                                  cancelBtn.disabled = true;
                                   cancelBtn.innerText = 'Pesanan Tidak Valid';
                              }
                               // Print tetap nonaktif
                               printBtn.disabled = true; printBtn.classList.add('hidden');
                              throw new Error(`HTTP error ${response.status} on cancel order`); // Lemparkan error
                         });
                     }
                    // Jika status HTTP OK, coba parse body respons sebagai JSON.
                    // Jika body respons BUKAN JSON, akan throw SyntaxError.
                    return response.json();
                })
                // Proses data JSON yang berhasil di-parse
                .then(data => {
                    console.log('Cancel API Response:', data); // Log data JSON

                    // Aktifkan kembali tombol Cek Status setelah proses batal (apapun hasilnya)
                    checkStatusBtn.disabled = false;

                    // Cek properti 'success' dari respons JSON (logika server)
                    if (data.success) {
                        // Pembatalan BERHASIL (server melaporkan success: true)
                        updateStatusDisplay(data.message || 'Pesanan berhasil dibatalkan.', 'success'); // Tampilkan pesan sukses

                        // Panggil fungsi untuk memperbarui state tombol berdasarkan status "dibatalkan"
                         updateButtonStates('dibatalkan');
                         // Opsi: Panggil checkStatus() setelah jeda singkat untuk mendapatkan status terkini dari database
                         // setTimeout(checkOrderStatus, 500);

                    } else {
                        // Pembatalan GAGAL (server melaporkan success: false)
                        // Misal: status order di DB sudah berubah duluan, ID tidak ditemukan, dll.
                         console.error('API Error Cancel:', data.message); // Log pesan error dari server
                         // Tampilkan pesan error dari server di area status
                         updateStatusDisplay(data.message || 'Gagal membatalkan pesanan.', 'danger');

                         // Jika respons error menyertakan 'current_status', gunakan itu untuk mengupdate state tombol
                         if (data.current_status) {
                             updateButtonStates(data.current_status); // Update tombol berdasarkan status saat gagal (status_db saat itu)
                         } else {
                             // Jika respons error TIDAK menyertakan status (kasus error internal server yang tidak spesifik)
                             // Aktifkan tombol Cek Status, Batalkan mungkin disabled (tergantung kondisi), print disabled.
                              checkStatusBtn.disabled = false; // Cek status bisa dicoba lagi
                              // Asumsikan status tidak pending (karena gagal batal) -> disabled batal
                              if ('<?php echo $is_order_id_valid ? "true" : "false"; ?>' === 'true') {
                                 cancelBtn.disabled = true;
                                  // Coba set teks yang menunjukkan tidak bisa batal
                                   cancelBtn.innerText = data.message && data.message.includes('Status') ? 'Tidak Dapat Dibatalkan' : 'Tidak Dapat Dibatalkan?';
                              } else {
                                  cancelBtn.disabled = true; // ID invalid
                                   cancelBtn.innerText = 'Pesanan Tidak Valid';
                              }
                              printBtn.disabled = true; printBtn.classList.add('hidden');
                             // Opsi: Panggil check status untuk menyinkronkan UI
                             // setTimeout(checkOrderStatus, 500);
                         }

                    }
                })
                // Tangkap error selama fetch atau saat parse JSON
                .catch(error => {
                     // Error komunikasi (jaringan) atau parsing JSON yang gagal
                    console.error('Fetch or JSON Parse Error Cancel Order:', error); // Log error
                     let errorMessage = 'Terjadi kesalahan komunikasi saat membatalkan: ';
                    // Cek jika error terkait parsing JSON
                    if (error instanceof SyntaxError && error.message.includes('Unexpected token')) {
                         errorMessage += 'Respons server bukan JSON.';
                         // Log peringatan jika kemungkinan PHP output lain sebelum/sebagai ganti JSON
                          console.error("-> Critical Error Likely PHP Output! Check Network tab > Response for raw data.");
                    } else {
                         // Error lainnya (jaringan, timeout, dll.)
                        errorMessage += error.message || 'Server tidak merespons.';
                    }
                     errorMessage += "<br>Mohon coba 'Cek Status' kembali atau refresh halaman.<br>Jika berlanjut, hubungi staf restoran."; // Petunjuk ke user/debug
                    updateStatusDisplay(errorMessage, 'danger'); // Styling 'danger'

                    // Setelah error komunikasi, aktifkan kembali Cek Status dan Batalkan (jika Order ID valid di PHP load).
                     checkStatusBtn.disabled = false;
                     if ('<?php echo $is_order_id_valid ? "true" : "false"; ?>' === 'true') {
                       cancelBtn.disabled = false; // Aktifkan batal untuk retry
                       cancelBtn.innerText = 'Batalkan Pesanan'; // Reset teks
                    } else {
                         // Ini seharusnya tidak tercapai karena tombol tidak render jika ID invalid
                         cancelBtn.disabled = true;
                          cancelBtn.innerText = 'Pesanan Tidak Valid';
                    }
                     printBtn.disabled = true; printBtn.classList.add('hidden'); // Print tetap nonaktif
                });
            } // End cancelOrder()


            // --- Function untuk Mencetak Struk (Membuka halaman struk di tab baru) ---
             function printReceipt() {
                  const orderIdToPrint = printBtn.dataset.orderId; // Ambil Order ID dari data attribute tombol
                  // Jika Order ID tidak ditemukan di attribute (seharusnya tidak terjadi jika ID valid di PHP load)
                  if (!orderIdToPrint) {
                      console.error('Print Receipt Error: Order ID data attribute is missing.');
                      updateStatusDisplay('Kesalahan internal: Gagal mendapatkan Order ID untuk dicetak.', 'danger');
                      return;
                  }

                  // Logika mencetak struk: Membuka file yang menampilkan struk kasir (misal sukses.php atau struk_kasir.php)
                  // di tab/jendela baru, dan opsional menambahkan parameter untuk memicu print otomatis di halaman target.

                  // !!! PASTIKAN PATH KE FILE STRUK ANDA BENAR RELATIF DARI kasir.php !!!
                  // Contoh: kasir.php di /pages/pelanggan/, file struk (misal sukses.php) di /pages/pelanggan/payment/
                  const receiptUrl = `struk_kasir.php?order_id=${orderIdToPrint}&print=auto`; // <<< SESUAIKAN NAMA SUBFOLDER payment/ DAN FILENYA (misal sukses.php)

                  window.open(receiptUrl, '_blank'); // Buka URL struk di tab baru

                  // Berikan feedback di UI halaman kasir.php
                  // Tambahkan link ke tab/jendela baru untuk membantu user jika tidak muncul otomatis
                   updateStatusDisplay(`Memicu dialog cetak struk untuk Pesanan #${orderIdToPrint}. Mohon periksa tab/jendela baru yang muncul.<br>Jika jendela tidak muncul, izinkan pop-up untuk situs ini.`, 'info');

                   // Opsional: Nonaktifkan tombol cetak sejenak untuk mencegah double click
                  printBtn.disabled = true;
                  printBtn.innerText = 'Mencetak...'; // Ubah teks tombol

                  // Setelah jeda waktu singkat, panggil fungsi checkOrderStatus lagi.
                  // Tujuannya untuk menyegarkan UI (state tombol dan teks) di halaman kasir.php
                  // agar kembali ke keadaan stabil (misal: tombol print kembali aktif dengan teks "Cetak Struk").
                   setTimeout(() => {
                       // checkOrderStatus akan mem-fetch status dari DB dan memanggil updateButtonStates()
                       checkOrderStatus();
                   }, 2500); // Delay 2.5 detik (opsional, bisa disesuaikan)

             } // End printReceipt()


            // --- Tambahkan Event Listeners ke tombol ---
            checkStatusBtn.addEventListener('click', checkOrderStatus);
            // Hanya tambahkan event listeners untuk Batalkan dan Cetak jika elemen tombolnya *ditemukan* di DOM.
            // Elemen-elemen ini hanya dirender oleh PHP jika Order ID dari URL GET valid ($is_order_id_valid = true).
            if(cancelBtn) {
                 cancelBtn.addEventListener('click', cancelOrder);
            } else {
                 // Log jika tombol tidak ditemukan (seharusnya karena Order ID di URL invalid saat load)
                 console.warn("Cancel button element not found.");
            }
            if(printBtn) {
                 printBtn.addEventListener('click', printReceipt);
            } else {
                 // Log jika tombol tidak ditemukan
                 console.warn("Print button element not found.");
            }


            // Otomatis cek status saat halaman dimuat (setelah semua elemen DOM siap).
            // Ini memberikan feedback awal tentang status pesanan saat customer pertama kali membuka link kasir.
            checkOrderStatus();

        } else {
             // Ini blok kode yang dieksekusi JIKA kondisi `if (cancelBtn && checkStatusBtn && printBtn && statusDisplay)` adalah FALSE.
             // Ini terjadi jika Order ID dari URL GET saat halaman dimuat adalah INVALID, sehingga PHP tidak merender
             // div `payment-box` beserta tombol-tombol dan area status display di dalamnya.
             // Kita bisa menambahkan log ke console browser untuk debugging.
            console.warn("Main payment box elements are not available. Likely due to invalid Order ID provided in the URL upon page load.");
        }

        // Catatan: Tombol "Kembali ke Menu" memiliki href statis/php-generated dan tidak membutuhkan JS listener di sini.

    }); // Akhir dari event listener 'DOMContentLoaded'
    </script>

</body>
</html>
<?php
// === TUTUP KONEKSI DATABASE ===
// Tutup koneksi database jika berhasil dibuka di awal script dan belum ditutup di blok POST request (karena POST request melakukan exit()).
// Check ini penting untuk rilis koneksi DB pada request GET yang berhasil render.
// Pastikan variabel $con ada, adalah objek mysqli yang valid, dan koneksi awalnya berhasil.
if (isset($con) && $con instanceof mysqli && !$con->connect_error) {
    $con->close();
}

// === Peringatan Akhir Tag PHP ===
// Dalam file campuran HTML+PHP, tag penutup 
?>