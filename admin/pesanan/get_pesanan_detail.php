<?php
session_start();

// Sertakan file koneksi.php (sesuaikan jalur jika diperlukan)
include '../koneksi.php';

// Pastikan ini adalah permintaan AJAX GET dan ada ID pesanan
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['id'])) {

    // Periksa pengguna login
    if (!isset($_SESSION['username'])) {
        echo "<div class='alert alert-danger'>Autentikasi dibutuhkan. Silakan login kembali.</div>";
        if (isset($conn) && $conn) $conn->close(); // Tutup koneksi
        exit();
    }

    $id_pesanan = $_GET['id'];

    // --- Ambil Detail Pesanan Utama ---
    // SELECT dari pesanan, JOIN ke pelanggan untuk nama (pl.nama) sesuai diagram
    $sql_pesanan = "
        SELECT
            p.*, -- Ambil semua kolom dari pesanan
            pl.nama AS nama_pelanggan -- *** AMBIL nama dari pl.nama, ALIAS nama_pelanggan ***
        FROM
            pesanan p
        LEFT JOIN
            pelanggan pl ON p.id_pelanggan = pl.id_pelanggan
        WHERE
            p.id_pesanan = ?
        LIMIT 1
    ";
    $stmt_pesanan = $conn->prepare($sql_pesanan);
     if ($stmt_pesanan === false) {
         echo "<div class='alert alert-danger'>Error mempersiapkan query pesanan: " . $conn->error . "</div>";
         if (isset($conn) && $conn) $conn->close();
         exit();
     }
    $stmt_pesanan->bind_param("i", $id_pesanan);
    $stmt_pesanan->execute();
    $result_pesanan = $stmt_pesanan->get_result();
    $pesanan = $result_pesanan ? $result_pesanan->fetch_assoc() : null; // Handle jika get_result gagal
    $stmt_pesanan->close(); // Tutup statement

    // Jika pesanan tidak ditemukan atau error saat mengambil data utama
    if (!$pesanan) {
        echo "<div class='alert alert-warning'>Detail pesanan tidak ditemukan atau gagal memuat data utama.</div>";
        if (isset($conn) && $conn) $conn->close();
        exit();
    }

    // --- Ambil Item Detail Pesanan ---
    // SELECT item dari detail_pesanan, JOIN ke menu untuk nama_menu (sesuai diagram)
    $sql_detail = "
        SELECT
            dp.jumlah,
            dp.harga_satuan,
            mn.nama_menu, -- *** AMBIL nama_menu dari mn.nama_menu ***
            mn.id_menu -- Sertakan juga ID menu jika nama_menu kosong/null
        FROM
            detail_pesanan dp
        LEFT JOIN
            menu mn ON dp.id_menu = mn.id_menu -- JOIN ke tabel menu
        WHERE
            dp.id_pesanan = ?
        ORDER BY dp.id_detail ASC -- Urutkan item berdasarkan id_detail
    ";
    $stmt_detail = $conn->prepare($sql_detail);
    if ($stmt_detail === false) {
        echo "<div class='alert alert-danger'>Error mempersiapkan query detail item: " . $conn->error . "</div>";
        if (isset($conn) && $conn) $conn->close();
        exit();
    }
    $stmt_detail->bind_param("i", $id_pesanan);
    $stmt_detail->execute();
    $result_detail = $stmt_detail->get_result();
     $detail_items = ($result_detail && $result_detail instanceof mysqli_result) ? $result_detail->fetch_all(MYSQLI_ASSOC) : []; // Fetch all items into an array
    $stmt_detail->close(); // Tutup statement detail

     // Helper function for ucfirst (aman HTML) - Hanya digunakan di file ini get_pesanan_detail.php
     function ucfirst_safe_detail($string) {
         if (!is_string($string)) return $string;
         // Gunakan htmlspecialchars untuk escaping data
         return htmlspecialchars(ucfirst(htmlspecialchars_decode($string ?? '', ENT_QUOTES)), ENT_QUOTES);
     }

    // --- Format HTML untuk Modal Body (Standard CSS / Bootstrap Utilities) ---
    // Gunakan nama alias `nama_pelanggan` dari SELECT query
    $nama_pelanggan_display = htmlspecialchars($pesanan['nama_pelanggan'] ?? $pesanan['id_pelanggan'] ?? 'N/A');

    $status_value = $pesanan['status'] ?? 'N/A';
    // CSS Class untuk badge status di modal (akan distyle oleh CSS di index.php)
    $status_class = 'status-' . htmlspecialchars($status_value); // Gunakan nilai mentah status untuk class

    // Bagian detail utama pesanan
    echo "<div class='mb-4 pb-3 border-bottom'>"; // Margin bottom dan border bawah Bootstrap
     // Title of the details section
     echo "<h5 class='font-weight-bold mb-3'>Pesanan #" . htmlspecialchars($pesanan['id_pesanan']) . "</h5>"; // Bold title


     // Display basic info (ID Pesanan, Status, Total Harga)
     // Note: Screenshot menunjukkan status & total harga setelah ID Pesanan utama
     echo "<p class='mb-1'><strong>ID Pesanan:</strong> " . htmlspecialchars($pesanan['id_pesanan']) . "</p>"; // ID Pesanan
     echo "<p class='mb-1'><strong class='text-muted'>Status:</strong> <span class='status-display " . $status_class . "'>" . ucfirst_safe_detail($status_value) . "</span></p>"; // Status Badge (pakai helper lokal)
     echo "<p class='mb-1'><strong class='text-muted'>Total Harga:</strong> Rp " . number_format($pesanan['total_harga'] ?? 0, 0, ',', '.') . "</p>"; // Total Price


     echo "<div class='row mt-3'>"; // Bootstrap grid for layout (2 columns for small details)
     echo "<div class='col-6'>"; // Column 1
     echo "<small class='text-muted'>Waktu Pesan:</small><br>"; // Label kecil muted
     echo "<span>" . htmlspecialchars($pesanan['waktu_pesan'] ?? 'N/A') . "</span>"; // Value
     echo "</div>";
     echo "<div class='col-6'>"; // Column 2
     echo "<small class='text-muted'>Metode Pembayaran:</small><br>";
     echo "<span>" . htmlspecialchars($pesanan['metode_pembayaran'] ?? 'N/A') . "</span>";
     echo "</div>";
     echo "</div>"; // End row


    echo "<div class='row mt-2'>"; // Another row for other details
     echo "<div class='col-6'>";
     echo "<small class='text-muted'>ID Meja:</small><br>";
     echo "<span>" . htmlspecialchars($pesanan['id_meja'] ?? 'N/A') . "</span>"; // ID Meja dari tabel pesanan
     echo "</div>";
     echo "<div class='col-6'>";
     echo "<small class='text-muted'>Nama Pelanggan:</small><br>";
     echo "<span>" . $nama_pelanggan_display . "</span>"; // Nama Pelanggan dari JOIN
     echo "</div>";
     echo "</div>"; // End row


    echo "</div>"; // End main detail section


    // Item Pesanan Section (Using List Structure like Screenshot terbaru)
     echo "<h6 class='mb-3 font-weight-bold'>Item Pesanan:</h6>";

     if (!empty($detail_items)) {
         // Gunakan ul dengan list-unstyled untuk daftar tanpa bullet point dan padding
         echo "<ul class='list-unstyled m-0 p-0'>"; // Bootstrap list style reset

        foreach ($detail_items as $item) {
             // Akses data item detail (menggunakan null coalescing untuk keamanan)
             $nama_menu_raw = $item['nama_menu'] ?? null;
             $id_menu_raw = $item['id_menu'] ?? null;
             $jumlah_raw = $item['jumlah'] ?? 0;
             $harga_satuan_raw = $item['harga_satuan'] ?? 0;

             // Tampilkan nama menu (dari JOIN) atau fallback ke ID
             $nama_menu_tampil = htmlspecialchars($nama_menu_raw ?? ($id_menu_raw ? 'ID Menu: ' . $id_menu_raw : 'Unknown Menu'));

             $harga_satuan_format = "Rp " . number_format($harga_satuan_raw, 0, ',', '.'); // Harga per item format
             $subtotal_calc = $harga_satuan_raw * $jumlah_raw; // Hitung subtotal item ini
             $subtotal_format = "Rp " . number_format($subtotal_calc, 0, ',', '.'); // Subtotal format

            // List item dengan tata letak flexbox (display:flex di Bootstrap = d-flex)
             echo "<li class='d-flex justify-content-between align-items-center py-2 border-bottom'>"; // Flexbox Bootstrap + spacing + border

            echo "<div class='flex-grow-1 mr-3'>"; // Konten kiri (nama item, detail harga x qty)
             echo "<p class='font-weight-bold mb-0'>" . $nama_menu_tampil . "</p>"; // Nama item (bold)
             // Detail harga dan jumlah (kecil, muted)
             // Menggunakan × untuk simbol 'x'
             echo "<small class='text-muted'>(" . $harga_satuan_format . " × " . htmlspecialchars($jumlah_raw) . ")</small>";
            echo "</div>"; // End Konten kiri

            echo "<span class='font-weight-bold' style='white-space: nowrap;'>" . $subtotal_format . "</span>"; // Subtotal kanan (bold, no wrap)

            echo "</li>"; // End list item
        }
         echo "</ul>"; // End list

     } else if ($result_detail === null || $result_detail === false) {
        // Jika query detail item gagal
        echo "<div class='alert alert-danger'>Gagal memuat detail item pesanan.</div>";
     } else {
        // Jika pesanan tidak punya detail item (list kosong)
        echo "<p class='text-muted'>Tidak ada item dalam pesanan ini.</p>";
     }

     // Tutup koneksi database di akhir file ini get_pesanan_detail.php
     if (isset($conn) && $conn) {
        $conn->close();
    }

} else {
    // Jika bukan GET request atau tidak ada ID yang valid di URL
    echo "<div class='alert alert-danger'>Invalid request or missing data.</div>";
     if (isset($conn) && $conn) {
         $conn->close();
     }
}
?>