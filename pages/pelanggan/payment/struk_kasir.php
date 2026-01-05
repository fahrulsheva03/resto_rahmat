<?php
// sukses.php - Halaman Struk/Detail Pesanan & Handler Midtrans Success Callback View
// Script ini berfungsi:
// 1. Menampilkan detail pesanan dengan gaya struk kasir/struk pembayaran, berdasarkan order_id dari URL.
// 2. Menerima parameter URL status (Midtrans callback view atau custom) untuk menampilkan pesan banner.
// 3. Mengupdate status pesanan di DB menjadi 'diproses' JIKA diakses dari Midtrans success callback view URL
//    DAN status order di DB masih 'pending', metode bayar 'midtrans'.
// 4. Berfungsi juga sebagai halaman cetak struk yang bisa diakses langsung (misal dari kasir.php).
// 5. Menggunakan tata letak berbasis tabel untuk info struk, daftar item, summary, dan payment info.
// 6. Menampilkan gambar thumbnail item menu.

// session_start(); // Aktifkan jika diperlukan. Jika hanya untuk menampilkan struk, mungkin tidak perlu.
                    // PENTING: Jika diaktifkan, pastikan ini adalah BARIS PERTAMA SEBELUM OUTPUT APAPUN!

// Set timezone (penting untuk format waktu yang akurat)
date_default_timezone_set('Asia/Jakarta');

// --- Konfigurasi Tampilkan Error (DEBUGGING ONLY) ---
// AKTIFKAN ini SAAT DEBUGGING untuk melihat error PHP, MATIKAN SAAT PRODUKSI (beri komentar).
ini_set('display_errors', 1);
error_reporting(E_ALL);
// Pastikan TIDAK ADA spasi atau baris kosong SEBELUM tag pembuka <?php
// =WAJIB MATIKAN DI LINGKUNGAN PRODUKSI =========================================


// Include file koneksi database. Variabel koneksi $con HARUS sudah ada dan valid setelah include.
// !!! PATH INI SANGAT PENTING DAN HARUS BENAR RELATIF terhadap lokasi file sukses.php Anda. !!!
// Hitung jumlah level folder ke atas untuk mencapai folder tempat 'config.php' berada.
// Contoh path jika sukses.php di /pages/pelanggan/payment/ dan config.php di folder root: '../../../config.php'
// Contoh path jika sukses.php di /pages/pelanggan/payment/ dan config.php di folder /pages/: '../../config.php'
$host = "localhost";
$user = "root";
$pass = "";
$db = "restoran";

$con = mysqli_connect($host, $user, $pass, $db);
if (!$con) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// Periksa koneksi database segera setelah include. Jika $con tidak valid, catat error.
if (!isset($con) || !$con instanceof mysqli || $con->connect_error) {
    $error_message_db_conn = "Koneksi database gagal: " . (isset($con->connect_error) ? $con->connect_error : 'Koneksi object tidak valid.');
    error_log("sukses.php DB Connect failed: " . $error_message_db_conn); // Log error di server side
    // Variabel $con_ok akan digunakan di FASE 1 & FASE 2 untuk cek apakah DB siap
    $con_ok = false;
} else {
     $con_ok = true; // Koneksi database OK
}


$order_id = null; // Valid order ID (integer)
$order_details = null; // Array yang akan menyimpan semua data pesanan & detail jika berhasil diambil
$order_id_clean_or_raw_html = null; // Untuk pesan error jika Order ID di URL invalid formatnya


// --- Helper functions ---

