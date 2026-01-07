<?php
session_start();
// Sertakan file koneksi.php (sesuaikan jalur jika diperlukan)
include '../koneksi.php'; // PASTIKAN JALUR INI BENAR

// Periksa apakah pengguna sudah login (opsional, tapi disarankan untuk laporan)
if (!isset($_SESSION['username'])) {
    echo "<h1>Akses Ditolak</h1><p>Anda harus login untuk dapat melihat laporan ini.</p>";
    if (isset($conn) && $conn) $conn->close();
    exit();
}

$laporan_error = null;
$results_laporan = []; // Ini akan berisi array pesanan, masing-masing dengan item detailnya
$nama_periode_display = "Belum Dipilih"; // Variabel untuk menyimpan nama periode (bulan/tanggal)
$total_pendapatan_laporan = 0;
$jumlah_pesanan_laporan = 0;

// Logika untuk menentukan apakah laporan bulanan atau harian
$sql_laporan_utama = "";
$param_type = "";
$param_value = "";

if (isset($_GET['tanggal']) && !empty($_GET['tanggal'])) {
    // Laporan Harian
    $tanggal_laporan_input = $_GET['tanggal']; // Format YYYY-MM-DD
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_laporan_input)) {
        $laporan_error = "Format tanggal tidak valid. Harap gunakan pemilih tanggal yang tersedia.";
    } else {
        $nama_periode_display = date("d F Y", strtotime($tanggal_laporan_input)); // Contoh: 28 Mei 2025
        $sql_laporan_utama = "
            SELECT
                p.id_pesanan, p.id_meja, p.status, p.total_harga, p.waktu_pesan, p.metode_pembayaran,
                pl.nama AS nama_pelanggan
            FROM
                pesanan p
            LEFT JOIN
                pelanggan pl ON p.id_pelanggan = pl.id_pelanggan
            WHERE
                DATE(p.waktu_pesan) = ?
            ORDER BY
                p.waktu_pesan ASC";
        $param_type = "s";
        $param_value = $tanggal_laporan_input;
    }
} elseif (isset($_GET['bulan']) && !empty($_GET['bulan'])) {
    // Laporan Bulanan
    $bulan_laporan_input = $_GET['bulan']; // Format YYYY-MM
    if (!preg_match('/^\d{4}-\d{2}$/', $bulan_laporan_input)) {
        $laporan_error = "Format bulan tidak valid. Harap gunakan pemilih bulan yang tersedia.";
    } else {
        // Konversi YYYY-MM ke nama bulan dan tahun untuk tampilan
        if (class_exists('IntlDateFormatter')) {
            $dateObj = DateTime::createFromFormat('Y-m', $bulan_laporan_input);
            if ($dateObj) {
                $formatter = new IntlDateFormatter('id_ID', IntlDateFormatter::LONG, IntlDateFormatter::NONE, 'Asia/Jakarta', null, 'MMMM yyyy');
                $nama_periode_display = $formatter->format($dateObj);
            } else {
                $nama_periode_display = "Bulan " . $bulan_laporan_input . " (Format tidak dikenali)";
            }
        } else {
            list($year, $month_num) = explode('-', $bulan_laporan_input);
            $month_name = date("F", mktime(0, 0, 0, (int)$month_num, 10));
            $bulan_indonesia = [
                "January" => "Januari", "February" => "Februari", "March" => "Maret",
                "April" => "April", "May" => "Mei", "June" => "Juni",
                "July" => "Juli", "August" => "Agustus", "September" => "September",
                "October" => "Oktober", "November" => "November", "December" => "Desember"
            ];
            $nama_periode_display = (isset($bulan_indonesia[$month_name]) ? $bulan_indonesia[$month_name] : $month_name) . " " . $year;
        }

        // Query untuk laporan bulanan
        $sql_laporan_utama = "
            SELECT
                p.id_pesanan, p.id_meja, p.status, p.total_harga, p.waktu_pesan, p.metode_pembayaran,
                pl.nama AS nama_pelanggan
            FROM
                pesanan p
            LEFT JOIN
                pelanggan pl ON p.id_pelanggan = pl.id_pelanggan
            WHERE
                DATE_FORMAT(p.waktu_pesan, '%Y-%m') = ?
            ORDER BY
                p.waktu_pesan ASC";
        $param_type = "s";
        $param_value = $bulan_laporan_input;
    }
} else {
    $laporan_error = "Silakan pilih tanggal atau bulan untuk laporan.";
}

