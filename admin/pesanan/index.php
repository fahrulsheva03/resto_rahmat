<?php
session_start();

// Sertakan file koneksi.php (sesuaikan jalur jika diperlukan)
include '../koneksi.php';

// --- Handle AJAX POST Request for Status Update ---
// Logika ini menangani pembaruan status pesanan melalui AJAX dari tabel
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id_pesanan']) && isset($_POST['status'])) {
    // Memastikan pengguna sudah login sebelum memproses update
    if (!isset($_SESSION['username'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Autentikasi dibutuhkan. Silakan login kembali.']);
        if (isset($conn) && $conn) $conn->close();
        exit();
    }

    $id_pesanan = $_POST['id_pesanan'];
    $newStatus = $_POST['status'];
    $allowed_statuses = ['pending', 'diproses', 'selesai', 'dibatalkan'];

    // Validasi status baru
    if (!in_array($newStatus, $allowed_statuses)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Nilai status tidak valid.']);
        if (isset($conn) && $conn) $conn->close();
        exit();
    }

    $sql_update = "UPDATE pesanan SET status = ? WHERE id_pesanan = ?";
    $stmt_update = $conn->prepare($sql_update);

    if ($stmt_update === false) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Gagal mempersiapkan query database: ' . $conn->error]);
        if (isset($conn) && $conn) $conn->close();
        exit();
    }

    $stmt_update->bind_param("si", $newStatus, $id_pesanan);
    header('Content-Type: application/json'); // Penting untuk respons JSON

    if ($stmt_update->execute()) {
        echo json_encode(['success' => true, 'message' => 'Status berhasil diperbarui.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal mengeksekusi query database: ' . $stmt_update->error]);
    }
    $stmt_update->close();
    if (isset($conn) && $conn) $conn->close();
    exit();
}

// --- Handle Delete Request ---
// Logika ini akan dipicu jika ada parameter 'delete_pesanan_id' di URL
if (isset($_GET['delete_pesanan_id'])) {
    if (!isset($_SESSION['username'])) {
        header("Location: ../auth/index.php?login_required=true");
        exit();
    }

    $delete_id = $_GET['delete_pesanan_id'];
    $sql_delete = "DELETE FROM pesanan WHERE id_pesanan = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    $delete_error = null;

    if ($stmt_delete === false) {
        $delete_error = "Gagal mempersiapkan query delete: " . $conn->error;
    } else {
        $stmt_delete->bind_param("i", $delete_id);
        if (!$stmt_delete->execute()) {
            $delete_error = "Gagal mengeksekusi delete: " . $stmt_delete->error;
        }
        $stmt_delete->close();
    }

    // Menampilkan SweetAlert berdasarkan hasil operasi delete
    if ($delete_error === null) {
        echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({ title: 'Berhasil!', text: 'Pesanan berhasil dihapus!', icon: 'success', confirmButtonText: 'OK' }).then(() => { let currentUrlParams = new URLSearchParams(window.location.search); currentUrlParams.delete('delete_pesanan_id'); let baseUrl = window.location.pathname; window.location.href = baseUrl + (currentUrlParams.toString() ? '?' + currentUrlParams.toString() : ''); }); });</script>";
    } else {
        echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({ title: 'Error!', text: '" . htmlspecialchars($delete_error) . "', icon: 'error', confirmButtonText: 'OK' }); });</script>";
    }
    // Koneksi akan ditutup di akhir script
}


// --- Handle Regular Page Load / Display Logic ---

// Periksa apakah pengguna sudah login
if (!isset($_SESSION['username'])) {
    header("Location: ../auth/index.php?login_required=true");
    exit();
}
$username = $_SESSION['username'];
$role = $_SESSION['role'];

// --- Logika Paginasi, Pencarian, Pengurutan ---
$rows_per_page = 10;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page = max(1, $current_page); // Pastikan halaman tidak kurang dari 1

$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : '';
$order_by = 'p.id_pesanan DESC'; // Urutan default

// Mengatur urutan berdasarkan pilihan sorting
switch ($sort_by) {
    case 'waktu_desc': $order_by = 'p.waktu_pesan DESC'; break;
    case 'waktu_asc': $order_by = 'p.waktu_pesan ASC'; break;
    case 'total_harga_asc': $order_by = 'p.total_harga ASC'; break;
    case 'total_harga_desc': $order_by = 'p.total_harga DESC'; break;
    case 'status_asc': $order_by = 'p.status ASC, p.id_pesanan DESC'; break;
    case 'id_meja_asc': $order_by = 'p.id_meja ASC, p.id_pesanan DESC'; break;
    case 'id_meja_desc': $order_by = 'p.id_meja DESC, p.id_pesanan DESC'; break;
    case 'nama_pelanggan_asc': $order_by = 'pl.nama ASC, p.id_pesanan DESC'; break;
    default: $order_by = 'p.id_pesanan DESC'; $sort_by = ''; // Reset sort_by jika tidak valid
}

// Helper function untuk kapitalisasi huruf pertama dan sanitasi HTML
function ucfirst_safe_index($string) {
    if (!is_string($string)) return $string;
    $decoded = htmlspecialchars_decode($string ?? '', ENT_QUOTES);
    return htmlspecialchars(ucfirst($decoded), ENT_QUOTES);
}