// Format angka menjadi mata uang Rupiah (Rp) dengan titik sebagai ribuan pemisah
function format_rp($amount) {
    // Memastikan input adalah angka float sebelum formatting
    $amount = (float)$amount;
    // number_format(angka, desimal, pemisah_desimal, pemisah_ribuan)
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

// Format bagian dari timestamp (tanggal atau jam)
function format_timestamp_part($timestamp, $part = 'date') {
     // Mengembalikan string default jika timestamp kosong, null, atau format default '0000-00-00 00:00:00'
     if (empty($timestamp) || $timestamp === '0000-00-00 00:00:00') {
         return '-';
     }
     // Mengonversi timestamp (string) ke objek DateTime untuk formatting yang aman
     try {
         $dateObj = new DateTime($timestamp);
         if ($part === 'date') {
            return $dateObj->format('Y-m-d'); // Contoh: 2025-05-02
         } elseif ($part === 'time') {
            return $dateObj->format('H:i:s'); // Contoh: 04:16:28
         } else {
             // Jika 'part' tidak spesifik (misal 'date' atau 'time'), kembalikan format timestamp asli dari DB
             // Atau format default DateTime: $dateObj->format('Y-m-d H:i:s');
             return $timestamp; // Mengembalikan string asli jika format lainnya
         }
     } catch (Exception $e) {
         // Menangkap error jika timestamp tidak valid formatnya dan gagal diparse
         error_log("sukses.php: Failed to parse timestamp '" . $timestamp . "': " . $e->getMessage()); // Log error
         return 'Format Waktu Error'; // Pesan error di UI
     }
}

// Menghasilkan tag HTML <img> untuk gambar item menu
function get_item_image_tag($image_filename) {
    // Mengembalikan string kosong jika nama file gambar kosong atau null
    if (empty($image_filename)) {
        return ''; // Tidak ada gambar untuk ditampilkan
    }

     // !!! SANGAT PENTING: SESUAIKAN PATH KE FOLDER GAMBAR MENU ANDA !!!
     // Hitung path relatif dari lokasi file sukses.php Anda ke folder yang berisi gambar menu.
     // Contoh: sukses.php di /pages/pelanggan/payment/, gambar di /admin/assets/images/products/
     // Path relatif dari sukses.php ke /admin/ adalah 4 level ke atas (../../../..) lalu turun ke admin/assets/images/products/
     $image_folder_base_path = '../../../../admin/assets/images/products/'; // <<< SESUAIKAN PATH INI DENGAN BENAR !!!

     $full_image_src = $image_folder_base_path . $image_filename;

     // Menambahkan class 'item-thumb' untuk styling
     return '<img src="' . htmlspecialchars($full_image_src) . '" alt="' . htmlspecialchars($image_filename) . '" class="item-thumb">';
     // Styling 'item-thumb' (lebar, tinggi, object-fit) diatur di CSS
}


// ================================================================
// --- FASE 1: AMBIL order_id DARI URL DAN FETCH DATA PESANAN LENGKAP DARI DATABASE ---

// Periksa apakah parameter 'order_id' ada di URL GET ($_GET)
if (isset($_GET['order_id']) && !empty($_GET['order_id'])) {
    $order_id_raw = $_GET['order_id']; // Ambil nilai string asli dari URL
    // Htmlspecialchars nilai mentah untuk display di pesan error jika formatnya invalid
    $order_id_clean_or_raw_html = htmlspecialchars($order_id_raw);

    // Sanitasi input order_id: hanya menyisakan digit angka
    $order_id_sanitized = filter_var($order_id_raw, FILTER_SANITIZE_NUMBER_INT);

    // Validasi order_id yang sudah disanitasi: harus berupa angka dan lebih besar dari 0
    if (is_numeric($order_id_sanitized) && (int)$order_id_sanitized > 0) {
         // Jika valid, simpan sebagai integer di variabel $order_id
         $order_id = (int)$order_id_sanitized;

         // === JALANKAN QUERY DATABASE UNTUK MENGAMBIL DETAIL PESANAN ===
        // Gunakan prepared statement untuk mencegah SQL Injection.
        // NAMA TABEL DAN KOLOM HARUS TEPAT SESUAI DENGAN STRUKTUR DB ANDA DAN QUERY YANG DISEDIAKAN.
        // Kolom `id_pelanggan` harus ada di tabel `pesanan` sebagai FK ke `pelanggan`.
        $sql = "
            SELECT
                p.id_pesanan,
                p.waktu_pesan,              -- Asumsikan nama kolom timestamp adalah 'waktu_pesan'
                p.id_meja AS meja,          -- Asumsikan kolom FK meja adalah 'id_meja', alias 'meja'
                pl.nama AS nama_pemesan,    -- Kolom 'nama' dari tabel pelanggan (alias 'nama_pemesan')
                pl.notelepon,               -- Kolom 'notelepon' dari tabel pelanggan
                p.total_harga,              -- Kolom total harga di pesanan
                p.metode_pembayaran,        -- Kolom metode pembayaran
                p.status AS order_status,   -- Kolom status di pesanan (alias 'order_status')
                dp.id_detail,               -- ID detail item (untuk urutan)
                dp.jumlah,                  -- Kuantitas per item di detail_pesanan
                dp.harga_satuan,            -- Harga satuan per item di detail_pesanan (saat order)
                dp.subtotal,                -- Subtotal per baris item di detail_pesanan
                m.nama_menu,                -- Nama menu dari tabel menu
                m.gambar                    -- Nama file gambar dari tabel menu
            FROM
                pesanan p                                   -- Join pesanan (alias p)
            JOIN
                detail_pesanan dp ON p.id_pesanan = dp.id_pesanan -- Join detail_pesanan (alias dp)
            JOIN
                menu m ON dp.id_menu = m.id_menu          -- Join menu (alias m)
            JOIN
                pelanggan pl ON p.id_pelanggan = pl.id_pelanggan -- Join pelanggan (alias pl)
            WHERE
                p.id_pesanan = ? -- Filter berdasarkan ID pesanan
            ORDER BY
                dp.id_detail ASC -- Urutkan detail item (berdasarkan ID atau urutan yang relevan)
        ";

         // Hanya eksekusi query jika koneksi DB berhasil ($con_ok adalah true)
        if ($con_ok) {
            $stmt = $con->prepare($sql); // Coba prepare statement

            // Periksa jika prepared statement berhasil dibuat (tidak ada sintaks error, nama tabel/kolom salah, dll)
            if ($stmt) {
                $stmt->bind_param("i", $order_id); // Bind parameter order_id (integer)
                $execute_success = $stmt->execute(); // Jalankan query ke database
                $result = $stmt->get_result(); // Ambil hasil query

                // Periksa jika query execute sukses DAN ada baris data yang ditemukan (num_rows > 0)
                if ($execute_success && $result && $result->num_rows > 0) {
                    $header_set = false; // Flag untuk menandai apakah data header sudah diambil (hanya perlu 1x)
                    $total_qty_calculated = 0; // Untuk menghitung total kuantitas semua item

                    // Loop melalui setiap baris hasil query (setiap baris mewakili satu item pesanan karena JOIN)
                    while ($row = $result->fetch_assoc()) {
                        // --- Memproses data dari SETIAP baris hasil JOIN ---

                        // Ambil data HEADER (dilakukan HANYA SEKALI menggunakan data dari baris PERTAMA hasil query)
                        if (!$header_set) {
                            $order_details['header'] = [
                                'id_pesanan' => $row['id_pesanan'],
                                'waktu_pesan' => $row['waktu_pesan'],
                                'meja' => $row['meja'] ?? '-', // Ambil dari alias 'meja'
                                'nama_pemesan' => $row['nama_pemesan'] ?? '-', // Ambil dari alias 'nama_pemesan'
                                'notelepon' => $row['notelepon'] ?? '-', // Ambil dari kolom 'notelepon'
                                'total_harga' => (float)($row['total_harga'] ?? 0), // Pastikan float & default 0
                                'metode_pembayaran' => $row['metode_pembayaran'] ?? 'N/A',
                                'status_db' => $row['order_status'] ?? 'Tidak Diketahui', // Ambil status dari alias 'order_status'
                                 // Tambahkan kolom header lain jika diambil di SELECT query, misal total_kuantitas_db (jika ada kolomnya)
                            ];
                            $header_set = true; // Tandai header sudah diambil agar tidak diproses lagi di iterasi berikutnya
                        }

                        // Simpan data DETAIL item ke dalam array 'items' (dilakukan untuk SETIAP baris)
                        $order_details['items'][] = [
                            'id_detail' => $row['id_detail'], // ID detail item
                            'nama_menu' => $row['nama_menu'] ?? 'Menu Tidak Dikenal', // Nama menu dari tabel menu
                            'jumlah' => (int)($row['jumlah'] ?? 0),          // Kuantitas (integer)
                            'harga_satuan' => (float)($row['harga_satuan'] ?? 0), // Harga satuan (float)
                            'subtotal' => (float)($row['subtotal'] ?? 0),      // Subtotal baris item (float)
                            'gambar' => $row['gambar'] ?? '',                // Nama file gambar
                            // Tambahkan kolom detail lain jika diambil di SELECT query (misal 'catatan' per item)
                            // 'catatan' => $row['catatan'] ?? '' // Asumsi ada kolom 'catatan' di detail_pesanan
                        ];

                        $total_qty_calculated += (int)($row['jumlah'] ?? 0); // Tambahkan kuantitas ke total (hitung manual jika tidak ada di DB header)
                    }
                     // Setelah loop selesai, simpan total kuantitas yang dihitung (jika tidak ada di header DB)
                     // Jika total_kuantitas sudah ada di header DB (diambil di SELECT query) gunakan itu
                     // Di query yang Anda berikan, total_kuantitas TIDAK ada. Jadi hitung manual.
                     $order_details['header']['total_qty'] = $total_qty_calculated;


                } elseif ($execute_success && $result && $result->num_rows == 0) {
                    // Query execute sukses, tapi mengembalikan 0 baris.
                    // Ini terjadi jika Order ID valid (format angka positif), tapi TIDAK ditemukan di database DENGAN DETAIL ITEMNYA.
                     // (bisa karena order_id salah, order dihapus, atau order ada tapi somehow detailnya kosong/deleted).
                     $order_id = null; // Setel Order ID jadi null lagi untuk mengaktifkan pesan error di HTML
                    // Atur pesan error spesifik yang akan ditampilkan di div error HTML
                    $error_message_display = "Pesanan dengan Order ID #{$order_id_clean_or_raw_html} tidak ditemukan di database atau tidak memiliki detail item.";
                     echo "<script>console.warn('Order ID {$order_id_sanitized} resulted in 0 rows. Not found or empty details.');</script>";

                } else { // Query execution failed (error saat menjalankan statement)
                     // Log error ke server side
                     error_log("sukses.php: SELECT query execution failed for Order ID {$order_id} : " . $stmt->error);
                     $order_id = null; // Setel Order ID jadi null untuk mengaktifkan pesan error di HTML
                     $error_message_display = "Gagal mengeksekusi query database untuk detail pesanan. Error: " . htmlspecialchars($stmt->error); // Pesan error untuk UI

                }
                $stmt->close(); // Penting: Tutup statement SETELAH SELESAI digunakan

            } else { // Gagal prepare statement (Sintaks query salah, nama tabel/kolom salah, dll.)
                // Log error ke server side
                 error_log("sukses.php: Failed to prepare SELECT statement: " . $con->error . " SQL: " . $sql);
                 $order_id = null; // Setel Order ID jadi null
                $error_message_display = "Gagal menyiapkan query database. Mohon hubungi staf. Error Prepare: " . htmlspecialchars($con->error);
            }
        } else {
            // Koneksi database GAGAL di awal script, query tidak pernah dijalankan.
            $order_id = null; // Setel Order ID jadi null
             // error_message_db_conn sudah diset di awal saat koneksi gagal.
             $error_message_display = "Gagal terhubung ke database: " . htmlspecialchars($error_message_db_conn);
        }

    } else { // Order ID dari URL TIDAK VALID formatnya (bukan angka positif)
        $order_id = null; // Order ID tetap null
        // Atur pesan error spesifik yang akan ditampilkan di div error HTML
        $error_message_display = "Format Order ID '{$order_id_clean_or_raw_html}' dari URL tidak valid. Order ID harus berupa angka positif.";
        echo "<script>console.warn('Invalid order_id parameter format received: " . $order_id_raw . "');</script>";
    }
} else { // Parameter 'order_id' TIDAK ADA di URL sama sekali
     $order_id = null; // Order ID tetap null
     // Atur pesan error spesifik
     $error_message_display = "Parameter Order ID tidak ditemukan di URL. Tidak dapat menampilkan detail pesanan.";
     echo "<script>console.warn('Order ID parameter is missing from the URL.');</script>";
}
// --- AKHIR FASE 1 ---
// ================================================================


// ================================================================
// --- FASE 2: LOGIKA UPDATE STATUS DATABASE (HANYA untuk Midtrans Success Callback View) ---
// Update status order di DB menjadi 'diproses' HANYA JIKA memenuhi kriteria callback Midtrans sukses:
// - Ada data order_details yang berhasil diambil
// - Parameter `status` di URL GET adalah 'success' (Midtrans success redirect signal)
// - Metode pembayaran order adalah 'midtrans' (dari data DB)
// - Status order di DB saat ini masih 'pending' (dari data DB sebelum potensi update ini)
// Status order LAIN (kasir, manual update admin, dll.) JANGAN DIUPDATE DI SINI.

if (
    isset($order_details) && // Pastikan data order ditemukan dan diambil
    isset($_GET['status']) && $_GET['status'] === 'success' && // Pastikan parameter URL `status=success` ada
    isset($order_details['header']['metode_pembayaran']) && strtolower($order_details['header']['metode_pembayaran']) === 'midtrans' && // Pastikan metode bayar Midtrans (case-insensitive)
    isset($order_details['header']['status_db']) && strtolower($order_details['header']['status_db']) === 'pending' // Pastikan status DB saat ini masih pending (case-insensitive)
) {
    $newStatusForDb = 'diproses'; // Status baru yang akan di-set di database
    $currentStatusRequired = 'pending'; // Syarat status saat ini di DB agar update berjalan

     // Cek kembali koneksi DB valid sebelum UPDATE
    if ($con_ok) {
        // Prepared statement untuk UPDATE
        // UPDATE pesanan menjadi 'diproses' HANYA JIKA ID, status='pending', DAN metode='midtrans' cocok.
        // WHERE clause penting untuk memastikan update yang tepat dan mencegah unintended updates.
        $stmtUpdate = $con->prepare("UPDATE pesanan SET status = ? WHERE id_pesanan = ? AND status = ? AND metode_pembayaran = 'midtrans'");

        // Cek jika prepared statement UPDATE berhasil dibuat
        if ($stmtUpdate) {
            // Bind parameter: s (string, new status), i (integer, order ID), s (string, required current status)
            $stmtUpdate->bind_param("sis", $newStatusForDb, $order_id, $currentStatusRequired);

            if ($stmtUpdate->execute()) { // Jalankan query UPDATE ke database
                // Cek jumlah baris yang terpengaruh oleh UPDATE
                if ($stmtUpdate->affected_rows > 0) {
                    // Update status di database BERHASIL (status berubah dari 'pending' ke 'diproses')
                    echo "<script>console.log('sukses.php: DB status updated successfully to \\'{$newStatusForDb}\\' for order {$order_id} (Midtrans success callback).');</script>";
                    // PERBARUI status_db di array $order_details agar tampilan struk mencerminkan status BARU ini
                    $order_details['header']['status_db'] = $newStatusForDb;
                } else {
                    // Execute sukses tapi 0 rows affected. Ini berarti kondisi WHERE (id, status=pending, metode=midtrans) tidak sepenuhnya cocok.
                    // Paling umum: status DB ternyata sudah berubah duluan (misal, dari admin).
                    echo "<script>console.warn('sukses.php: DB status update attempted for order {$order_id}, but no rows affected (status might not be pending anymore or method is not midtrans?).');</script>";
                    // Dalam kasus 0 affected rows, data $order_details['header']['status_db'] tetap sesuai dengan hasil SELECT FASE 1, yang akurat.
                }
            } else { // Terjadi error saat mengeksekusi UPDATE query
                error_log("sukses.php: DB status update execution failed for order {$order_id}: " . $stmtUpdate->error);
                // UI akan tetap menampilkan status dari SELECT awal jika update gagal.
            }
            $stmtUpdate->close(); // Penting: Tutup statement UPDATE

        } else { // Gagal prepare UPDATE statement
             error_log("sukses.php: DB status update prepare failed: " . $con->error);
            // UI akan tetap menampilkan status dari SELECT awal.
        }
    } else {
         // Koneksi DB gagal, update tidak bisa dilakukan. error_log sudah di FASE 1.
    }
}
// --- AKHIR FASE 2 ---
// ================================================================


// ================================================================
// --- FASE 3: MENENTUKAN PESAN BANNER STATUS LAYAR (Opsional) ---
// Pesan dan warna banner besar yang ditampilkan di bagian atas struk HANYA di layar (tidak saat print).
// Banner ini terutama relevan untuk memberikan feedback instan dari redirect pembayaran online atau custom status URL.

$show_status_banner = false; // Default: banner status tidak ditampilkan

if (isset($_GET['status'])) { // Jika ada parameter 'status' di URL GET (misal dari Midtrans redirect, atau custom link)
    $status_param_url = $_GET['status'];
    $show_status_banner = true; // Tampilkan banner karena ada parameter status di URL

    switch ($status_param_url) {
        // Mapping parameter URL 'status' ke pesan dan class CSS Bootstrap alerts
        case 'success': $payment_status_banner_message = 'Pembayaran Online BERHASIL!'; $status_banner_class = 'alert-success'; break; // Green
        case 'pending': $payment_status_banner_message = 'Pembayaran Online Menunggu Konfirmasi. Mohon Ikuti Instruksi Pembayaran Anda.'; $status_banner_class = 'alert-warning'; break; // Yellow/Warning
        case 'challenge': $payment_status_banner_message = 'Pembayaran Online Memerlukan Verifikasi Tambahan (Challenge). Mohon Periksa Detail Transaksi Anda.'; $status_banner_class = 'alert-warning'; break; // Yellow/Warning
        case 'failed': $payment_status_banner_message = 'Pembayaran Online Gagal. Silakan coba metode lain atau bayar di kasir.'; $status_banner_class = 'alert-danger'; break; // Red/Danger
        case 'canceled': $payment_status_banner_message = 'Anda Menutup Jendela Pembayaran Online. Pesanan menunggu Anda lanjutkan bayar atau hubungi staf.'; $status_banner_class = 'alert-warning'; break; // Yellow/Warning
        case 'deny': $payment_status_banner_message = 'Transaksi Online Ditolak Sistem Pembayaran. Silakan coba metode lain atau bayar di kasir.'; $status_banner_class = 'alert-danger'; break; // Red/Danger
        case 'expired': $payment_status_banner_message = 'Transaksi Pembayaran Online Telah Kadaluarsa.'; $status_banner_class = 'alert-danger'; break; // Red/Danger

         // Case untuk status URL yang mengindikasikan non-online payment atau info umum (contoh dari kasir.php)
         // Anda bisa menambahkan case lain jika ada custom status parameter URL
        case 'kasir': // Misal link dari kasir.php pakai ?status=kasir
             $payment_status_banner_message = 'Ini adalah Struk Informasi Pesanan.'; // Pesan lebih umum
             $status_banner_class = 'alert-info'; // Biru/Info
             break;
        case 'created': // Status di Midtrans webhook (jika tidak redirect langsung success, tapi perlu notifikasi dulu)
             $payment_status_banner_message = 'Pesanan Anda Berhasil Dibuat dan Menunggu Pembayaran Online.';
             $status_banner_class = 'alert-info';
             break;


        default:
            // Jika ada parameter 'status' tapi nilainya tidak dikenal
            $payment_status_banner_message = 'Status Pembayaran Tidak Dikenal: ' . htmlspecialchars($status_param_url);
            $status_banner_class = 'alert-secondary'; // Abu-abu (info umum)
            break;
    }

} // Jika tidak ada parameter 'status' di URL, maka banner status tidak akan ditampilkan (show_status_banner tetap false).
// UI hanya akan menampilkan struk dengan data status dari DB.

// --- Menentukan status yang ditampilkan di bagian INFO STRUK itu sendiri ---
// Ini diambil LANGSUNG dari hasil DB, setelah potensi update di FASE 2.
$status_in_struk_db = isset($order_details['header']['status_db']) ? $order_details['header']['status_db'] : 'N/A';
// Untuk tampilan yang user-friendly di dalam tabel info struk: konversi underscore ke spasi & capitalize
$display_status_in_struk = ucwords(str_replace('_', ' ', $status_in_struk_db));


// --- AKHIR FASE 3 ---
// ================================================================


?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Pesanan - <?php echo ($order_details && $order_id !== null) ? 'Order #' . htmlspecialchars($order_details['header']['id_pesanan']) : 'Error'; ?></title>

    <!-- Sertakan file Bootstrap CSS untuk alert dan button (sesuaikan path) -->
    <!-- Pastikan PATH INI BENAR RELATIF dari lokasi sukses.php Anda! -->
     <!-- Contoh: sukses.php di /pages/pelanggan/payment/, Bootstrap CSS di /admin/assets/bootstrap-5.3.0/css/ -->
    <link href="../../../admin/assets/bootstrap-5.3.0/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">


    <style>
        /* ============================================================== */
        /* --- STYLE CSS untuk tampilan seperti struk kasir --- */

        body {
            background-color: #f8f9fa; /* Warna background agak lebih terang */
            color: #212529; /* Teks dark */
            font-family: 'Consolas', 'Courier New', monospace; /* Font thermal printer */
            padding: 10px;
            line-height: 1.4;
            display: flex;
            flex-direction: column; /* Column layout */
            align-items: center; /* Center horizontally */
            min-height: 100vh;
            overflow-y: auto; /* Allow scroll */
             gap: 15px; /* Jarak antar elemen di body (banner, container struk, dll) */
        }

        /* Gaya untuk banner status (alert Bootstrap) */
        .status-message-display {
            margin: 0 auto; /* Auto margins to center */
            padding: 12px 20px;
            border-radius: 5px;
            font-weight: bold;
            word-wrap: break-word;
            width: 100%; /* Take full width available in flex container */
            max-width: 380px; /* Max width consistent with struk-container */
            text-align: center;
             font-size: 0.95em;
        }

        /* Container utama untuk seluruh konten struk */
        .struk-container {
            width: 350px; /* Preferred thermal width */
            max-width: 95%; /* Responsive */
            background-color: #fff;
            padding: 20px 15px;
            border: 1px solid #ddd;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin: 0 auto; /* Center */

             /* Flexbox for internal layout */
             display: flex;
             flex-direction: column;
             align-items: stretch; /* Children fill width */
             gap: 15px; /* Jarak antar blok di dalam struk */
        }

        /* Default spacing for blocks inside struk-container */
        .shop-info, .struk-info, .item-list, .summary, .payment-info, .thanks, .error-message {
             /* margin-bottom controlled by gap in .struk-container */
             width: 100%;
        }

        .shop-info { text-align: center; }

        .shop-logo {
             max-width: 100px;
             height: auto;
             margin-bottom: 10px; /* Jarak di bawah logo */
             display: block;
             margin-left: auto; margin-right: auto; /* Center logo */
        }

        .shop-info h3 { margin: 0; font-size: 1.6em; font-weight: bold; word-wrap: break-word; }
        .shop-info p { margin: 2px 0; font-size: 0.9em; word-wrap: break-word; }

        .separator {
             border-bottom: 1px dashed #000; /* Garis putus-putus */
             margin: 15px 0; /* Jarak atas/bawah */
             width: 100%;
        }


        /* --- Gaya Tabel dalam Struk --- */
        /* Default table styles */
        .struk-info table, .item-list table, .summary table, .payment-info table {
             width: 100%;
             border-collapse: collapse;
             margin: 0; /* Reset default margin */
             font-size: 0.95em; /* Default font size for tables */
        }

        /* Default cell styles in tables */
        .struk-info table td, .item-list table td, .summary table td, .payment-info table td {
             padding: 4px 0;
             border: none;
             vertical-align: top;
             text-align: left;
             word-wrap: break-word;
        }

        /* Info Table Layout (Nomor Order, Date, Time, etc) */
        .struk-info table td:first-child { width: 110px; padding-right: 8px; font-weight: normal; }
        .struk-info table td:last-child { text-align: right; }
        /* Special row for Order ID */
        .struk-info .order-id-row td { font-weight: bold; }
        .struk-info .order-id-row td:last-child { font-size: 1.1em; }


        /* Item List Table Layout */
         .item-list { font-size: 0.9em; } /* Font size sedikit lebih kecil untuk item list */
         .item-list table td { padding: 6px 0; } /* Lebih banyak padding antar item */

         /* Item details structure using nested flexbox within TD */
         .item-details-content { display: flex; align-items: flex-start; gap: 8px; width: 100%;} /* Ensure width 100% in TD */

         /* Image thumbnail style */
         .item-thumb {
             width: 40px; height: 40px; object-fit: cover;
             border: 1px solid #eee; border-radius: 4px;
             flex-shrink: 0; /* Prevent shrinking */
             display: block; /* Take up own line */
         }
         /* Text part of item details (name and qty*price) */
         .item-text-details { flex-grow: 1; word-break: break-word;}

         /* Columns in Item List Table */
         .item-list .col-item-number-image { width: 50px; padding-right: 8px; } /* Penomoran & Gambar */
         .item-list .col-item-details { flex-grow: 1; padding-right: 8px;} /* Nama & Qty x Price (grow) */
         .item-list .col-item-price { width: 80px; text-align: right; font-weight: bold; flex-shrink: 0;} /* Subtotal item (fixed width) */

         /* Style for item name and qty x price text elements */
        .item-name { font-weight: bold; display: block; line-height: 1.2; margin-bottom: 2px;}
        .item-qty-price { font-size: 0.9em; color: #555; font-weight: normal; line-height: 1.2; }


        /* Summary Table Layout */
        .summary { font-size: 1em; } /* Default font size */
        .summary table td { padding: 5px 0;} /* More padding for summary rows */

        .summary .label-col, .payment-info .label-col { width: 110px; padding-right: 8px; font-weight: normal; } /* Labels column */
        .summary .value-col, .payment-info .value-col { text-align: right; font-weight: normal; } /* Values column */

         /* Total Row styling */
        .summary .total-row td {
             font-size: 1.3em;
             font-weight: bold;
              padding-top: 8px;
              border-top: 1px dashed #000; /* Garis putus di atas TOTAL */
        }
         .summary .total-row .label-col { font-weight: bold;}


        /* Payment Info Table Layout */
        .payment-info { font-size: 0.95em; } /* Font size sedikit kecil */
        .payment-info table td { padding: 5px 0; }


        /* Thank you message */
        .thanks { text-align: center; font-size: 1em; margin-top: 20px; font-weight: bold; word-wrap: break-word; line-height: 1.4; }

         /* Button Area styling (Screen only) */
         .button-area {
            text-align: center;
            margin-top: 25px;
             padding-top: 15px;
             border-top: 1px solid #eee;
             width: 100%;
             max-width: 350px; /* Match struk container width */
             margin-left: auto; margin-right: auto; /* Center */
             display: flex; /* Use flex to arrange buttons */
             justify-content: center; /* Center buttons horizontally */
             gap: 10px; /* Spacing between buttons */
         }
         .button-area .btn { /* Style Bootstrap buttons */
            font-size: 0.9em;
             padding: 8px 15px;
         }

         /* Gaya khusus untuk pesan error dalam container struk jika data tidak ditemukan */
         /* error-message div used when $order_details is null */


        /* === @media print styles === */
        /* Gaya khusus untuk cetak di printer thermal */
         @media print {
            /* Body Print Style: Reset styles */
            body {
                background-color: #fff !important;
                color: #000 !important;
                justify-content: flex-start !important;
                align-items: flex-start !important;
                padding: 0 !important;
                margin: 0 !important;
                 /* Optional: Set paper size for thermal printer (adjust if needed) */
                 /* @page { size: 80mm auto; margin: 0mm; } */
                 width: auto !important; max-width: none !important; /* Allow width to be controlled by printer/container */
                 min-height: auto !important;
                 overflow: visible !important;
                 line-height: 1.2 !important; /* Rapat */
                 font-size: 9pt !important; /* Base print font size */
                 font-family: 'Consolas', 'Courier New', monospace !important;
                 display: block !important; /* Body becomes block */
            }

            /* Struk Container Print Style: Reset and set thermal size */
            .struk-container {
                width: 80mm !important; /* Target thermal printer width */
                max-width: 80mm !important;
                min-width: 80mm !important; /* Ensure minimum width too */
                box-shadow: none !important;
                border: none !important;
                margin: 0 !important; /* No margin, should be controlled by @page if set */
                padding: 5mm 3mm !important; /* Padding inside struk */
                 display: block !important; /* Back to block */
                 page-break-after: auto !important; /* Auto page breaks if content spills */
                 page-break-inside: avoid !important; /* Try to avoid breaking container */
            }

            /* Hide elements only for screen display */
             .status-message-display, .button-area {
                 display: none !important;
             }

            /* Adjust margins and borders for blocks when printing */
            .shop-info, .struk-info, .item-list, .summary, .payment-info, .thanks, .error-message {
                 margin-bottom: 3mm !important; /* Reduced spacing between blocks */
             }
             .separator {
                border-bottom: 1px dashed #000 !important;
                margin: 3mm 0 !important; /* Spacing around separators */
             }

             /* Font sizes for print (adjust as needed for readability on thermal) */
             .shop-info h3 { font-size: 12pt !important; }
             .shop-info p { font-size: 8pt !important; }
             .struk-info, .item-list table, .summary table, .payment-info table { font-size: 9pt !important; } /* Main content tables */
             .thanks { font-size: 10pt !important; margin-top: 5mm !important; }


            /* TABLE Cell Print Style */
             .struk-info table td, .item-list table td, .summary table td, .payment-info table td {
                 padding: 2px 0 !important; /* Less vertical padding */
                 word-wrap: break-word !important; /* Ensure text wraps */
                 word-break: break-all !important; /* Break words if necessary to fit width */
             }


             /* Specific Table Column Widths for Print */
              /* Info table */
             .struk-info table td:first-child { width: 40% !important; padding-right: 3mm !important; font-weight: bold !important;} /* Labels bold and specific width */
              .struk-info table td:last-child { width: 60% !important; text-align: right !important; }

              /* Item list table columns (Image/Num, Details, Price) */
              .item-list .col-item-number-image { width: 15% !important; padding-right: 3mm !important;}
              .item-list .col-item-details { width: 50% !important; padding-right: 3mm !important; }
              .item-list .col-item-price { width: 35% !important; text-align: right !important; font-weight: bold !important;}


               /* Item Details Content (Flexbox inside TD for Print) */
               .item-details-content { display: flex !important; gap: 3mm !important; align-items: flex-start !important; width: auto !important;}
               .item-thumb {
                 width: 10mm !important; height: auto !important; object-fit: cover !important;
                 border: none !important; border-radius: 0 !important;
                 flex-shrink: 0 !important; display: block !important;
              }
               .item-text-details { flex-grow: 1 !important; word-break: break-word !important; }
                .item-name { font-weight: bold !important; margin-bottom: 1px !important; line-height: 1.1 !important; font-size: 9pt !important;}
                .item-qty-price { font-size: 8pt !important; line-height: 1.1 !important; color: #000 !important; font-weight: normal !important; }

              /* Summary & Payment Table columns */
             .summary .label-col, .payment-info .label-col { width: 60% !important; font-weight: normal !important;}
             .summary .value-col, .payment-info .value-col { width: 40% !important; text-align: right !important; }

              /* Total Row Print */
             .summary .total-row td { font-size: 10pt !important; font-weight: bold !important; padding-top: 3px !important; border-top: 1px dashed #000 !important;}


             /* Penomoran item - Ensure visibility */
              .item-list .item-counter { font-weight: bold !important; display: inline-block !important;}


         }
        /* --- AKHIR STYLE CSS --- */
        /* ============================================================== */
    </style>
</head>
<body>

    <?php if ($show_status_banner): // Tampilkan banner status jika flag true ?>
         <!-- Banner Pesan Status (Alert Bootstrap) -->
         <div class="status-message-display alert <?php echo htmlspecialchars($status_banner_class); ?>" role="alert">
             <?php echo nl2br(htmlspecialchars($payment_status_banner_message)); // nl2br agar newline dari PHP tampil sbg <br> ?>
         </div>
     <?php endif; ?>

    <div class="struk-container">

        <?php
         // === CEK UNTUK MENAMPILKAN ERROR ATAU STRUK ===
         // Tampilkan pesan error JIKA koneksi DB gagal, ATAU jika data order tidak berhasil diambil ($order_details null)
        if (!$con_ok || !isset($order_details) || $order_details === null ): ?>
             <!-- Menampilkan pesan error -->
             <div class="error-message">
                 <div class="alert alert-danger text-center" role="alert" style="width: 100%; margin: auto 0; padding: 15px;">
                    <strong>Error!</strong> Detail pesanan tidak dapat ditampilkan.
                     <br>
                     <?php
                         // Prioritaskan pesan error koneksi DB jika ada
                         if (!$con_ok) {
                              echo nl2br(htmlspecialchars($error_message_db_conn));
                         } else {
                              // Jika DB OK, tampilkan pesan error dari proses fetch data ($error_message_display)
                              echo nl2br(htmlspecialchars($error_message_display ?? 'Terjadi kesalahan yang tidak diketahui saat mengambil data.')); // Fallback message
                         }
                         // Tambahkan informasi Order ID yang diterima (raw) jika ada di URL
                         if (isset($order_id_clean_or_raw_html)) {
                             echo "<br>ID di URL: \"{$order_id_clean_or_raw_html}\"";
                         }
                     ?>
                 </div>
             </div>

         <?php else: // Jika data order berhasil diambil ($order_details is not null) ?>
             <!-- === KONTEN STRUK PESANAN (jika berhasil fetch data) === -->

            <!-- Shop Info (Header Statis Restoran - MOHON GANTI TEKS INI!) -->
            <div class="shop-info">
                 <!-- Gambar Logo Resto (PASTIHKAN PATHNYA BENAR!) -->
                 <?php
                    // Path ke file logo restoran Anda relatif dari lokasi file sukses.php.
                    // Contoh: sukses.php di /pages/pelanggan/payment/, logo di /admin/assets/images/logo-rika.svg
                    $logo_path_relative = '../../../admin/assets/images/logo-rika.svg'; // <<< SESUAIKAN PATH LOGO ANDA !!!

                    // Cek apakah file logo ada. Jika path salah atau file tidak ada, tag <img> tidak akan dicetak.
                    if (file_exists($logo_path_relative)) {
                         // Menggunakan fungsi get_item_image_tag sebagai contoh jika sudah diubah utk general images
                         // Atau buat fungsi helper baru: function get_logo_tag($path) { ... }
                         echo '<img src="' . htmlspecialchars($logo_path_relative) . '" alt="Logo Resto" class="shop-logo">';
                    } else {
                         // Jika logo tidak ditemukan, log error server dan tampilkan nama restoran saja.
                          error_log("sukses.php: Restaurant logo file not found at path: " . $logo_path_relative);
                         // Tampilkan nama restoran sebagai fallback
                          echo '<h3 style="font-size: 1.8em; margin-bottom: 5px;">' . htmlspecialchars("resto rahmat") . '</h3>'; // <<< GANTI NAMA RESTORAN
                    }
                 ?>
                <h3 style="font-weight: bold;"><?php echo htmlspecialchars("resto rahmat"); ?></h3> <!-- Nama Restoran Anda -->
                <p><?php echo htmlspecialchars("Jl. Jalan jalan Saja"); ?></p> <!-- Alamat Lengkap -->
                <p><?php echo htmlspecialchars("Makassar, 12345"); ?></p> <!-- Kota, Kode Pos -->
                <p>Telp: <?php echo htmlspecialchars("085213976352"); ?></p> <!-- Nomor Telepon -->
                <!-- Info tambahan lainnya di header jika perlu -->
                 <!-- <p>Instagram: @restorika</p> -->
            </div>

            <div class="separator"></div> <!-- Garis pemisah -->

            <!-- Info Pesanan (Nomor Order, Tanggal, Jam, Meja, Pemesan, Status DB) Menggunakan TABEL -->
            <div class="struk-info">
                 <table>
                    <tbody>
                         <tr>
                             <td>Tanggal:</td><td><?php echo htmlspecialchars(format_timestamp_part($order_details['header']['waktu_pesan'], 'date')); ?></td>
                         </tr>
                         <tr>
                             <td>Jam:</td><td><?php echo htmlspecialchars(format_timestamp_part($order_details['header']['waktu_pesan'], 'time')); ?></td>
                         </tr>
                         <tr class="order-id-row">
                             <td>Nomor Order:</td><td>#<?php echo htmlspecialchars($order_details['header']['id_pesanan']); ?></td>
                         </tr>
                         <tr>
                             <td>Meja:</td><td><?php echo htmlspecialchars($order_details['header']['meja']); // Menggunakan data dari DB ?></td>
                         </tr>
                          <tr>
                             <td>Pemesan:</td><td><?php echo htmlspecialchars($order_details['header']['nama_pemesan']); ?></td>
                         </tr>
                         <tr>
                             <td>Telepon:</td><td><?php echo htmlspecialchars($order_details['header']['notelepon']); ?></td>
                         </tr>
                          <!-- Menampilkan Status Order dari DB dalam Info Struk (Sesuai Contoh Gambar) -->
                           <!-- Ini BUKAN banner status besar, tapi baris info di dalam struk -->
                         <tr>
                              <td>Status Order:</td><td><?php echo htmlspecialchars($display_status_in_struk); ?></td>
                         </tr>

                    </tbody>
                </table> <!-- Akhir tabel struk info -->
            </div> <!-- Akhir div struk-info -->


            <div class="separator"></div> <!-- Garis pemisah -->


            <!-- Detail Item Pesanan Menggunakan TABEL -->
            <div class="item-list">
                 <table>
                    <tbody>
                         <?php if (isset($order_details['items']) && !empty($order_details['items'])): ?>
                             <?php $item_counter = 1; // Penomoran item ?>
                            <?php foreach ($order_details['items'] as $item): ?>
                                <!-- Setiap baris item pesanan -->
                                <tr>
                                    <!-- Kolom KIRI: Penomoran + Gambar Thumbnail -->
                                     <td class="col-item-number-image">
                                          <span class="item-counter"><?php echo $item_counter++; ?>.</span>
                                          <?php // Tag gambar dicetak di baris terpisah (di bawah nomor) dalam sel yg sama ?>
                                     </td>
                                    <!-- Kolom TENGAH: Nama Item dan Qty x Harga Satuan -->
                                    <td class="col-item-details">
                                        <!-- Gunakan div flex di sini untuk mengatur gambar & teks nama/qty disampingan -->
                                        <div class="item-details-content">
                                            <div class="item-text-details">
                                                 <span class="item-name"><?php echo htmlspecialchars($item['nama_menu']); ?></span><br>
                                                 <span class="item-qty-price">
                                                     <?php echo htmlspecialchars($item['jumlah']); ?> × <?php echo format_rp($item['harga_satuan']); ?>
                                                 </span>
                                            </div>
                                        </div>
                                    </td>
                                    <!-- Kolom KANAN: Subtotal Item -->
                                    <td class="col-item-price">
                                        <?php echo format_rp($item['subtotal']); ?>
                                    </td>
                                </tr>
                                 <?php
                                 // --- OPSIONAL: Tambahkan baris catatan di bawah item jika ada kolom 'catatan' di detail_pesanan ---
                                 // Ini membutuhkan: 1. Menambah kolom 'catatan' di tabel detail_pesanan.
                                 //                2. Menambah 'dp.catatan' di SELECT query FASE 1.
                                 //                3. Menambah 'catatan' ke array $order_details['items'].
                                 // Lalu di sini, Anda bisa mencetak baris baru di dalam tbody:
                                 /*
                                 if (isset($item['catatan']) && !empty($item['catatan'])) {
                                     echo '<tr class="item-note-row"><td colspan="3" style="padding-left: 30px; font-style:italic; font-size:0.9em;">Note: ' . htmlspecialchars($item['catatan']) . '</td></tr>';
                                 }
                                 */
                                 ?>
                            <?php endforeach; ?>
                         <?php else: // Jika array item kosong atau tidak ditemukan detail pesanan di DB ?>
                             <tr><td colspan="3" class="text-center text-muted" style="font-style: italic;">-- Tidak ada item dalam pesanan ini --</td></tr>
                         <?php endif; ?>
                    </tbody>
                 </table> <!-- Akhir tabel item list -->
            </div> <!-- Akhir div item-list -->


            <div class="separator"></div> <!-- Garis pemisah -->


            <!-- Summary (Total Quantity, Subtotal, Total Keseluruhan) Menggunakan TABEL -->
            <div class="summary">
                <table>
                    <tbody>
                         <!-- Tampilkan total kuantitas dari perhitungan FASE 1 -->
                         <tr>
                             <td class="label-col">Total Qty:</td>
                             <td class="value-col"><?php echo htmlspecialchars($order_details['header']['total_qty']); ?></td>
                         </tr>
                          <!-- Subtotal -->
                          <!-- Di contoh gambar Anda, "Subtotal" nilainya sama dengan "TOTAL". -->
                          <!-- Jika total_harga di tabel 'pesanan' adalah subtotal (sebelum pajak/diskon) bisa ditampilkan di sini. -->
                          <!-- Jika total_harga adalah final total, mungkin ini bisa ditampilkan di sini dan baris 'TOTAL' dihitung lain (misal, total_bayar - pajak) -->
                          <!-- Berdasarkan query, 'total_harga' adalah yang final. Gunakan 'total_harga' dari header DB. -->
                         <tr>
                              <td class="label-col">Subtotal:</td>
                              <td class="value-col"><?php echo format_rp($order_details['header']['total_harga']); ?></td>
                         </tr>
                          <!-- Baris Total Harga Keseluruhan -->
                         <tr class="total-row">
                             <td class="label-col">TOTAL:</td>
                             <!-- Menggunakan kolom total_harga dari tabel pesanan -->
                             <td class="value-col"><?php echo format_rp($order_details['header']['total_harga']); ?></td>
                         </tr>
                         <!-- Tambahkan baris Diskon, Pajak, Layanan jika ada kolomnya di DB pesanan & diambil di SELECT FASE 1 -->
                          <?php
                             // Contoh (perlu menambah kolom di SELECT query FASE 1 dan di $order_details['header']):
                             // if (isset($order_details['header']['diskon_amount'])) {
                             //    echo '<tr><td class="label-col">Diskon:</td><td class="value-col">- ' . format_rp($order_details['header']['diskon_amount']) . '</td></tr>';
                             // }
                             // if (isset($order_details['header']['tax_amount'])) {
                             //     echo '<tr><td class="label-col">Pajak:</td><td class="value-col">+ ' . format_rp($order_details['header']['tax_amount']) . '</td></tr>';
                             // }
                          ?>
                    </tbody>
                 </table> <!-- Akhir tabel summary -->
            </div> <!-- Akhir div summary -->


             <!-- Informasi Pembayaran (Metode Bayar) Menggunakan TABEL -->
             <div class="payment-info">
                 <table>
                     <tbody>
                         <!-- Metode Pembayaran dari header pesanan -->
                          <tr>
                              <td class="label-col">Metode Bayar:</td>
                              <!-- Mengubah underscores "_" menjadi spasi dan meng-capitalize setiap kata -->
                              <td class="value-col"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $order_details['header']['metode_pembayaran']))); ?></td>
                          </tr>
                          <!-- Baris Bayar (jumlah dibayar) dan Kembali (kembalian) jika ada data/kolomnya di DB -->
                          <?php
                             // Ini memerlukan kolom seperti 'jumlah_dibayar' atau 'cash_paid' di tabel 'pesanan'
                             // dan harus diambil di SELECT query FASE 1 serta ditampung di $order_details['header'].
                             // Contoh (jika ada kolom 'jumlah_dibayar'):
                             // if (isset($order_details['header']['jumlah_dibayar'])) {
                             //    $jumlah_dibayar = (float)($order_details['header']['jumlah_dibayar'] ?? 0);
                             //    $total_yang_dibayar = (float)($order_details['header']['total_harga'] ?? 0); // Assuming total_harga is the amount paid against
                             //    // Asumsi jika 'jumlah_dibayar' ada, itu untuk cash payment, hitung kembalian
                             //    $kembalian = $jumlah_dibayar > $total_yang_dibayar ? $jumlah_dibayar - $total_yang_dibayar : 0;
                             //    echo '<tr><td class="label-col">Bayar:</td><td class="value-col">' . format_rp($jumlah_dibayar) . '</td></tr>';
                             //    echo '<tr><td class="label-col">Kembali:</td><td class="value-col">' . format_rp($kembalian) . '</td></tr>';
                             // }
                          ?>
                     </tbody>
                 </table> <!-- Akhir tabel payment info -->
             </div> <!-- Akhir div payment-info -->


             <div class="separator"></div> <!-- Garis pemisah -->


            <!-- Pesan Terima Kasih (Statis - GANTI TEKS INI!) -->
            <div class="thanks">
                 <p><?php echo htmlspecialchars("Terimakasih Telah Berbelanja"); ?></p> <!-- Pesan Akhir Struk -->
                 <!-- Opsional: Info tambahan seperti website, akun medsos, slogan, atau waktu print/buka halaman -->
                 <!-- <p style="font-size:0.8em; margin-top:5px;">Follow us @restorika di Instagram!</p> -->
                 <!-- <p style="font-size:0.7em; margin-top:8px;"><?php echo date('Y-m-d H:i:s'); // Waktu saat halaman dimuat ?></p> -->
            </div>


        <?php endif; // Akhir dari block ELSE ($order_details is null -> error) dan IF ($order_details -> show struk) ?>


    </div> <!-- Akhir struk-container -->


    <!-- Area Tombol (Kembali & Print) - Muncul di LAYAR SAJA, sembunyikan di print media CSS -->
    <!-- Tombol ini selalu muncul terlepas dari error atau sukses menampilkan struk -->
    <div class="button-area">
         <?php
         // === Persiapan Link Kembali ke Menu ===
         // Mengambil data dari $order_details jika ada, untuk parameter URL kembali.
         // Jika $order_details null (error saat fetch), gunakan nilai default kosong ''.
         $back_meja = isset($order_details['header']['meja']) ? htmlspecialchars($order_details['header']['meja']) : '';
         $back_nama = isset($order_details['header']['nama_pemesan']) ? htmlspecialchars($order_details['header']['nama_pemesan']) : '';
         $back_notelepon = isset($order_details['header']['notelepon']) ? htmlspecialchars($order_details['header']['notelepon']) : '';

         // !!! SANGAT PENTING: SESUAIKAN PATH kembali ke index.php ini !!!
         // Hitung path relatif dari lokasi file sukses.php Anda ke file index.php halaman menu utama.
         // Contoh: sukses.php di /pages/pelanggan/payment/, index.php di /pages/
         $base_back_url = "../index.php"; // <<< SESUAIKAN PATH KEMBALI INI !!!

         // Bentuk string parameter URL jika nilai tidak kosong
         $back_params = [];
         if (!empty($back_meja)) { $back_params[] = "meja=" . urlencode($back_meja); } // urlencode() penting untuk nilai parameter URL
         if (!empty($back_nama)) { $back_params[] = "nama=" . urlencode($back_nama); }
         if (!empty($back_notelepon)) { $back_params[] = "notelepon=" . urlencode($back_notelepon); }

         // Gabungkan base URL dengan parameter, tambahkan '?' hanya jika ada parameter
         $back_url = $base_back_url;
         if (!empty($back_params)) {
             $back_url .= "?" . implode("&", $back_params); // Implode parameters with '&'
         }
         ?>
        <!-- Tombol Kembali (Menggunakan kelas Bootstrap .btn dan .btn-secondary) -->
        <!-- Attribute href menggunakan URL yang sudah disusun dari data pesanan/default -->
         <a href="<?php echo $back_url; ?>" class="btn btn-secondary btn-sm">Kembali</a>

         <?php if (isset($order_details)): // Tampilkan tombol Print HANYA jika data pesanan berhasil ditampilkan ?>
            <!-- Tombol Cetak Struk (Menggunakan kelas Bootstrap .btn dan .btn-primary) -->
            <!-- Atribut 'onclick' sederhana langsung memanggil fungsi print bawaan browser -->
            <button class="btn btn-primary btn-sm" onclick="window.print()">Cetak Struk</button>
         <?php endif; ?>
    </div> <!-- Akhir button-area -->


    <!-- Sertakan file Bootstrap JS (tidak wajib jika hanya butuh fungsionalitas print) -->
    <!-- Jika tidak membutuhkan fitur JS Bootstrap lain, bisa dihapus -->
    <!-- Pastikan PATH INI BENAR RELATIF dari lokasi sukses.php Anda! -->
     <!-- Contoh: sukses.php di /pages/pelanggan/payment/, Bootstrap JS di /admin/assets/bootstrap-5.3.0/js/ -->
    <script src="../../../admin/assets/bootstrap-5.3.0/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigldG/sdsWaHVrLCw7Qb9DXgDA9i" crossorigin="anonymous"></script>
    <!-- Kode JS lain jika dibutuhkan (saat ini hanya logging opsional) -->


    <!-- Kode JS untuk memicu print otomatis jika ada parameter URL khusus (optional) -->
     <script>
        // Mengambil parameter URL dari window.location
        const urlParams = new URLSearchParams(window.location.search);
        // Cek apakah ada parameter 'print' dengan nilai 'auto' di URL (misal: sukses.php?...&print=auto)
        const autoPrint = urlParams.get('print') === 'auto';

        // Tambahkan event listener ke DOMContentLoaded untuk memastikan elemen HTML sudah ada
        document.addEventListener('DOMContentLoaded', function() {
            // Mencari tombol cetak berdasarkan kelas atau ID
            const printButton = document.querySelector('.button-area .btn-primary');

            // Log untuk debugging di console browser
            if (typeof console === 'object' && typeof console.log === 'function') {
                 console.log('sukses.php client-side script loaded.');
                 <?php // Echo log PHP variables to JS console (OPTIONAL - comment out in PRODUCTION) ?>
                 // console.log('Order ID from URL (sanitized PHP var):', <?php echo json_encode($order_id); ?>); // Will be null if invalid/missing
                 // console.log('Raw/Clean Order ID from URL (for display):', '<?php echo $order_id_clean_or_raw_html; ?>');
                 // console.log('Connection Status (PHP var):', '<?php echo $con_ok ? "OK" : "Failed"; ?>');
                 <?php if (isset($order_details)): ?>
                     // console.log('Order Details Fetched (PHP):', <?php echo json_encode($order_details['header']); ?>); // Log only header for brevity
                 <?php else: ?>
                     // console.log('Order Details Fetched (PHP):', 'No data found/fetched.');
                     // console.error('Error Message (PHP var):', '<?php echo htmlspecialchars($error_message_display ?? $error_message_db_conn ?? "N/A"); ?>');
                 <?php endif; ?>
                 // console.log('URL GET Params (PHP var):', <?php echo json_encode($_GET); ?>);
                 // console.log('Show Status Banner (PHP var):', '<?php echo $show_status_banner ? "true" : "false"; ?>');
                 // console.log('Status Banner Class (PHP var):', '<?php echo htmlspecialchars($status_banner_class); ?>');


            }

             // == Logika Print Otomatis ==
             // Cek apakah pemicu auto-print diaktifkan via URL DAN tombol cetak ditemukan di halaman.
            if (autoPrint && printButton) {
                 console.log('Auto print triggered by URL parameter.');
                 // Menggunakan window.onload di dalam DOMContentLoaded untuk menunggu semua resource termasuk gambar dimuat,
                 // kemudian panggil print() setelah jeda singkat. Ini penting untuk hasil cetak yang lengkap.
                 window.onload = function() {
                     setTimeout(function() {
                          console.log('Calling window.print() after slight delay.');
                          window.print();
                     }, 500); // Delay 500ms
                 }
            }

        });
    </script>

</body>
</html>
<?php
// === TUTUP KONEKSI DATABASE ===
// Tutup koneksi database jika berhasil dibuka di awal script dan belum ditutup.
// Pastikan $con ada, valid objek mysqli, dan koneksinya berhasil sebelum ditutup.
if (isset($con) && $con instanceof mysqli && !$con->connect_error) {
    $con->close();
}

?>