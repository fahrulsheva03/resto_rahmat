<?php
// sukses.php - Menampilkan Struk Pesanan & Update Status Payment Gateway

// --- DEBUGGING (MATIKAN DI PRODUKSI!) ---
ini_set('display_errors', 1);
error_reporting(E_ALL);
// ----------------------------------------

date_default_timezone_set('Asia/Jakarta');

// --- Koneksi Database ---
// Ganti dengan kredensial Anda atau include config.php
$host = "localhost";
$user = "root";
$pass = "";
$db = "restoran";

$con = mysqli_connect($host, $user, $pass, $db);

// Cek koneksi DB
$con_ok = true;
if (!$con) {
    $error_message_db_conn = "Koneksi database gagal: " . mysqli_connect_error();
    error_log("sukses.php DB Connect failed: " . $error_message_db_conn);
    $con_ok = false;
}

$order_id = null; // ID Pesanan yang valid
$order_details = null; // Detail Pesanan dari DB
$error_message_display = null; // Pesan error untuk UI
$order_id_clean_or_raw_html = null; // ID dari URL (HTML-encoded)

// --- Helper functions ---

// Format Rupiah
function format_rp($amount) {
    $amount = (float)$amount;
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

// Format Tanggal atau Jam dari Timestamp
function format_timestamp_part($timestamp, $part = 'date') {
     if (empty($timestamp) || $timestamp === '0000-00-00 00:00:00') return '-';
     try { $dateObj = new DateTime($timestamp); return $part === 'date' ? $dateObj->format('Y-m-d') : ($part === 'time' ? $dateObj->format('H:i:s') : $timestamp); }
     catch (Exception $e) { error_log("sukses.php: Failed to parse timestamp: " . $timestamp . ". Error: " . $e->getMessage()); return 'Error Waktu'; }
}

// Get Tag Gambar Item Menu
function get_item_image_tag($image_filename) {
    if (empty($image_filename)) return '';
}


// ================================================================
// --- FASE 1: Ambil Order ID & Fetch Data Pesanan ---

if (isset($_GET['order_id']) && !empty($_GET['order_id'])) {
    $order_id_raw = $_GET['order_id'];
    $order_id_sanitized = filter_var($order_id_raw, FILTER_SANITIZE_NUMBER_INT);
    $order_id_clean_or_raw_html = htmlspecialchars($order_id_raw); // Untuk error display

    if (is_numeric($order_id_sanitized) && (int)$order_id_sanitized > 0) {
         $order_id = (int)$order_id_sanitized;

        if ($con_ok) {
            // Query SELECT (SESUAI DENGAN YANG ANDA BERIKAN)
            $sql_select_order = "
                SELECT
                    p.id_pesanan, p.waktu_pesan, p.id_meja AS meja, pl.nama AS nama_pemesan,
                    pl.notelepon, p.total_harga, p.metode_pembayaran, p.status AS order_status,
                    dp.id_detail, dp.jumlah, dp.harga_satuan, dp.subtotal, m.nama_menu, m.gambar
                FROM
                    pesanan p JOIN detail_pesanan dp ON p.id_pesanan = dp.id_pesanan
                JOIN menu m ON dp.id_menu = m.id_menu JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan
                WHERE p.id_pesanan = ?
                ORDER BY dp.id_detail ASC
            ";
            $stmt_select_order = $con->prepare($sql_select_order);

            if ($stmt_select_order) {
                $stmt_select_order->bind_param("i", $order_id);
                $execute_success = $stmt_select_order->execute();
                $result = $stmt_select_order->get_result();

                if ($execute_success && $result && $result->num_rows > 0) {
                    $header_set = false;
                    $total_qty_calculated = 0;

                    while ($row = $result->fetch_assoc()) {
                        if (!$header_set) {
                            $order_details['header'] = [
                                'id_pesanan' => $row['id_pesanan'], 'waktu_pesan' => $row['waktu_pesan'],
                                'meja' => $row['meja'] ?? '-', 'nama_pemesan' => $row['nama_pemesan'] ?? '-',
                                'notelepon' => $row['notelepon'] ?? '-', 'total_harga' => (float)($row['total_harga'] ?? 0),
                                'metode_pembayaran' => $row['metode_pembayaran'] ?? 'N/A', 'status' => $row['order_status'] ?? 'Tidak Diketahui',
                            ];
                            $header_set = true;
                        }
                        $order_details['items'][] = [
                            'id_detail' => $row['id_detail'], 'nama_menu' => $row['nama_menu'] ?? 'Menu Tidak Dikenal',
                            'jumlah' => (int)($row['jumlah'] ?? 0), 'harga_satuan' => (float)($row['harga_satuan'] ?? 0),
                            'subtotal' => (float)($row['subtotal'] ?? 0), 'gambar' => $row['gambar'] ?? '',
                        ];
                        $total_qty_calculated += (int)($row['jumlah'] ?? 0);
                    }
                    $order_details['header']['total_qty'] = $total_qty_calculated;

                } elseif ($execute_success && $result && $result->num_rows == 0) {
                     $order_id = null;
                     $error_message_display = "Pesanan #{$order_id_clean_or_raw_html} tidak ditemukan atau tidak memiliki detail item.";
                } else {
                     error_log("sukses.php: SELECT exec failed for order " . ($order_id ?? 'N/A') . " : " . $stmt_select_order->error);
                     $order_id = null;
                     $error_message_display = "Gagal mengeksekusi query detail pesanan. Error: " . htmlspecialchars($stmt_select_order->error);
                }
                $stmt_select_order->close();
            } else {
                 error_log("sukses.php: Failed to prepare SELECT statement: " . $con->error . " SQL: " . $sql_select_order);
                 $order_id = null;
                 $error_message_display = "Gagal menyiapkan query database. Error Prepare: " . htmlspecialchars($con->error);
            }
        } else {
            $order_id = null;
            $error_message_display = "Gagal terhubung ke database: " . htmlspecialchars($error_message_db_conn ?? 'Unknown DB error.');
        }

    } else {
        $order_id = null;
        $error_message_display = "Format Order ID '{$order_id_clean_or_raw_html}' dari URL tidak valid.";
    }
} else {
    $order_id = null;
    $error_message_display = "Parameter Order ID tidak ditemukan di URL.";
}
// --- END FASE 1 ---


// ================================================================
// --- FASE 2: Handle Payment Gateway Callback & Update Status ---
// Ini dijalankan jika diakses dari Payment Gateway Redirect View.

// Sesuaikan NAMA PARAMETER status sukses/gagal dari Payment Gateway Anda
$payment_gateway_status_param_name = 'payment_status'; // <<<< UBAH NAMA INI JIKA BEDA

if (
    isset($order_details) && // Order details berhasil diambil
    isset($_GET[$payment_gateway_status_param_name]) && $_GET[$payment_gateway_status_param_name] === 'success' && // Parameter status gateway sukses di URL
    isset($order_details['header']['metode_pembayaran']) && strtolower($order_details['header']['metode_pembayaran']) === 'midtrans' && // Metode bayar Midtrans di DB
    isset($order_details['header']['status']) && strtolower($order_details['header']['status']) === 'pending' // Status DB masih pending
) {
    // Kondisi terpenuhi, coba update status di DB
    $newStatusForDb = 'diproses';
    $currentStatusRequired = 'pending';
    $paymentMethodRequired = 'midtrans';

    if ($con_ok) {
        $stmtUpdate = $con->prepare("UPDATE pesanan SET status = ? WHERE id_pesanan = ? AND status = ? AND metode_pembayaran = ?");

        if ($stmtUpdate) {
            $stmtUpdate->bind_param("siss", $newStatusForDb, $order_id, $currentStatusRequired, $paymentMethodRequired);

            if ($stmtUpdate->execute()) {
                if ($stmtUpdate->affected_rows > 0) {
                    // Sukses update di DB!
                    echo "<script>console.log('SUCCESS: DB status updated to \\'{$newStatusForDb}\\' for order {$order_id}. Rows affected: {$stmtUpdate->affected_rows}');</script>";
                    $order_details['header']['status'] = $newStatusForDb; // Update status di array untuk display
                } else {
                    // 0 rows affected (kondisi WHERE tidak cocok - misal status sudah berubah duluan)
                    echo "<script>console.warn('WARNING: UPDATE query executed but 0 rows affected. Order ID: {$order_id}. Status may have changed before.');</script>";
                }
            } else {
                error_log("sukses.php: DB status update exec failed for order {$order_id}: " . $stmtUpdate->error);
                echo "<script>console.error('ERROR: UPDATE execution failed. Error: " . htmlspecialchars($stmtUpdate->error) . "');</script>";
            }
            $stmtUpdate->close();
        } else {
            error_log("sukses.php: DB status update prepare failed: " . $con->error);
            echo "<script>console.error('ERROR: Failed to prepare UPDATE statement. Error: " . htmlspecialchars($con->error) . "');</script>";
        }
    } else {
         // Koneksi DB tidak ok, update tidak jalan.
         echo "<script>console.error('ERROR: DB connection not available for UPDATE.');</script>";
    }
} // End if condition for update

// --- END FASE 2 ---


// ================================================================
// --- FASE 3: Tentukan Pesan Banner & Status Tampilan Struk ---

$show_status_banner = false;
$payment_status_banner_message = '';
$status_banner_class = '';

// Parameter status payment gateway di URL
$payment_gateway_status_param_name = 'payment_status'; // <<<< NAMA PARAMETER

// Prioritaskan pesan banner dari parameter URL
if (isset($_GET[$payment_gateway_status_param_name])) { // <<<< CEK PARAMETER INI!
    $status_param_url = $_GET[$payment_gateway_status_param_name];
    $show_status_banner = true; // Tampilkan banner jika ada param status

    switch ($status_param_url) {
        case 'success': $payment_status_banner_message = 'Pembayaran Online BERHASIL!'; $status_banner_class = 'alert-success'; break; // Hijau
        case 'pending': $payment_status_banner_message = 'Pembayaran Online Menunggu Konfirmasi. Mohon Ikuti Instruksi Pembayaran Anda.'; $status_banner_class = 'alert-warning'; break; // Kuning
        case 'challenge': $payment_status_banner_message = 'Pembayaran Online Memerlukan Verifikasi Tambahan (Challenge). Mohon Periksa Detail Transaksi Anda.'; $status_banner_class = 'alert-warning'; break; // Kuning
        case 'failed': $payment_status_banner_message = 'Pembayaran Online Gagal. Silakan coba metode lain atau bayar di kasir.'; $status_banner_class = 'alert-danger'; break; // Merah
        case 'canceled': $payment_status_banner_message = 'Anda Menutup Jendela Pembayaran Online. Jika Ingin Melanjutkan Bayar Online, Hubungi Staf.'; $status_banner_class = 'alert-warning'; break; // Kuning
        case 'deny': $payment_status_banner_message = 'Transaksi Online Ditolak Sistem Pembayaran.'; $status_banner_class = 'alert-danger'; break; // Merah
        case 'expired': $payment_status_banner_message = 'Transaksi Pembayaran Online Telah Kadaluarsa.'; $status_banner_class = 'alert-danger'; break; // Merah

        default:
            $payment_status_banner_message = 'Status Pembayaran Tidak Dikenal: ' . htmlspecialchars($status_param_url);
            $status_banner_class = 'alert-secondary'; // Abu-abu
            break;
    }
} /*
   // Jika tidak ada parameter payment_status di URL, tapi ada parameter status umum, pakai itu untuk banner
   // Contoh: Jika link dari kasir.php pakai ?status=kasir
   elseif (isset($_GET['status'])) {
        $status_param_umum = $_GET['status'];
        // Mapping parameter status umum jika diperlukan untuk banner
        switch ($status_param_umum) {
             case 'kasir': $payment_status_banner_message = 'Informasi Pesanan Kasir.'; $status_banner_class = 'alert-info'; $show_status_banner = true; break;
             // Tambahkan case lain untuk status umum jika ada
             default: // Jika status umum tidak dikenal, fallback ke banner berdasarkan status DB jika data order ada
                 if ($order_details) {
                     $db_status_for_banner_fallback = $order_details['header']['status']; // Ambil status dari DB
                      switch ($db_status_for_banner_fallback) {
                          case 'pending': $payment_status_banner_message = 'Pesanan menunggu pembayaran.'; $status_banner_class = 'alert-warning'; $show_status_banner = true; break;
                          case 'diproses': $payment_status_banner_message = 'Pesanan sedang diproses.'; $status_banner_class = 'alert-info'; $show_status_banner = true; break;
                          case 'selesai': $payment_status_banner_message = 'Pesanan telah selesai.'; $status_banner_class = 'alert-success'; $show_status_banner = true; break;
                           case 'dibatalkan': $payment_status_banner_message = 'Pesanan ini sudah dibatalkan.'; $status_banner_class = 'alert-danger'; $show_status_banner = true; break;
                           default: $payment_status_banner_message = 'Status Pesanan: ' . htmlspecialchars(ucwords(str_replace('_', ' ', $db_status_for_banner_fallback))); $status_banner_class = 'alert-secondary'; $show_status_banner = true; break;
                      }
                 } // End if order_details
                 break; // End default case for status_param_umum
        }
   }
   */
   // Jika TIDAK ada parameter status spesifik di URL (payment_status atau status umum yang dihandle),
   // banner tidak ditampilkan (show_status_banner tetap false). Tampilan akan fokus pada struk dengan status dari DB.


// --- Status yang ditampilkan di bagian INFO STRUK (dari DB setelah update FASE 2) ---
$status_in_struk_db = isset($order_details['header']['status']) ? $order_details['header']['status'] : 'N/A';
$display_status_in_struk = ucwords(str_replace('_', ' ', $status_in_struk_db));

// --- END FASE 3 ---

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Pesanan - <?php echo ($order_details && $order_id !== null) ? 'Order #' . htmlspecialchars($order_details['header']['id_pesanan']) : 'Error'; ?></title>

    <!-- Sertakan Bootstrap CSS (sesuaikan path) -->
    <!-- Pastikan PATH INI BENAR RELATIF dari sukses.php Anda! -->

    <style>
        /* ============================================================== */
        /* --- STYLE CSS Struk Kasir --- */

        body {
            background-color: #f8f9fa; color: #212529;
            font-family: 'Consolas', 'Courier New', monospace;
            padding: 10px; line-height: 1.4;
            display: flex; flex-direction: column; align-items: center;
            min-height: 100vh; overflow-y: auto; gap: 15px;
        }

        /* Gaya banner status (alert Bootstrap) */
        .status-message-display {
            margin: 0 auto; padding: 12px 20px; border-radius: 5px;
            font-weight: bold; word-wrap: break-word; width: 100%;
            max-width: 380px; text-align: center; font-size: 0.95em;
        }

        /* Container utama struk */
        .struk-container {
            width: 350px; max-width: 95%;
            background-color: #fff; padding: 20px 15px;
            border: 1px solid #ddd; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin: 0 auto;
            display: flex; flex-direction: column; align-items: stretch; gap: 15px;
        }

        /* Default spacing for blocks */
        .shop-info, .struk-info, .item-list, .summary, .payment-info, .thanks, .error-message { width: 100%; }
        .shop-info { text-align: center; }
        .shop-logo { max-width: 100px; height: auto; margin: 0 auto 10px; display: block; } /* Center logo */
        .shop-info h3 { margin: 0; font-size: 1.6em; font-weight: bold; word-wrap: break-word; }
        .shop-info p { margin: 2px 0; font-size: 0.9em; word-wrap: break-word; }
        .separator { border-bottom: 1px dashed #000; margin: 15px 0; width: 100%; }

        /* Table Styles */
        .struk-info table, .item-list table, .summary table, .payment-info table { width: 100%; border-collapse: collapse; margin: 0; font-size: 0.95em; }
        .struk-info table td, .item-list table td, .summary table td, .payment-info table td { padding: 4px 0; border: none; vertical-align: top; text-align: left; word-wrap: break-word; }

        /* Info Table */
        .struk-info table td:first-child { width: 110px; padding-right: 8px; font-weight: normal; }
        .struk-info table td:last-child { text-align: right; }
        .struk-info .order-id-row td { font-weight: bold; }
        .struk-info .order-id-row td:last-child { font-size: 1.1em; }

        /* Item List */
        .item-list { font-size: 0.9em; }
        .item-list table td { padding: 6px 0; }
        .item-details-content { display: flex; align-items: flex-start; gap: 8px; width: 100%;}
        .item-thumb { width: 40px; height: 40px; object-fit: cover; border: 1px solid #eee; border-radius: 4px; flex-shrink: 0; display: block; }
        .item-text-details { flex-grow: 1; word-break: break-word;}
        .item-list .col-item-number-image { width: 50px; padding-right: 8px; }
        .item-list .col-item-details { flex-grow: 1; padding-right: 8px;}
        .item-list .col-item-price { width: 80px; text-align: right; font-weight: bold; flex-shrink: 0;}
        .item-name { font-weight: bold; display: block; line-height: 1.2; margin-bottom: 2px;}
        .item-qty-price { font-size: 0.9em; color: #555; font-weight: normal; line-height: 1.2; }

        /* Summary & Payment Table */
        .summary, .payment-info { font-size: 1em; }
        .summary table td, .payment-info table td { padding: 5px 0; }
        .summary .label-col, .payment-info .label-col { width: 110px; padding-right: 8px; font-weight: normal; }
        .summary .value-col, .payment-info .value-col { text-align: right; font-weight: normal; }
        .summary .total-row td { font-size: 1.3em; font-weight: bold; padding-top: 8px; border-top: 1px dashed #000; }
        .summary .total-row .label-col { font-weight: bold;}

        /* Thanks message */
        .thanks { text-align: center; font-size: 1em; margin-top: 20px; font-weight: bold; word-wrap: break-word; line-height: 1.4; }

        /* Button Area (Screen only) */
        .button-area {
            text-align: center; margin-top: 25px; padding-top: 15px; border-top: 1px solid #eee;
            width: 100%; max-width: 350px; margin: 25px auto 0; /* Adjusted margin-top, added auto margins for centering */
            display: flex; justify-content: center; gap: 10px; /* Flexbox for buttons */
        }
        .button-area .btn { font-size: 0.9em; padding: 8px 15px; }

        /* Error Message style if order_details is null */
        .error-message { width: 100%; margin: 0 auto; padding: 15px; } /* Ensure width and basic centering */

        /* --- @media print styles --- */
        @media print {
            body { background-color: #fff !important; color: #000 !important; justify-content: flex-start !important; align-items: flex-start !important; padding: 0 !important; margin: 0 !important; width: 80mm !important; max-width: 80mm !important; min-width: 80mm !important; min-height: auto !important; overflow: visible !important; line-height: 1.2 !important; font-size: 9pt !important; font-family: 'Consolas', 'Courier New', monospace !important; display: block !important; gap: 0 !important; } /* Removed gap */
            .status-message-display, .button-area { display: none !important; } /* Hide */
            .struk-container { width: 80mm !important; max-width: 80mm !important; min-width: 80mm !important; box-shadow: none !important; border: none !important; margin: 0 !important; padding: 5mm 3mm !important; display: block !important; page-break-after: auto !important; page-break-inside: avoid !important; gap: 0 !important;} /* Removed gap */

             /* Reduced margins and border margins for print */
            .shop-info, .struk-info, .item-list, .summary, .payment-info, .thanks, .error-message { margin-bottom: 3mm !important; }
            .separator { border-bottom: 1px dashed #000 !important; margin: 3mm 0 !important; }

            /* Font sizes for print */
            .shop-info h3 { font-size: 12pt !important; } .shop-info p { font-size: 8pt !important; }
            .struk-info, .item-list table, .summary table, .payment-info table { font-size: 9pt !important; }
            .thanks { font-size: 10pt !important; margin-top: 5mm !important; }

            /* Table cell padding for print */
             .struk-info table td, .item-list table td, .summary table td, .payment-info table td { padding: 2px 0 !important; word-wrap: break-word !important; word-break: break-all !important;}

            /* Specific column widths for print */
             .struk-info table td:first-child { width: 40% !important; padding-right: 3mm !important; font-weight: bold !important;} .struk-info table td:last-child { width: 60% !important; text-align: right !important; }
             .item-list .col-item-number-image { width: 15% !important; padding-right: 3mm !important;} .item-list .col-item-details { width: 50% !important; padding-right: 3mm !important;} .item-list .col-item-price { width: 35% !important; text-align: right !important; font-weight: bold !important;}
             .item-details-content { display: flex !important; gap: 3mm !important; align-items: flex-start !important; width: auto !important;}
             .item-thumb { width: 10mm !important; height: auto !important; object-fit: cover !important; border: none !important; border-radius: 0 !important; flex-shrink: 0 !important; display: block !important;}
             .item-text-details { flex-grow: 1 !important; word-break: break-word !important; }
              .item-name { font-weight: bold !important; margin-bottom: 1px !important; line-height: 1.1 !important; font-size: 9pt !important;}
              .item-qty-price { font-size: 8pt !important; line-height: 1.1 !important; color: #000 !important; font-weight: normal !important; }

             .summary .label-col, .payment-info .label-col { width: 60% !important; font-weight: normal !important;} .summary .value-col, .payment-info .value-col { width: 40% !important; text-align: right !important;}
             .summary .total-row td { font-size: 10pt !important; font-weight: bold !important; padding-top: 3px !important; border-top: 1px dashed #000 !important;}
             .item-list .item-counter { font-weight: bold !important; display: inline-block !important;}


        /* --- END STYLE CSS --- */
        /* ============================================================== */
    </style>
</head>
<body>

    <div class="struk-container">

        <?php
         // Tampilkan pesan error JIKA koneksi DB gagal, ATAU jika data order tidak berhasil diambil
        if (!$con_ok || !isset($order_details) || $order_details === null ): ?>
             <div class="error-message">
                 <div class="alert alert-danger text-center" role="alert">
                    <strong>Error!</strong> Detail pesanan tidak dapat ditampilkan.
                     <br>
                     <?php
                         if (!$con_ok) echo nl2br(htmlspecialchars($error_message_db_conn));
                         else echo nl2br(htmlspecialchars($error_message_display ?? 'Terjadi kesalahan yang tidak diketahui saat mengambil data.'));
                         if (isset($order_id_clean_or_raw_html)) {
                             echo "<br>ID di URL: \"{$order_id_clean_or_raw_html}\"";
                         }
                     ?>
                 </div>
             </div>

         <?php else: // Jika data order berhasil diambil ?>

            <!-- Shop Info (Header Statis - SESUAIKAN) -->
            <div class="shop-info">
                 <!-- Gambar Logo Resto (PASTIHKAN PATHNYA BENAR!) -->
                 <?php
                    // !!! SESUAIKAN PATH LOGO ANDA !!!
                    $logo_path_relative = '../../../admin/assets/images/logo-rika.svg';
                    if (file_exists($logo_path_relative)) echo '<img src="' . htmlspecialchars($logo_path_relative) . '" alt="Logo Resto" class="shop-logo">';
                    else error_log("sukses.php: Restaurant logo file not found at path: " . $logo_path_relative); // Log error jika tidak ditemukan
                 ?>
                <h3><?php echo htmlspecialchars("resto rahmat"); ?></h3> <!-- Nama Restoran -->
                <p><?php echo htmlspecialchars("Jl. Jalan jalan Saja"); ?></p> <!-- Alamat -->
                <p><?php echo htmlspecialchars("Makassar, 12345"); ?></p> <!-- Kota/Kode Pos -->
                <p>Telp: <?php echo htmlspecialchars("085213976352"); ?></p> <!-- Nomor Telepon -->
            </div>
            <div class="separator"></div>

            <!-- Info Pesanan (TABEL) -->
            <div class="struk-info">
                 <table>
                    <tbody>
                         <tr><td>Tanggal:</td><td><?php echo htmlspecialchars(format_timestamp_part($order_details['header']['waktu_pesan'], 'date')); ?></td></tr>
                         <tr><td>Jam:</td><td><?php echo htmlspecialchars(format_timestamp_part($order_details['header']['waktu_pesan'], 'time')); ?></td></tr>
                         <tr class="order-id-row"><td>Nomor Order:</td><td>#<?php echo htmlspecialchars($order_details['header']['id_pesanan']); ?></td></tr>
                         <tr><td>Meja:</td><td><?php echo htmlspecialchars($order_details['header']['meja'] ?? '-'); ?></td></tr>
                         <tr><td>Pemesan:</td><td><?php echo htmlspecialchars($order_details['header']['nama_pemesan'] ?? '-'); ?></td></tr>
                         <tr><td>Telepon:</td><td><?php echo htmlspecialchars($order_details['header']['notelepon'] ?? '-'); ?></td></tr>
                         <tr><td>Status Order:</td><td><?php echo htmlspecialchars($display_status_in_struk); ?></td></tr> <!-- Status dari DB (setelah update FASE 2) -->
                    </tbody>
                </table>
            </div>
            <div class="separator"></div>

            <!-- Detail Item (TABEL) -->
            <div class="item-list">
                 <table>
                    <tbody>
                         <?php if (!empty($order_details['items'])): ?>
                             <?php $item_counter = 1; ?>
                            <?php foreach ($order_details['items'] as $item): ?>
                                <tr>
                                     <td class="col-item-number-image"><span class="item-counter"><?php echo $item_counter++; ?>.</span></td>
                                     <td class="col-item-details">
                                          <div class="item-details-content">
                                                <?php echo get_item_image_tag($item['gambar']); // Gambar Item ?>
                                                <div class="item-text-details">
                                                     <span class="item-name"><?php echo htmlspecialchars($item['nama_menu']); ?></span><br>
                                                     <span class="item-qty-price"><?php echo htmlspecialchars($item['jumlah']); ?> × <?php echo format_rp($item['harga_satuan']); ?></span>
                                                </div>
                                           </div>
                                     </td>
                                     <td class="col-item-price"><?php echo format_rp($item['subtotal']); ?></td>
                                </tr>
                                <?php // Opsi: Baris catatan item jika ada kolom catatan di DB dan diambil
                                // if (isset($item['catatan']) && !empty($item['catatan'])) echo '<tr><td colspan="3" style="padding-left: 30px; font-style:italic; font-size:0.9em;">Note: ' . htmlspecialchars($item['catatan']) . '</td></tr>';
                                ?>
                            <?php endforeach; ?>
                         <?php else: ?>
                             <tr><td colspan="3" class="text-center text-muted" style="font-style: italic;">-- Tidak ada item dalam pesanan ini --</td></tr>
                         <?php endif; ?>
                    </tbody>
                 </table>
            </div>
            <div class="separator"></div>

            <!-- Summary (TABEL) -->
            <div class="summary">
                <table>
                    <tbody>
                         <tr><td class="label-col">Total Qty:</td><td class="value-col"><?php echo htmlspecialchars($order_details['header']['total_qty'] ?? '0'); ?></td></tr>
                         <tr><td class="label-col">Subtotal:</td><td class="value-col"><?php echo format_rp($order_details['header']['total_harga'] ?? '0'); ?></td></tr>
                         <tr class="total-row"><td class="label-col">TOTAL:</td><td class="value-col"><?php echo format_rp($order_details['header']['total_harga'] ?? '0'); ?></td></tr>
                         <?php // Opsi: Baris Diskon/Pajak/Layanan jika ada di DB dan diambil
                         // if (isset($order_details['header']['diskon_amount'])) echo '<tr><td class="label-col">Diskon:</td><td class="value-col">- ' . format_rp($order_details['header']['diskon_amount']) . '</td></tr>';
                         ?>
                    </tbody>
                 </table>
            </div>

            <!-- Payment Info (TABEL) -->
             <div class="payment-info">
                 <table>
                     <tbody>
                          <tr>
                              <td class="label-col">Metode Bayar:</td>
                              <td class="value-col"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $order_details['header']['metode_pembayaran'] ?? '-'))); ?></td>
                          </tr>
                          <?php // Opsi: Baris Bayar dan Kembali jika ada di DB dan diambil
                          // if (isset($order_details['header']['jumlah_dibayar'])) { ... echo rows ... }
                          ?>
                     </tbody>
                 </table>
             </div>
            <div class="separator"></div>

            <!-- Thanks Message (STATIS - SESUAIKAN) -->
            <div class="thanks">
                 <p><?php echo htmlspecialchars("Terimakasih Telah Berbelanja"); ?></p>
            </div>

        <?php endif; // End if order_details (show struk or error) ?>

    </div> <!-- End .struk-container -->


    <!-- Button Area (LAYAR SAJA) -->
    <!-- Ditempatkan di luar struk-container tapi di-center dengan lebar yang sama -->
    <div class="button-area">
         <?php
         // --- Link "Kembali" (Mengambil param dari URL sukses.php) ---
         // Ambil meja, nama, telepon dari parameter URL sukses.php saat ini
         $back_meja_from_url_sukses = $_GET['meja'] ?? null;
         $back_nama_from_url_sukses = $_GET['nama'] ?? null;
         $back_notelepon_from_url_sukses = $_GET['notelepon'] ?? null;

         // !!! PATH KEMBALI INI PENTING - SESUAIKAN RELATIF KE index.php !!!
         // Hitung path dari sukses.php ke index.php Anda.
         $base_back_url = "../"; // Path relatif dari sukses.php (jika di /payment/) ke index.php (jika di /pelanggan/)

         // Bentuk URL kembali dengan parameter jika tersedia dari GET sukses.php
         $back_params = [];
         if (!empty($back_meja_from_url_sukses)) $back_params[] = "meja=" . urlencode($back_meja_from_url_sukses);
         if (!empty($back_nama_from_url_sukses)) $back_params[] = "nama=" . urlencode($back_nama_from_url_sukses);
         if (!empty($back_notelepon_from_url_sukses)) $back_params[] = "notelepon=" . urlencode($back_notelepon_from_url_sukses);

         $back_url = $base_back_url . (!empty($back_params) ? "?" . implode("&", $back_params) : "");
         ?>
         <!-- Tombol Kembali -->
        <a href="<?php echo htmlspecialchars($back_url); ?>" class="btn btn-secondary btn-sm">Kembali</a>

        <?php if (isset($order_details)): // Tombol Print hanya jika ada data pesanan ?>
             <!-- Tombol Cetak Struk -->
             <button class="btn btn-primary btn-sm" onclick="window.print()">Cetak Struk</button>
         <?php endif; ?>

         <!-- --- Placeholder Banner Status (Tampil HANYA Jika URL ada parameter status spesifik) --- -->
         <!-- Banner status dipindahkan ke bawah button area, sesuaikan styling jika tata letak tidak pas -->
          <?php
            // Parameter URL status dari payment gateway (GANTI JIKA NAMA BEDA!)
            $payment_gateway_status_param_name = 'payment_status';
           // Tentukan apakah banner status harus tampil berdasarkan parameter URL
           // Prioritaskan nama parameter status gateway jika ada
           $show_status_banner_from_url = false;
           $status_param_for_banner = null; // Parameter URL yang dipakai untuk banner

           if (isset($_GET[$payment_gateway_status_param_name])) {
               $show_status_banner_from_url = true;
               $status_param_for_banner = $_GET[$payment_gateway_status_param_name];
           }
           // Jika tidak ada parameter payment gateway status, tapi ada parameter status umum (misal dari link kasir.php)?
           elseif (isset($_GET['status'])) {
                $show_status_banner_from_url = true;
                $status_param_for_banner = $_GET['status']; // Pakai parameter status umum
           }


           // Jika banner harus tampil, tentukan pesan dan kelasnya (ulangi switch case dari FASE 3)
           $payment_status_banner_message = '';
           $status_banner_class = '';
           if ($show_status_banner_from_url && $status_param_for_banner !== null) {
                switch ($status_param_for_banner) {
                    case 'success': $payment_status_banner_message = 'Pembayaran Online BERHASIL!'; $status_banner_class = 'alert-success'; break;
                    case 'pending': $payment_status_banner_message = 'Pembayaran Online Menunggu Konfirmasi. Mohon Ikuti Instruksi Pembayaran Anda.'; $status_banner_class = 'alert-warning'; break;
                    case 'challenge': $payment_status_banner_message = 'Pembayaran Online Memerlukan Verifikasi Tambahan. Mohon Periksa Detail Transaksi Anda.'; $status_banner_class = 'alert-warning'; break;
                    case 'failed': $payment_status_banner_message = 'Pembayaran Online Gagal. Silakan coba metode lain atau bayar di kasir.'; $status_banner_class = 'alert-danger'; break;
                    case 'canceled': $payment_status_banner_message = 'Anda Menutup Jendela Pembayaran Online. Pesanan menunggu Anda lanjutkan bayar atau hubungi staf.'; $status_banner_class = 'alert-warning'; break;
                    case 'deny': $payment_status_banner_message = 'Transaksi Online Ditolak Sistem Pembayaran. Silakan coba metode lain atau bayar di kasir.'; $status_banner_class = 'alert-danger'; break;
                    case 'expired': $payment_status_banner_message = 'Transaksi Pembayaran Online Telah Kadaluarsa.'; $status_banner_class = 'alert-danger'; break;
                    case 'kasir': $payment_status_banner_message = 'Informasi Pesanan Kasir.'; $status_banner_class = 'alert-info'; break; // Case status=kasir
                    case 'created': $payment_status_banner_message = 'Pesanan Berhasil Dibuat dan Menunggu Pembayaran.'; $status_banner_class = 'alert-info'; break; // Case status=created
                    default: $payment_status_banner_message = 'Status Pembayaran Tidak Dikenal: ' . htmlspecialchars($status_param_for_banner); $status_banner_class = 'alert-secondary'; break;
                }
           }
         // Cetak banner jika show_status_banner_from_url true dan pesan tidak kosong
         if ($show_status_banner_from_url && !empty($payment_status_banner_message)): ?>
             <!-- Bootstrap Alert/Banner Pesan Status (VISIBLE DI LAYAR, HIDDEN DI PRINT) -->
             <!-- Diposisikan di bawah button area, agar tidak mengganggu tata letak utama struk di print -->
              <!-- Sesuaikan styling margin/posisi jika perlu di CSS agar posisinya pas di LAYAR -->
         <?php endif; ?>


    </div> <!-- Akhir button-area -->
 
</body>
</html>
<?php
// --- TUTUP KONEKSI DATABASE ---
// Tutup koneksi DB jika berhasil dibuka dan belum ditutup oleh blok POST.
if (isset($con) && $con instanceof mysqli && !$con->connect_error) {
    $con->close();
}

?>