// --- Inisialisasi variabel error dan hasil SELECT ---
$select_error_msg = null;
$result_select = null;
$total_rows = 0;

// Basis query untuk operasi SELECT dan COUNT
$base_query_select = "
    SELECT
        p.id_pesanan, p.id_meja, p.id_pelanggan, p.status, p.total_harga, p.waktu_pesan, p.metode_pembayaran,
        pl.nama AS nama_pelanggan
    FROM
        pesanan p
    LEFT JOIN
        pelanggan pl ON p.id_pelanggan = pl.id_pelanggan
";
$base_query_count = "
    FROM
        pesanan p
    LEFT JOIN
        pelanggan pl ON p.id_pelanggan = pl.id_pelanggan
    LEFT JOIN
        meja m ON p.id_meja = m.id_meja
";

// Membangun klausa WHERE untuk pencarian
$where_clause = "";
$bindings = [];
$binding_types = "";
$search_like = '%' . $search_term . '%';

if (!empty($search_term)) {
    $searchable_cols = [
        'p.id_pesanan', 'p.id_meja', 'p.status',
        'p.waktu_pesan', 'p.metode_pembayaran',
        'pl.nama'
    ];

    $where_parts = [];
    $current_bindings = [];
    $current_binding_types = "";

    foreach ($searchable_cols as $col) {
        $where_parts[] = "$col LIKE ?";
        $current_bindings[] = $search_like;
        $current_binding_types .= "s";
    }

    if (!empty($where_parts)) {
        $where_clause = " WHERE " . implode(" OR ", $where_parts);
        $bindings = $current_bindings;
        $binding_types = $current_binding_types;
    }
}

// --- Query COUNT (*) untuk paginasi ---
$sql_count = "SELECT COUNT(*) AS total_rows " . $base_query_count . $where_clause;
$stmt_count = $conn->prepare($sql_count);
if ($stmt_count === false) {
    $select_error_msg = "COUNT Prepare failed: " . $conn->error;
} else {
    // Bind parameter untuk query COUNT
    if (!empty($bindings)) {
        // Menggunakan call_user_func_array untuk bind_param dengan array dinamis
        $bind_params_count = [$binding_types];
        foreach($bindings as &$bind_value){
            $bind_params_count[] = &$bind_value;
        }
        if (!call_user_func_array([$stmt_count, 'bind_param'], $bind_params_count)) {
            $select_error_msg = "COUNT Bind param failed: " . $stmt_count->error;
        }
    }

    if ($select_error_msg === null && !$stmt_count->execute()) {
        $select_error_msg = "COUNT Execute failed: " . $stmt_count->error;
    } else if ($select_error_msg === null) {
        $result_count = $stmt_count->get_result();
        if($result_count) {
            $row_count_assoc = $result_count->fetch_assoc();
            $total_rows = $row_count_assoc ? $row_count_assoc['total_rows'] : 0;
        } else {
            $select_error_msg = "COUNT Get result failed: " . $conn->error;
        }
    }
    if ($stmt_count) $stmt_count->close();
}

// Hitung total halaman, validasi current_page, dan offset untuk LIMIT
$total_pages = ($rows_per_page > 0 && $total_rows > 0) ? ceil($total_rows / $rows_per_page) : 1;
$total_pages = max(1, $total_pages);
$current_page = min($current_page, $total_pages);
$current_page = max(1, $current_page);
$offset = ($current_page - 1) * $rows_per_page;
$offset = max(0, $offset); // Pastikan offset tidak negatif

// --- Query SELECT Data untuk tampilan tabel ---
$sql_select = $base_query_select . $where_clause . " ORDER BY " . $order_by . " LIMIT ?, ?";
$stmt_select = $conn->prepare($sql_select);
$result_select = null; // Inisialisasi null