// Proses query utama jika tidak ada error
if ($laporan_error === null && !empty($sql_laporan_utama)) {
    $stmt_laporan_utama = $conn->prepare($sql_laporan_utama);

    if ($stmt_laporan_utama === false) {
        $laporan_error = "Gagal mempersiapkan query laporan utama: " . $conn->error;
    } else {
        $stmt_laporan_utama->bind_param($param_type, $param_value);
        if ($stmt_laporan_utama->execute()) {
            $result_set_utama = $stmt_laporan_utama->get_result();
            $temp_orders = [];
            if ($result_set_utama) {
                while ($row_utama = $result_set_utama->fetch_assoc()) {
                    $row_utama['items'] = []; // Inisialisasi array untuk item detail
                    $temp_orders[$row_utama['id_pesanan']] = $row_utama;
                }
            } else {
                $laporan_error = (isset($laporan_error) ? $laporan_error . " | " : "") . "Gagal mendapatkan hasil query laporan utama: " . $conn->error;
            }
            $stmt_laporan_utama->close();

            // 2. Jika ada pesanan utama, ambil detail item untuk masing-masing pesanan
            if (!empty($temp_orders)) {
                $array_id_pesanan = array_keys($temp_orders);
                if (!empty($array_id_pesanan)) { // Pastikan array tidak kosong sebelum membuat placeholder
                    $placeholders = implode(',', array_fill(0, count($array_id_pesanan), '?'));
                    $types = str_repeat('i', count($array_id_pesanan));

                    // Sesuaikan nama tabel dan kolom di sini jika berbeda
                    // Mengganti dp.subtotal_item menjadi dp.subtotal sesuai screenshot
                    $sql_detail_item = "
                        SELECT dp.id_pesanan, dp.jumlah, dp.harga_satuan, dp.subtotal, m.nama_menu
                        FROM detail_pesanan dp
                        JOIN menu m ON dp.id_menu = m.id_menu
                        WHERE dp.id_pesanan IN ($placeholders)
                        ORDER BY dp.id_pesanan, m.nama_menu ASC";

                    $stmt_detail_item = $conn->prepare($sql_detail_item);
                    if ($stmt_detail_item === false) {
                        $laporan_error = (isset($laporan_error) ? $laporan_error . " | " : "") . "Gagal mempersiapkan query detail item: " . $conn->error;
                    } else {
                        $stmt_detail_item->bind_param($types, ...$array_id_pesanan);
                        if ($stmt_detail_item->execute()) {
                            $result_detail_set = $stmt_detail_item->get_result();
                            if ($result_detail_set) {
                                while ($item_row = $result_detail_set->fetch_assoc()) {
                                    if (isset($temp_orders[$item_row['id_pesanan']])) {
                                        $temp_orders[$item_row['id_pesanan']]['items'][] = $item_row;
                                    }
                                }
                            } else {
                                $laporan_error = (isset($laporan_error) ? $laporan_error . " | " : "") . "Gagal mendapatkan hasil query detail item: " . $conn->error;
                            }
                        } else {
                            $laporan_error = (isset($laporan_error) ? $laporan_error . " | " : "") . "Gagal mengeksekusi query detail item: " . $stmt_detail_item->error;
                        }
                        $stmt_detail_item->close();
                    }
                }
            }
            $results_laporan = array_values($temp_orders); // Konversi kembali ke array numerik

            // Hitung ulang ringkasan setelah semua data (termasuk item) terkumpul
            $jumlah_pesanan_laporan = count($results_laporan);
            $total_pendapatan_laporan = 0;
            foreach ($results_laporan as $order_data) {
                if (strtolower($order_data['status']) == 'selesai') {
                    $total_pendapatan_laporan += (float)$order_data['total_harga'];
                }
            }

        } else { // Gagal eksekusi query utama
            $laporan_error = (isset($laporan_error) ? $laporan_error . " | " : "") . "Gagal mengeksekusi query laporan utama: " . $stmt_laporan_utama->error;
            if($stmt_laporan_utama) $stmt_laporan_utama->close();
        }
    }
} else if (empty($_GET['tanggal']) && empty($_GET['bulan'])) {
    // Jika tidak ada parameter yang dipilih, jangan tampilkan error, biarkan halaman kosong sampai dipilih
    $laporan_error = "Silakan pilih tanggal atau bulan untuk menampilkan laporan.";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Pesanan Detail - Resto-Rahmat</title>
    <link rel="shortcut icon" href="../assets/images/logo.png" /> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 20px;
            line-height: 1.6;
            background-color: #fff;
        }
        .report-container {
            width: 95%;
            margin: 0 auto;
            padding: 15px;
        }
        .report-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .report-header h1 { margin: 0; font-size: 24px; }
        .report-header h2 { margin: 5px 0; font-size: 20px; }
        .report-header p { margin: 5px 0; font-size: 14px; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 11px; /* Ukuran font lebih kecil untuk mengakomodasi detail */
        }
        th, td {
            border: 1px solid #888;
            padding: 6px; /* Padding lebih kecil */
            text-align: left;
            vertical-align: top; /* Rata atas untuk sel dengan konten beragam */
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .summary-section {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px solid #333;
        }
        .summary-section h3 { margin-top: 0; font-size: 16px; }
        .summary-section p { font-size: 14px; margin: 5px 0; }
        .print-button-container { text-align: center; margin: 20px 0; }
        .btn-print, .btn-close {
            padding: 10px 20px; font-size: 16px; cursor: pointer; color: white;
            border: none; border-radius: 5px; text-decoration: none; display: inline-block;
        }
        .btn-print { background-color: #4CAF50; }
        .btn-close { background-color: #f44336; margin-left:10px; }
        .alert { padding: 15px; margin-bottom: 20px; border: 1px solid transparent; border-radius: 4px; text-align: center; }
        .alert-danger { color: #a94442; background-color: #f2dede; border-color: #ebccd1; }
        .alert-info { color: #31708f; background-color: #d9edf7; border-color: #bce8f1; }

        .item-details-container {
            padding: 6px 10px;
            background-color: #fdfdfd;
            margin-top: 0px;
            border-top: 1px dashed #ccc; /* Garis pemisah halus */
        }
        .item-details-container strong {
            display: block;
            margin-bottom: 4px;
            font-size: 0.95em;
            color: #333;
        }
        .item-list {
            list-style-type: none; /* Hapus bullet point default */
            margin: 0;
            padding: 0;
            font-size: 0.9em; /* Sedikit lebih kecil dari teks utama sel */
        }
        .item-list li {
            padding: 2px 0;
            border-bottom: 1px dotted #eee; /* Pemisah antar item */
        }
        .item-list li:last-child {
            border-bottom: none;
        }
        .item-list .item-name { font-weight: 500; }
        .item-list .item-qty-price { color: #555; }
        .item-list .item-subtotal { font-weight: bold; }


        @media print {
            body { margin: 0.5cm; font-size: 9pt; background-color: #fff; } /* Font lebih kecil untuk print */
            .report-container { width: 100%; margin: 0; padding: 0; border: none; box-shadow: none; }
            .no-print { display: none !important; }
            table { font-size: 8pt; page-break-inside: auto; } /* Font tabel lebih kecil lagi */
            th, td { padding: 4px; border: 1px solid #333; }
            thead { display: table-header-group; }
            tfoot { display: table-footer-group; }
            tr, .summary-section, .item-details-container { page-break-inside: avoid !important; } /* Hindari pecah halaman di dalam detail */
            .report-header h1 { font-size: 16pt; }
            .report-header h2 { font-size: 13pt; }
            .report-header p { font-size: 10pt; }
            .summary-section p { font-size: 9pt; }
            .summary-section h3 { font-size: 11pt; }
            .item-details-container { background-color: #fff !important; border-top: 1px dashed #999; padding: 4px 6px; }
            .item-list { font-size: 0.85em; }
            .item-list li { padding: 1px 0; border-bottom: 1px dotted #ccc; }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <div class="print-button-container no-print">
            <button onclick="window.print()" class="btn-print">
                <i class="fas fa-print"></i> Cetak Laporan Ini
            </button>
            <button onclick="window.close()" class="btn-close">
                <i class="fas fa-times-circle"></i> Tutup
            </button>
        </div>

        <div class="report-header">
            <h1>Resto-Rahmat</h1>
            <h2>Laporan Detail Pesanan <?php echo (isset($_GET['tanggal']) && !empty($_GET['tanggal'])) ? 'Harian' : 'Bulanan'; ?></h2>
            <p>Periode: <strong><?php echo htmlspecialchars($nama_periode_display); ?></strong></p>
            <p class="no-print" style="font-size: 12px;">Dicetak pada: <?php echo date("d M Y, H:i:s T"); ?></p>
        </div>

        <?php if ($laporan_error): ?>
            <div class="alert alert-danger" role="alert">
                Terjadi kesalahan: <?php echo htmlspecialchars($laporan_error); ?>
            </div>
        <?php elseif (empty($results_laporan) && (isset($_GET['tanggal']) || isset($_GET['bulan'])) ): ?>
            <div class="alert alert-info" role="alert">
                Tidak ada data pesanan ditemukan untuk periode <?php echo htmlspecialchars($nama_periode_display); ?>.
            </div>
        <?php elseif (!empty($results_laporan)): ?>
            <table>
                <thead>
                    <tr>
                        <th class="text-center" style="width:3%;">No.</th>
                        <th class="text-center" style="width:7%;">ID Pesanan</th>
                        <th class="text-center" style="width:7%;">ID Meja</th>
                        <th style="width:18%;">Nama Pelanggan</th>
                        <th class="text-center" style="width:10%;">Status</th>
                        <th style="width:30%;">Detail Item</th> <th class="text-right" style="width:10%;">Total Harga</th>
                        <th style="width:15%;">Waktu & Metode</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $nomor_urut = 1;
                    foreach ($results_laporan as $row_laporan):
                        $total_harga_laporan_rupiah = "Rp " . number_format($row_laporan["total_harga"] ?? 0, 0, ',', '.');
                        $nama_pelanggan_laporan = htmlspecialchars($row_laporan["nama_pelanggan"] ?? 'N/A');
                        $id_meja_laporan = htmlspecialchars($row_laporan["id_meja"] ?? 'N/A');
                        $status_laporan = htmlspecialchars(ucfirst(str_replace('_', ' ', $row_laporan["status"] ?? 'N/A')));
                    ?>
                    <tr>
                        <td class="text-center"><?php echo $nomor_urut++; ?></td>
                        <td class="text-center"><?php echo htmlspecialchars($row_laporan["id_pesanan"]); ?></td>
                        <td class="text-center"><?php echo $id_meja_laporan; ?></td>
                        <td><?php echo $nama_pelanggan_laporan; ?></td>
                        <td class="text-center"><?php echo $status_laporan; ?></td>
                        <td> <?php if (!empty($row_laporan['items'])): ?>
                            <div class="item-details-container">
                                <ul class="item-list">
                                    <?php foreach ($row_laporan['items'] as $item): ?>
                                    <li>
                                        <span class="item-name"><?php echo htmlspecialchars($item['nama_menu']); ?></span><br>
                                        <span class="item-qty-price">
                                            <?php echo htmlspecialchars($item['jumlah']); ?> x Rp <?php echo number_format($item['harga_satuan'], 0, ',', '.'); ?>
                                        </span>
                                        <span class="item-subtotal">= Rp <?php echo number_format($item['subtotal'], 0, ',', '.'); ?></span>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <?php else: ?>
                                <span style="font-size:0.85em; color:#777;">(Tidak ada detail item)</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right"><?php echo $total_harga_laporan_rupiah; ?></td>
                        <td>
                            <?php echo htmlspecialchars(date("d/m/y H:i", strtotime($row_laporan["waktu_pesan"] ?? time()))); ?><br>
                            <span style="font-size:0.9em; color:#555;"><?php echo htmlspecialchars($row_laporan["metode_pembayaran"] ?? 'N/A'); ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="summary-section">
                <h3>Ringkasan Laporan</h3>
                <p>Total Jumlah Pesanan: <strong><?php echo $jumlah_pesanan_laporan; ?></strong></p>
                <p>Total Estimasi Pendapatan (dari pesanan 'Selesai'): <strong>Rp <?php echo number_format($total_pendapatan_laporan, 0, ',', '.'); ?></strong></p>
            </div>
        <?php else: ?>
            <div class="alert alert-info" role="alert">
                Silakan pilih tanggal atau bulan untuk menampilkan laporan.
            </div>
        <?php endif; ?>
    </div>

    <?php
    if (isset($conn) && $conn) $conn->close();
    ?>
</body>
</html>