if ($stmt_select === false) {
    $select_error_msg = "SELECT Prepare failed: " . $conn->error;
} else {
    $select_bindings = $bindings;
    $select_bindings[] = $offset;
    $select_bindings[] = $rows_per_page;
    $select_binding_types = $binding_types . "ii"; // Tambah "ii" untuk offset dan limit

    if (!empty($select_bindings)) {
        $bind_params_select = [$select_binding_types];
        foreach($select_bindings as &$bind_value){
            $bind_params_select[] = &$bind_value;
        }
        if (!call_user_func_array([$stmt_select, 'bind_param'], $bind_params_select)) {
            $select_error_msg = "SELECT Bind param failed: " . $stmt_select->error;
        }
    } else {
        // Fallback jika tidak ada bindings (tidak ada search term), hanya offset dan limit
        $stmt_select->bind_param("ii", $offset, $rows_per_page);
    }

    if ($select_error_msg === null && !$stmt_select->execute()) {
        $select_error_msg = "SELECT Execute failed: " . $stmt_select->error;
        $result_select = null;
    } else if ($select_error_msg === null) {
        $result_select = $stmt_select->get_result();
        if($result_select === false) {
            $select_error_msg = "SELECT Get result failed: " . $conn->error;
            $result_select = null;
        }
    }
    if ($stmt_select) $stmt_select->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Resto-Rahmat - Pesanan</title>
    <link rel="stylesheet" href="../assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../assets/vendors/flag-icon-css/css/flag-icon.min.css">
    <link rel="stylesheet" href="../assets/vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="../assets/css/demo_2/style.css" />

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="shortcut icon" href="../assets/images/logo.png" />

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">


    <style>
        /* CSS for the status badge in the table and modal */
        .status-display {
            display: inline-block; padding: .25em .5em; font-size: 75%; font-weight: bold;
            line-height: 1; color: #fff; text-align: center; white-space: nowrap;
            vertical-align: baseline; border-radius: .25rem;
            cursor: pointer; /* Dapat diklik di tabel */
            transition: background-color 0.3s ease, opacity 0.3s ease;
        }
        /* Kursor default di modal */
        #pesananDetailSidebar .status-display { cursor: default; }
        /* Hover effect di tabel */
        .status-display.status-clickable:hover { opacity: 0.9; }

        /* Warna badge berdasarkan status */
        .status-pending { background-color: #ffc107; }
        .status-diproses { background-color: #17a2b8; }
        .status-selesai { background-color: #28a745; }
        .status-dibatalkan { background-color: #dc3545; }


        /* Custom CSS for modal that slides from the right */
        #pesananDetailSidebar .modal-dialog {
            /* Ini yang membuat modal muncul dari kanan dan mengisi tinggi */
            max-width: 450px; /* Atur lebar maksimum modal */
            width: 95%; /* Pastikan lebarnya proporsional di layar lebih kecil */
            margin: 0 0 0 auto !important; /* Dorong ke kanan menggunakan Bootstrap auto-margin override */
            margin-top: 0 !important; /* Hilangkan margin vertikal */
            margin-bottom: 0 !important; /* Hilangkan margin vertikal */
            height: 100vh; /* Ambil seluruh tinggi viewport */
            /* Transform digunakan untuk animasi slide dengan Bootstrap fade class */
            transform: translate(0, 0) !important; /* Posisi awal saat tampil */
            transition: transform .3s ease-out; /* <<< Ini mengaktifkan transisi geser */
        }
        /* Transisi untuk efek slide */
        #pesananDetailSidebar.fade .modal-dialog { transform: translate(100%, 0); transition: transform .3s ease-out; } /* Slide keluar ke kanan */
        #pesananDetailSidebar.show .modal-dialog { transform: translate(0, 0); } /* Slide masuk */

        /* Modal Content and Body styles */
        #pesananDetailSidebar .modal-content { height: 100%; border-radius: 0; } /* Konten mengisi tinggi dialog, tidak ada sudut membulat */
        #pesananDetailSidebar .modal-body { padding: 1.5rem; overflow-y: auto; } /* Tambah padding dan scroll jika konten panjang */

        /* Style for item list in modal (Standard CSS + Bootstrap utilities) */
        #pesananDetailSidebar .modal-body .list-unstyled { /* Targeting ul dengan class list-unstyled Bootstrap */
            margin: 0 !important; padding: 0 !important; /* Reset ul default margin/padding */
        }
         #pesananDetailSidebar .modal-body .list-unstyled li { /* Gaya setiap item li */
            display: flex; /* Gunakan flexbox Bootstrap */
            justify-content: space-between; /* Atur jarak konten */
            align-items: center; /* Rata tengah vertikal */
            padding-top: 8px; padding-bottom: 8px; /* Spasi vertikal */
            border-bottom: 1px solid #eee; /* Divider line antar item */
           }
           #pesananDetailSidebar .modal-body .list-unstyled li:last-child {
            border-bottom: none; /* Hapus border di item terakhir */
           }

           #pesananDetailSidebar .modal-body .list-unstyled li .flex-grow-1 { /* Bagian kiri item list */
            flex-grow: 1;
            margin-right: 15px; /* Spasi antara bagian kiri dan kanan */
           }
           #pesananDetailSidebar .modal-body .list-unstyled li p {
            margin-bottom: 0; /* Hapus margin bawah default paragraf */
            }
           #pesananDetailSidebar .modal-body .list-unstyled li small {
            display: block; /* Biar di baris baru */
            font-size: 0.85rem; /* Ukuran sedikit lebih besar dari 0.8rem */
            color: #555; /* Warna abu-abu muted */
           }
           #pesananDetailSidebar .modal-body .list-unstyled li .font-weight-bold { /* Menggunakan class Bootstrap untuk bold font */
            font-weight: bold;
           }


        /* --- CSS OVERRIDE untuk SweetAlert2 INPUT RADIO (PENTING!) --- */
        /* Menjamin style SweetAlert terlihat benar meskipun style template konflik */
        .swal2-container .swal2-radio { display: block !important; margin: 1em auto !important; padding: 0 !important; width: auto !important; box-sizing: border-box !important; text-align: left !important; }
        .swal2-container .swal2-radio label { display: flex !important; align-items: center !important; margin: 0.5em 0 !important; padding: 0.5em !important; box-sizing: border-box !important; cursor: pointer !important; color: inherit !important; background-color: transparent !important; border: none !important; }
         .swal2-container .swal2-radio label:hover { background-color: #f0f0f0 !important; }
         .swal2-container .swal2-radio input[type="radio"] { flex-shrink: 0 !important; margin-right: 0.75em !important; margin-left: 0 !important; padding: 0 !important; width: auto !important; height: auto !important; display: inline-block !important; box-sizing: border-box !important; vertical-align: middle !important; -webkit-appearance: radio !important; -moz-appearance: radio !important; appearance: radio !important; }
         .swal2-container .swal2-radio span { flex-grow: 1 !important; line-height: 1.5 !important; vertical-align: middle !important; }

         /* Optional: Atur style tombol SweetAlert agar cocok */
         .swal2-container .swal2-confirm { background-color: #007bff !important; color: white !important; border-radius: .25rem !important; } /* Bootstrap primary blue */
         .swal2-container .swal2-cancel { background-color: #6c757d !important; color: white !important; border-radius: .25rem !important; } /* Bootstrap secondary grey */
         .swal2-popup .swal2-actions button { margin: 0 0.3125em !important; } /* Spasi antar tombol */
         .swal2-popup { width: auto !important; max-width: 400px !important; } /* Lebar max modal SweetAlert */

         /* Gaya untuk memastikan badge status di modal detail terlihat jelas */
         #pesananDetailSidebar .modal-body .status-display {
             font-size: 90%; /* Slightly larger in modal than table */
             padding: .25em .6em;
             cursor: default; /* Not clickable in modal */
             opacity: 1 !important; /* Full opacity */
             pointer-events: none; /* Not clickable */
         }
    </style>
</head>

<body>
    <div class="container-scroller">

        <?php include '../partials/_navbar.php' // PASTIKAN JALUR INI BENAR ?>

        <div class="container-fluid page-body-wrapper">
            <div class="main-panel">
                <div class="content-wrapper">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                        <div class="header-left">
                            <h3>Data Pesanan</h3>
                        </div>

                        <div class="d-flex align-items-center">
                            <select id="sortSelect" class="form-control form-control-sm mr-2" onchange="applyFilterSort()" style="width: 200px;">
                                <option value="">-- Urutkan --</option>
                                <option value="waktu_desc" <?php if ($sort_by == 'waktu_desc') echo 'selected'; ?>>Waktu Pesan Terbaru</option>
                                <option value="waktu_asc" <?php if ($sort_by == 'waktu_asc') echo 'selected'; ?>>Waktu Pesan Terlama</option>
                                <option value="total_harga_asc" <?php if ($sort_by == 'total_harga_asc') echo 'selected'; ?>>Total Harga Terendah</option>
                                <option value="total_harga_desc" <?php if ($sort_by == 'total_harga_desc') echo 'selected'; ?>>Total Harga Tertinggi</option>
                                <option value="status_asc" <?php if ($sort_by == 'status_asc') echo 'selected'; ?>>Status A-Z</option>
                                <option value="id_meja_asc" <?php if ($sort_by == 'id_meja_asc') echo 'selected'; ?>>ID Meja Menaik</option>
                                <option value="id_meja_desc" <?php if ($sort_by == 'id_meja_desc') echo 'selected'; ?>>ID Meja Menurun</option>
                                <option value="nama_pelanggan_asc" <?php if ($sort_by == 'nama_pelanggan_asc') echo 'selected'; ?>>Nama Pelanggan A-Z</option>
                            </select>
                            <div class="input-group input-group-sm mr-2" style="width: 250px;">
                                <input type="text" id="searchInput" class="form-control" placeholder="Cari pesanan..." value="<?php echo htmlspecialchars($search_term); ?>" onkeypress="handleSearchKey(event)">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="button" onclick="applyFilterSort()">Cari</button>
                                    <button class="btn btn-danger" type="button" onclick="clearFilterSort()">Batal</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-body">
                            <h4 class="card-title">Laporan Pesanan</h4>
                            <div class="d-flex flex-column flex-md-row justify-content-start align-items-start align-items-md-center">
                                <form id="laporanBulananForm" method="GET" action="laporan_pesanan.php" target="_blank" class="form-inline mb-2 mr-md-3 mb-md-0">
                                    <div class="form-group mr-2">
                                        <label for="inputBulanLaporan" class="sr-only">Pilih Bulan</label>
                                        <input type="month" class="form-control form-control-sm" id="inputBulanLaporan" name="bulan" required>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-success">
                                        <i class="fas fa-print"></i> Cetak Laporan Bulanan
                                    </button>
                                </form>

                                <form id="laporanHarianForm" method="GET" action="laporan_pesanan.php" target="_blank" class="form-inline">
                                    <div class="form-group mr-2">
                                        <label for="inputTanggalLaporan" class="sr-only">Pilih Tanggal</label>
                                        <input type="date" class="form-control form-control-sm" id="inputTanggalLaporan" name="tanggal" required>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        <i class="fas fa-print"></i> Cetak Laporan Harian
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <?php
                    // Tampilkan error jika terjadi saat query data
                    if ($select_error_msg !== null && trim($select_error_msg) !== '') {
                        echo "<div class='alert alert-danger' role='alert'>" . htmlspecialchars($select_error_msg) . "</div>";
                    } else { // Tampilkan tabel hanya jika tidak ada error SELECT
                    ?>
                    <div class='grid-margin stretch-card'>
                        <div class='card'>
                            <div class='card-body'>
                                <div class='table-responsive'>
                                    <table class='table table-hover' id='pesananTable'>
                                        <thead class='text-center'>
                                            <tr><th>NO</th><th>ID Pesanan</th><th>ID Meja</th><th>Nama Pelanggan</th><th>Status</th><th>Total Harga</th><th>Waktu Pesan</th><th>Metode Pembayaran</th><th>Action</th></tr>
                                        </thead>
                                        <tbody class='text-center'>
                                            <?php
                                            // Pastikan $result_select valid dan memiliki baris
                                            if ($result_select && ($result_select instanceof mysqli_result) && $result_select->num_rows > 0) {
                                                $row_number = $offset + 1;
                                                while ($row = $result_select->fetch_assoc()) {
                                                    $total_harga_rupiah = "Rp " . number_format($row["total_harga"] ?? 0, 0, ',', '.');
                                                    // Ambil nama pelanggan dari hasil JOIN (alias nama_pelanggan)
                                                    $nama_pelanggan_tampil = htmlspecialchars($row["nama_pelanggan"] ?? 'N/A');
                                                    // Ambil ID Meja dari tabel pesanan langsung
                                                    $id_meja_tampil = htmlspecialchars($row["id_meja"] ?? 'N/A');

                                                    // Status (untuk badge/button klikable)
                                                    $current_status_value = htmlspecialchars($row["status"] ?? ''); // Nilai mentah (pending, diproses)
                                                    $status_text_display = ucfirst_safe_index(str_replace('_', ' ', $row["status"] ?? 'N/A')); // Agar 'diproses' jadi 'Diproses'


                                                    $status_class_display = 'status-' . strtolower($current_status_value); // Class untuk warna (pastikan lowercase)

                                                    echo "<tr>";
                                                    echo "<td>" . $row_number . "</td>";
                                                    echo "<td>" . htmlspecialchars($row["id_pesanan"]) . "</td>";
                                                    echo "<td>" . $id_meja_tampil . "</td>";
                                                    echo "<td>" . $nama_pelanggan_tampil . "</td>";

                                                    // Kolom Status (badge/button klikable)
                                                    echo "<td>";
                                                    // Tambahkan data-status untuk menyimpan nilai mentah status
                                                    echo "<span class='status-display status-clickable " . $status_class_display . "' data-id='" . htmlspecialchars($row["id_pesanan"]) . "' data-status='" . $current_status_value . "'>";
                                                    echo $status_text_display; // Tampilkan teks status (sudah html-escaped)
                                                    echo "</span>";
                                                    echo "</td>";

                                                    echo "<td>" . $total_harga_rupiah . "</td>";
                                                    echo "<td>" . htmlspecialchars($row["waktu_pesan"] ?? 'N/A') . "</td>";
                                                    echo "<td>" . htmlspecialchars($row["metode_pembayaran"] ?? 'N/A') . "</td>";
                                                    echo "<td>
                                                            <button class='btn btn-sm btn-info detail-btn mr-1' data-id='" . htmlspecialchars($row["id_pesanan"]) . "' type='button'>
                                                                <i class='fas fa-info-circle'></i> Detail
                                                            </button>
                                                            </td>";
                                                    echo "</tr>";
                                                    $row_number++;
                                                }
                                            } else {
                                                if ($result_select === null || $result_select === false) {
                                                    echo "<tr><td colspan='9'><div class='alert alert-warning m-2' role='alert'>Gagal memuat data pesanan.</div></td></tr>";
                                                } else {
                                                    echo "<tr><td colspan='9'><div class='alert alert-info m-2 text-center' role='alert'>Tidak ada data pesanan ditemukan" . (!empty($search_term) ? " untuk pencarian '" . htmlspecialchars($search_term) . "'" : "") . ".</div></td></tr>";
                                                }
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if ($total_pages > 1) { ?>
                                <nav aria-label="Page navigation">
                                    <ul class="pagination justify-content-center mt-4">
                                        <li class="page-item <?php if ($current_page <= 1) echo 'disabled'; ?>">
                                            <a class="page-link" href="?page=<?php echo max(1, $current_page - 1); echo (!empty($search_term) ? "&search=" . urlencode($search_term) : ''); echo (!empty($sort_by) ? "&sort=" . urlencode($sort_by) : ''); ?>" <?php if ($current_page <= 1) echo 'tabindex="-1" aria-disabled="true"'; ?>>Previous</a>
                                        </li>
                                        <?php $links_per_side = 2; $start_page_link = max(1, $current_page - $links_per_side); $end_page_link = min($total_pages, $current_page + $links_per_side);
                                        if ($start_page_link > 1) { echo '<li class="page-item"><a class="page-link" href="?page=1'; echo (!empty($search_term) ? "&search=" . urlencode($search_term) : ''); echo (!empty($sort_by) ? "&sort=" . urlencode($sort_by) : ''); echo '">1</a></li>'; if ($start_page_link > 2) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; } }
                                        for ($i = $start_page_link; $i <= $end_page_link; $i++) { ?> <li class="page-item <?php if ($i == $current_page) echo 'active'; ?>"> <a class="page-link" href="?page=<?php echo $i; echo (!empty($search_term) ? "&search=" . urlencode($search_term) : ''); echo (!empty($sort_by) ? "&sort=" . urlencode($sort_by) : ''); ?>"><?php echo $i; ?></a> </li> <?php }
                                        if ($end_page_link < $total_pages) { if ($end_page_link < $total_pages - 1) { echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; } echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages; echo (!empty($search_term) ? "&search=" . urlencode($search_term) : ''); echo (!empty($sort_by) ? "&sort=" . urlencode($sort_by) : ''); echo '">' . $total_pages . '</a></li>'; }
                                        ?>
                                        <li class="page-item <?php if ($current_page >= $total_pages) echo 'disabled'; ?>">
                                            <a class="page-link" href="?page=<?php echo min($total_pages, $current_page + 1); echo (!empty($search_term) ? "&search=" . urlencode($search_term) : ''); echo (!empty($sort_by) ? "&sort=" . urlencode($sort_by) : ''); ?>" <?php if ($current_page >= $total_pages) echo 'tabindex="-1" aria-disabled="true"'; ?>>Next</a>
                                        </li>
                                    </ul>
                                </nav>
                                <?php } ?>
                                <?php if($total_rows >= 0){ ?> <div class="text-center mt-2"> <small>Menampilkan <?php echo ($result_select && ($result_select instanceof mysqli_result) ? $result_select->num_rows : 0); ?> dari <?php echo $total_rows; ?> total pesanan<?php echo (!empty($search_term) ? " untuk pencarian '" . htmlspecialchars($search_term) . "'" : ""); ?>.</small> </div> <?php } ?>

                            </div>
                        </div>
                    </div>
                    <?php } ?>

                </div>
                <div class="modal fade" id="pesananDetailSidebar" tabindex="-1" aria-labelledby="pesananDetailSidebarLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="pesananDetailSidebarLabel">Detail Pesanan</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">×</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <p class="text-center"><i class="fas fa-spinner fa-spin mr-2"></i>Memuat...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <script src="../assets/vendors/js/vendor.bundle.base.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/js/off-canvas.js"></script>
    <script src="../assets/js/hoverable-collapse.js"></script>
    <script src="../assets/js/misc.js"></script>
    <script src="../assets/js/settings.js"></script>
    <script src="../assets/js/todolist.js"></script>
    <script>
        // Function untuk konfirmasi penghapusan pesanan
        function confirmDelete(id) {
            Swal.fire({
                title: 'Apakah Anda yakin?',
                text: "Pesanan ini akan dihapus secara permanen!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Hapus parameter delete_pesanan_id yang mungkin ada di URL sebelum redirect
                    let currentUrlParams = new URLSearchParams(window.location.search);
                    currentUrlParams.delete('delete_pesanan_id');
                    let baseUrl = window.location.pathname;
                    // Redirect dengan ID pesanan untuk dihapus, serta parameter filter/sort yang ada
                    window.location.href = baseUrl + '?delete_pesanan_id=' + id + '&' + currentUrlParams.toString();
                }
            });
        }
        window.confirmDelete = confirmDelete; // Membuat fungsi ini dapat diakses secara global

        // Helper function untuk kapitalisasi huruf pertama dan sanitasi HTML pada sisi JS
        function jsUcfirst(string) {
            if (!string) return '';
            string = String(string).replace(/_/g, ' '); // Ganti underscore dengan spasi
            const parser = new DOMParser();
            // Gunakan DOMParser untuk decode entitas HTML dasar, lalu textContent untuk escape lagi
            const decoded = parser.parseFromString(`<!doctype html><body>${string}`, 'text/html').body.textContent || string;
            const capitalized = decoded.charAt(0).toUpperCase() + decoded.slice(1);
            const tempDiv = document.createElement('div');
            tempDiv.textContent = capitalized; // Gunakan textContent untuk sanitasi dasar
            return tempDiv.innerHTML; // Dapatkan string yang telah disanitasi
        }


        // --- JavaScript untuk Filter, Sort, dan Paginasi (menggunakan full page reload) ---
        function applyFilterSort() {
            const searchInput = document.getElementById("searchInput").value;
            const sortSelect = document.getElementById("sortSelect").value;
            const currentPage = <?php echo $current_page; ?>;

            let params = new URLSearchParams(window.location.search);

            const urlSearch = params.get('search') || '';
            const urlSort = params.get('sort') || '';

            // Jika pencarian atau pengurutan berubah, reset ke halaman 1
            if (searchInput !== urlSearch || sortSelect !== urlSort) {
                params.set('page', 1);
            }

            if (searchInput) {
                params.set('search', searchInput);
            } else {
                params.delete('search');
            }

            if (sortSelect && sortSelect !== '') {
                params.set('sort', sortSelect);
            } else {
                params.delete('sort');
            }

            // Memastikan parameter 'page' selalu ada jika bukan halaman 1, atau dihapus jika halaman 1 dan tidak ada parameter lain
            let pageParam = params.get('page');
            if (pageParam === '1' && !params.has('search') && !params.has('sort')) {
                params.delete('page');
            } else if (!pageParam) {
                params.set('page', 1); // Default ke halaman 1 jika belum diatur
            }

            let queryString = params.toString();
            let newUrl = window.location.pathname + (queryString ? '?' + queryString : '');
            window.location.href = newUrl;
        }

        // Fungsi untuk menghapus semua filter dan pengurutan
        function clearFilterSort() {
            // Cukup kembali ke URL dasar tanpa parameter apapun
            window.location.href = window.location.pathname;
        }

        // Menangani penekanan tombol Enter pada input pencarian
        function handleSearchKey(event) {
            if (event.key === 'Enter') {
                event.preventDefault(); // Mencegah form submit default
                applyFilterSort();
            }
        }

        // Membuat fungsi-fungsi ini dapat diakses secara global
        window.applyFilterSort = applyFilterSort;
        window.clearFilterSort = clearFilterSort;


        // --- Logika Utama DOMContentLoaded (Modal Detail & Ubah Status) ---
        document.addEventListener('DOMContentLoaded', function() {

            // Mendapatkan elemen modal dan bagian tubuh modal
            const pesananDetailSidebarElement = document.getElementById('pesananDetailSidebar');
            let pesananDetailSidebarModal = null;
            const modalBody = pesananDetailSidebarElement ? pesananDetailSidebarElement.querySelector('.modal-body') : null;
            const tableBody = document.querySelector("#pesananTable tbody");

            // Inisialisasi instance Bootstrap Modal
            if (pesananDetailSidebarElement) {
                try {
                    // Deteksi Bootstrap 5+ (jika ada `bootstrap.Modal`) atau Bootstrap 4 (jika ada `jQuery().modal`)
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        pesananDetailSidebarModal = new bootstrap.Modal(pesananDetailSidebarElement, { keyboard: true, backdrop: true });
                    } else if (typeof jQuery !== 'undefined' && $(pesananDetailSidebarElement).modal) {
                        pesananDetailSidebarModal = $(pesananDetailSidebarElement).modal({ show: false, keyboard: true, backdrop: true });
                    } else {
                        console.error("Bootstrap Modal JS tidak ditemukan atau gagal diinisialisasi!");
                    }
                } catch(e) {
                    console.error("Error saat menginisialisasi Bootstrap Modal:", e);
                }
            } else {
                console.error("Elemen modal #pesananDetailSidebar tidak ditemukan!");
            }


            // --- Penanganan Klik Tombol Detail (Membuka Modal Sidebar) ---
            if (tableBody && modalBody && pesananDetailSidebarModal) {
                tableBody.addEventListener('click', function(event) {
                    const detailButton = event.target.closest('.detail-btn');
                    if (detailButton) {
                        event.preventDefault(); // Mencegah aksi default tombol
                        const pesananId = detailButton.getAttribute('data-id');

                        // Tampilkan loading spinner di modal
                        modalBody.innerHTML = '<p class="text-center"><i class="fas fa-spinner fa-spin mr-2"></i>Memuat...</p>';

                        // Tampilkan modal
                        if (typeof bootstrap !== 'undefined' && bootstrap.Modal && pesananDetailSidebarModal.show) { // Bootstrap 5+
                             pesananDetailSidebarModal.show();
                        } else if (typeof jQuery !== 'undefined' && $(pesananDetailSidebarElement).modal) { // Bootstrap 4
                            $(pesananDetailSidebarElement).modal('show');
                        } else {
                             console.error("Tidak dapat menampilkan modal detail.");
                             Swal.fire('Error', 'Gagal menampilkan modal detail.', 'error');
                             return;
                        }

                        // Ambil detail pesanan via AJAX
                        fetch('get_pesanan_detail.php?id=' + encodeURIComponent(pesananId)) // PASTIKAN FILE get_pesanan_detail.php ADA DAN BENAR
                        .then(response => {
                            if (!response.ok) {
                                // Jika respons bukan OK, coba parse sebagai JSON untuk pesan error
                                const contentType = response.headers.get("content-type");
                                if (contentType && contentType.indexOf("application/json") !== -1) {
                                    return response.json().then(err => {
                                        throw new Error(err.message || `Server error ${response.status}.`);
                                    });
                                } else {
                                    // Jika bukan JSON, log respons mentah dan lempar error generik
                                    response.text().then(text => console.error("Non-JSON body:", text));
                                    throw new Error(`HTTP error! Status: ${response.status}`);
                                }
                            }
                            return response.text(); // Mengasumsikan respons adalah HTML
                        })
                        .then(htmlContent => {
                            modalBody.innerHTML = htmlContent; // Masukkan konten HTML ke modal
                        })
                        .catch(error => {
                            console.error('Fetch Error Detail:', error);
                            modalBody.innerHTML = `<div class="alert alert-danger" role='alert'>Gagal memuat detail pesanan:<br>${error.message}</div>`;
                        });
                    }
                }); // Akhir dari event listener detailButton click

                // --- Penanganan Klik Badge Status (Membuka SweetAlert untuk Ubah Status) ---
                tableBody.addEventListener('click', function(event) {
                    const statusSpan = event.target.closest('.status-clickable');
                    if (statusSpan) {
                        event.preventDefault(); // Mencegah aksi default span
                        const pesananId = statusSpan.getAttribute('data-id');
                        const currentStatus = statusSpan.getAttribute('data-status'); // Dapatkan nilai status mentah

                        const statusOptions = {
                            'pending': 'Pending',
                            'diproses': 'Diproses',
                            'selesai': 'Selesai',
                            'dibatalkan': 'Dibatalkan'
                        };

                        Swal.fire({
                            title: 'Ubah Status Pesanan #' + pesananId,
                            input: 'radio',
                            inputOptions: statusOptions,
                            inputValue: currentStatus, // Menandai opsi yang sedang aktif
                            showCancelButton: true,
                            confirmButtonText: 'Simpan',
                            cancelButtonText: 'Batal',
                            showLoaderOnConfirm: true,
                            reverseButtons: true,
                            allowOutsideClick: () => !Swal.isLoading(),
                            inputValidator: (value) => {
                                if (!value) {
                                    return 'Anda perlu memilih salah satu status!';
                                }
                                if (!Object.keys(statusOptions).includes(value)) {
                                    return 'Nilai status yang dipilih tidak valid!';
                                }
                                return null;
                            },
                            preConfirm: (newStatus) => {
                                // Nonaktifkan badge sementara proses update
                                statusSpan.style.opacity = '0.5';
                                statusSpan.style.pointerEvents = 'none';

                                // Kirim permintaan AJAX untuk update status
                                return fetch('index.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded',
                                        'X-Requested-With': 'XMLHttpRequest' // Menandakan ini adalah permintaan AJAX
                                    },
                                    body: `id_pesanan=${encodeURIComponent(pesananId)}&status=${encodeURIComponent(newStatus)}`
                                })
                                .then(response => {
                                    if (!response.ok) {
                                        const contentType = response.headers.get("content-type");
                                        if (contentType && contentType.indexOf("application/json") !== -1) {
                                            return response.json().then(err => {
                                                throw new Error(err.message || `Server error ${response.status}.`);
                                            });
                                        } else {
                                            response.text().then(text => console.error("Non-JSON Status Update Response:", text));
                                            throw new Error(`HTTP error! Status: ${response.status}. Response was not JSON.`);
                                        }
                                    }
                                    const contentType = response.headers.get("content-type");
                                    if (contentType && contentType.indexOf("application/json") !== -1) {
                                        return response.json(); // Mengasumsikan respons sukses adalah JSON
                                    } else {
                                        response.text().then(text => console.error("OK non-JSON Status Update Response:", text));
                                        throw new Error('Invalid response from server (expected JSON).');
                                    }
                                })
                                .then(data => {
                                    if (data.success) {
                                        return { ...data, updatedStatusValue: newStatus }; // Sertakan status baru untuk update UI
                                    } else {
                                        throw new Error(data.message || 'Gagal memperbarui status dari server.');
                                    }
                                })
                                .catch(error => {
                                    console.error('Fetch Status Update Error:', error);
                                    Swal.showValidationMessage(`Update gagal: ${error.message || error}`);
                                    return false; // Menghentikan SweetAlert dari penutupan otomatis
                                })
                                .finally(() => {
                                    // Aktifkan kembali badge setelah proses selesai
                                    statusSpan.style.opacity = '1';
                                    statusSpan.style.pointerEvents = 'auto';
                                });
                            }
                        }).then((result) => {
                            if (result.isConfirmed && result.value && result.value.success) {
                                const newStatusValue = result.value.updatedStatusValue; // Dapatkan nilai status baru dari preConfirm
                                const targetSpan = tableBody.querySelector(`.status-clickable[data-id="${pesananId}"]`);
                                if (targetSpan) {
                                    targetSpan.textContent = jsUcfirst(newStatusValue); // Perbarui teks menggunakan helper JS
                                    // Hapus semua kelas status lama dan tambahkan kelas status baru
                                    targetSpan.classList.remove('status-pending', 'status-diproses', 'status-selesai', 'status-dibatalkan');
                                    targetSpan.classList.add('status-' + newStatusValue.toLowerCase());
                                    targetSpan.setAttribute('data-status', newStatusValue); // Perbarui atribut data-status
                                }
                                // Tampilkan notifikasi sukses
                                Swal.fire({ icon: 'success', title: 'Diperbarui!', text: result.value.message || 'Status pesanan telah diperbarui.', showConfirmButton: false, timer: 1500 });
                            } else if (result.dismiss) {
                                /* Modal ditutup oleh pengguna, tidak perlu aksi */
                            }
                            // Kasus jika preConfirm mengembalikan false (error sudah ditangani oleh Swal.showValidationMessage)
                        });
                    }
                });

            } else {
                console.warn("Elemen tabel atau modal tidak ditemukan untuk event listener.");
            }

        }); // Akhir DOMContentLoaded

    </script>
</body>
</html>
<?php
// Tutup koneksi database
if (isset($conn) && $conn) {
    $conn->close();
}
?>