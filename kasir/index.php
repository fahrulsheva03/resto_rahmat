<?php
session_start();

// --- Hardcoded login untuk demo. Ganti dengan sistem autentikasi Anda. ---
// Jika belum ada sesi username, set sesi untuk demo
if (!isset($_SESSION['username'])) {
    $_SESSION['username'] = 'admin'; // Contoh username
    $_SESSION['role'] = 'admin';     // Contoh role
}
// --- Akhir hardcoded login ---

// Sertakan file koneksi.php (sesuaikan jalur jika diperlukan)
include 'koneksi.php';

// --- Handle AJAX POST Request for Status Update ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['id_pesanan']) && isset($_POST['status'])) {
    if (!isset($_SESSION['username'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Autentikasi dibutuhkan. Silakan login kembali.']);
        if (isset($conn) && $conn) $conn->close();
        exit();
    }
    $id_pesanan = $_POST['id_pesanan'];
    $newStatus = $_POST['status'];
    $allowed_statuses = ['pending', 'diproses', 'selesai', 'dibatalkan'];
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
    header('Content-Type: application/json');
    if ($stmt_update->execute()) {
        echo json_encode(['success' => true, 'message' => 'Status berhasil diperbarui.', 'updatedStatusValue' => $newStatus]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal mengeksekusi query database: ' . $stmt_update->error]);
    }
    $stmt_update->close();
    if (isset($conn) && $conn) $conn->close();
    exit();
}

// --- Handle Delete Request ---
if (isset($_GET['delete_pesanan_id'])) {
    if (!isset($_SESSION['username'])) { header("Location: auth/index.php?login_required=true"); exit(); }
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
    // Set SweetAlert message via JavaScript setelah redirect atau di halaman ini
    if ($delete_error === null) {
        $_SESSION['swal_message'] = ['title' => 'Berhasil!', 'text' => 'Pesanan berhasil dihapus!', 'icon' => 'success'];
    } else {
        $_SESSION['swal_message'] = ['title' => 'Error!', 'text' => 'Gagal menghapus pesanan: ' . htmlspecialchars($delete_error), 'icon' => 'error'];
    }
    // Hapus parameter delete_pesanan_id dari URL sebelum redirect
    $current_url_params = $_GET;
    unset($current_url_params['delete_pesanan_id']);
    $redirect_query_string = http_build_query($current_url_params);
    header("Location: index.php" . (!empty($redirect_query_string) ? "?" . $redirect_query_string : ""));
    exit();
}


// --- Handle Regular Page Load / Display Logic ---

// Periksa apakah pengguna sudah login
if (!isset($_SESSION['username'])) {
    header("Location: auth/index.php?login_required=true");
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
$order_by = 'p.id_pesanan DESC'; // Default order

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

// Helper function htmlspecialchars + ucfirst (aman XSS)
function ucfirst_safe_index($string) {
    if (!is_string($string)) return $string;
    $decoded = htmlspecialchars_decode($string ?? '', ENT_QUOTES);
    return htmlspecialchars(ucfirst($decoded), ENT_QUOTES);
}

// --- Inisialisasi variabel error dan result SELECT ---
$select_error_msg = null;
$result_select = null;
$total_rows = 0;

// Basis query untuk SELECT dan COUNT
$base_query_select = "
    SELECT
        p.id_pesanan,
        p.id_meja,
        p.id_pelanggan,
        p.status,
        p.total_harga,
        p.waktu_pesan,
        p.metode_pembayaran,
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

// Buat klausa WHERE untuk pencarian
$where_clause = "";
$bindings = [];
$binding_types = "";
$search_like = '%' . $search_term . '%';
if (!empty($search_term)) {
    $searchable_cols = [
        'p.id_pesanan', 'p.id_meja', 'p.status', 'p.metode_pembayaran', 'pl.nama'
    ]; // Kolom yang dicari string

    $where_parts = [];
    foreach ($searchable_cols as $col) {
        $where_parts[] = "$col LIKE ?";
        $bindings[] = $search_like;
        $binding_types .= "s";
    }

    // Tambahkan pencarian numerik jika search_term adalah angka
    if (is_numeric($search_term)) {
        // Konversi search_term ke integer/decimal untuk pencarian numerik
        $numeric_search_term = (float)$search_term; // Gunakan float untuk total_harga
        $where_parts[] = "p.total_harga = ?";
        $bindings[] = $numeric_search_term;
        $binding_types .= "d"; // d untuk double/decimal

        // Jika Anda ingin juga mencari id_pelanggan atau id_meja berdasarkan angka tepat:
        $where_parts[] = "p.id_pelanggan = ?";
        $bindings[] = (int)$search_term;
        $binding_types .= "i";
    }


    if (!empty($where_parts)) {
        $where_clause = " WHERE " . implode(" OR ", $where_parts);
    }
}

// --- Query COUNT (*) ---
$sql_count = "SELECT COUNT(*) AS total_rows " . $base_query_count . $where_clause;
$stmt_count = $conn->prepare($sql_count);
if ($stmt_count === false) {
    $select_error_msg = "COUNT Prepare failed: " . $conn->error;
} else {
    if (!empty($bindings)) {
        // Gunakan call_user_func_array untuk bind_param dengan array dinamis
        $bind_params_count = [$binding_types];
        foreach($bindings as &$bind_value){ // Referensi diperlukan untuk call_user_func_array
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

// Hitung total halaman, validasi current_page, dan hitung offset
$total_pages = ($rows_per_page > 0 && $total_rows > 0) ? ceil($total_rows / $rows_per_page) : 1;
$total_pages = max(1, $total_pages); // Pastikan minimal 1 halaman
$current_page = min($current_page, $total_pages); // Pastikan halaman tidak melebihi total halaman
$current_page = max(1, $current_page); // Pastikan halaman tidak kurang dari 1
$offset = ($current_page - 1) * $rows_per_page;
$offset = max(0, $offset); // Pastikan offset tidak negatif

// --- Query SELECT Data ---
$sql_select = $base_query_select . $where_clause . " ORDER BY " . $order_by . " LIMIT ?, ?";
$stmt_select = $conn->prepare($sql_select);
$result_select = null; // Inisialisasi null

if ($stmt_select === false) {
    $select_error_msg = "SELECT Prepare failed: " . $conn->error;
} else {
    // Siapkan bindings untuk LIMIT dan OFFSET
    $select_bindings = $bindings;
    $select_bindings[] = $offset;
    $select_bindings[] = $rows_per_page;
    $select_binding_types = $binding_types . "ii"; // Tambahkan "ii" untuk offset dan rows_per_page

    // Bind parameter secara dinamis
    $bind_params_select = [$select_binding_types];
    foreach($select_bindings as &$bind_value){
        $bind_params_select[] = &$bind_value;
    }

    if (!call_user_func_array([$stmt_select, 'bind_param'], $bind_params_select)) {
        $select_error_msg = "SELECT Bind param failed: " . $stmt_select->error;
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
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pesanan - Admin Dashboard</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container-scroller {
            display: flex;
            min-height: 100vh;
        }
        .page-body-wrapper {
            flex-grow: 1;
            display: flex;
        }
        .main-panel {
            flex-grow: 1;
            padding: 20px;
        }
        .content-wrapper {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
        }
        .table-responsive {
            margin-top: 20px;
        }
        .status-display {
            padding: .35em .65em;
            border-radius: .25rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-block; /* Agar padding dan margin berfungsi */
            min-width: 80px; /* Lebar minimum agar konsisten */
            text-align: center;
        }
        .status-pending { background-color: #ffc107; color: #343a40; } /* yellow */
        .status-diproses { background-color: #0dcaf0; color: #fff; } /* cyan */
        .status-selesai { background-color: #198754; color: #fff; } /* green */
        .status-dibatalkan { background-color: #dc3545; color: #fff; } /* red */

        /* Custom styles for Modal Sidebar */
        .modal.fade .modal-dialog {
            transform: translate(100%, 0);
            transition: transform .3s ease-out;
            margin: 0;
            width: 350px; /* Lebar sidebar */
            max-width: 350px;
            height: 100vh;
            position: fixed;
            right: 0;
            top: 0;
            bottom: 0;
        }
        .modal.fade.show .modal-dialog {
            transform: translate(0, 0);
        }
        .modal-dialog {
            pointer-events: none; /* Allows clicks outside to close */
        }
        .modal-content {
            height: 100%;
            border-radius: 0;
            pointer-events: auto; /* Re-enable clicks inside */
            display: flex;
            flex-direction: column;
        }
        .modal-header {
            border-bottom: 1px solid #dee2e6;
            padding: 1rem;
        }
        .modal-body {
            flex-grow: 1;
            overflow-y: auto;
            padding: 1.5rem;
        }
        .modal-footer {
            border-top: 1px solid #dee2e6;
            padding: 1rem;
            flex-shrink: 0; /* Prevent shrinking */
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .modal.fade .modal-dialog {
                width: 90%;
                max-width: 90%;
            }
            .d-flex.flex-wrap.justify-content-between {
                flex-direction: column;
                align-items: flex-start !important;
            }
            .d-flex.align-items-center {
                flex-direction: column;
                align-items: flex-start !important;
                width: 100%;
            }
            #sortSelect, .input-group {
                width: 100% !important;
                margin-bottom: 10px;
            }
            .input-group .btn {
                width: auto;
            }
        }
    </style>
</head>

<body>
    <div class="container-scroller">
        <div class="container-fluid page-body-wrapper">
            <div class="main-panel">
                <div class="content-wrapper">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                        <div class="header-left">
                            <h2>Data Pesanan</h2>
                        </div>

                        <div class="d-flex align-items-center">
                            <select id="sortSelect" class="form-select form-select-sm me-2" onchange="applyFilterSort()" style="min-width: 200px;">
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
                            <div class="input-group input-group-sm" style="min-width: 250px;">
                                <input type="text" id="searchInput" class="form-control" placeholder="Cari pesanan..." value="<?php echo htmlspecialchars($search_term); ?>" onkeypress="handleSearchKey(event)">
                                <button class="btn btn-outline-secondary" type="button" onclick="applyFilterSort()">Cari</button>
                                <button class="btn btn-danger" type="button" onclick="clearFilterSort()">Batal</button>
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
                                            <tr>
                                                <th>NO</th>
                                                <th>ID Pesanan</th>
                                                <th>ID Meja</th>
                                                <th>Nama Pelanggan</th>
                                                <th>Status</th>
                                                <th>Total Harga</th>
                                                <th>Waktu Pesan</th>
                                                <th>Metode Pembayaran</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody class='text-center'>
                                            <?php
                                            // Pastikan $result_select valid dan memiliki baris
                                            if ($result_select && ($result_select instanceof mysqli_result) && $result_select->num_rows > 0) {
                                                $row_number = $offset + 1;
                                                while ($row = $result_select->fetch_assoc()) {
                                                    $total_harga_rupiah = "Rp " . number_format($row["total_harga"] ?? 0, 0, ',', '.');
                                                    $nama_pelanggan_tampil = htmlspecialchars($row["nama_pelanggan"] ?? 'N/A');
                                                    $id_meja_tampil = htmlspecialchars($row["id_meja"] ?? 'N/A');
                                                    $current_status_value = htmlspecialchars($row["status"] ?? ''); // Nilai mentah (pending, diproses)

                                                    $status_text_display = ucfirst($current_status_value); // Untuk tampilan awal di tabel
                                                    $status_class_display = 'status-' . $current_status_value; // Class untuk warna

                                                    echo "<tr>";
                                                    echo "<td>" . $row_number . "</td>";
                                                    echo "<td>" . htmlspecialchars($row["id_pesanan"]) . "</td>";
                                                    echo "<td>" . $id_meja_tampil . "</td>";
                                                    echo "<td>" . $nama_pelanggan_tampil . "</td>";

                                                    // Kolom Status (badge/button klikable)
                                                    echo "<td>";
                                                    echo "<span class='status-display status-clickable " . $status_class_display . "' data-id='" . htmlspecialchars($row["id_pesanan"]) . "' data-status='" . $current_status_value . "'>";
                                                    echo $status_text_display;
                                                    echo "</span>";
                                                    echo "</td>";

                                                    echo "<td>" . $total_harga_rupiah . "</td>";
                                                    echo "<td>" . htmlspecialchars($row["waktu_pesan"] ?? 'N/A') . "</td>";
                                                    echo "<td>" . htmlspecialchars($row["metode_pembayaran"] ?? 'N/A') . "</td>";
                                                    echo "<td>
                                                            <button class='btn btn-sm btn-info detail-btn' data-id='" . htmlspecialchars($row["id_pesanan"]) . "' type='button'>
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
                                                    echo "<tr><td colspan='9'>Tidak ada data pesanan ditemukan" . (!empty($search_term) ? " untuk pencarian '" . htmlspecialchars($search_term) . "'" : "") . ".</div></td></tr>";
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
                                        <?php
                                        $links_per_side = 2;
                                        $start_page_link = max(1, $current_page - $links_per_side);
                                        $end_page_link = min($total_pages, $current_page + $links_per_side);

                                        if ($start_page_link > 1) {
                                            echo '<li class="page-item"><a class="page-link" href="?page=1';
                                            echo (!empty($search_term) ? "&search=" . urlencode($search_term) : '');
                                            echo (!empty($sort_by) ? "&sort=" . urlencode($sort_by) : '');
                                            echo '">1</a></li>';
                                            if ($start_page_link > 2) {
                                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                            }
                                        }

                                        for ($i = $start_page_link; $i <= $end_page_link; $i++) { ?>
                                            <li class="page-item <?php if ($i == $current_page) echo 'active'; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; echo (!empty($search_term) ? "&search=" . urlencode($search_term) : ''); echo (!empty($sort_by) ? "&sort=" . urlencode($sort_by) : ''); ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php }

                                        if ($end_page_link < $total_pages) {
                                            if ($end_page_link < $total_pages - 1) {
                                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                            }
                                            echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages;
                                            echo (!empty($search_term) ? "&search=" . urlencode($search_term) : '');
                                            echo (!empty($sort_by) ? "&sort=" . urlencode($sort_by) : '');
                                            echo '">' . $total_pages . '</a></li>';
                                        }
                                        ?>
                                        <li class="page-item <?php if ($current_page >= $total_pages) echo 'disabled'; ?>">
                                            <a class="page-link" href="?page=<?php echo min($total_pages, $current_page + 1); echo (!empty($search_term) ? "&search=" . urlencode($search_term) : ''); echo (!empty($sort_by) ? "&sort=" . urlencode($sort_by) : ''); ?>" <?php if ($current_page >= $total_pages) echo 'tabindex="-1" aria-disabled="true"'; ?>>Next</a>
                                        </li>
                                    </ul>
                                </nav>
                                <?php } ?>
                                <?php if($total_rows >= 0){ ?>
                                    <div class="text-center mt-2">
                                        <small>Menampilkan <?php echo ($result_select && ($result_select instanceof mysqli_result) ? $result_select->num_rows : 0); ?> dari <?php echo $total_rows; ?> total pesanan<?php echo (!empty($search_term) ? " untuk pencarian '" . htmlspecialchars($search_term) . "'" : ""); ?>.</small>
                                    </div>
                                <?php } ?>

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
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p class="text-center"><i class="fas fa-spinner fa-spin me-2"></i>Memuat...</p>
                            </div>
                        </div>
                    </div>
                </div>
                </div>
            </div>
        </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Function confirm delete
        function confirmDelete(id) {
            Swal.fire({
                title: 'Apakah Anda yakin?',
                text: "Pesanan ini akan dihapus secara permanen!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d', // Bootstrap secondary color
                confirmButtonText: 'Ya, hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    let currentUrlParams = new URLSearchParams(window.location.search);
                    currentUrlParams.delete('delete_pesanan_id'); // Hapus jika sudah ada
                    let baseUrl = window.location.pathname;
                    window.location.href = baseUrl + '?delete_pesanan_id=' + id + '&' + currentUrlParams.toString();
                }
            });
        }
        window.confirmDelete = confirmDelete; // Make global for get_pesanan_detail.php


        // Helper function for JS side capitalization + basic html escaping
        function jsUcfirst(string) {
            if (!string) return '';
            const parser = new DOMParser();
            // Decode HTML entities first to ensure correct capitalization
            const decoded = parser.parseFromString(`<!doctype html><body>${string}`, 'text/html').body.textContent || string;
            const capitalized = decoded.charAt(0).toUpperCase() + decoded.slice(1);
            // Re-encode for display in HTML
            const tempDiv = document.createElement('div');
            tempDiv.textContent = capitalized;
            return tempDiv.innerHTML;
        }


        // --- JavaScript Filter, Sort, Pagination via URL (full page reload) ---
        function applyFilterSort() {
            const searchInput = document.getElementById("searchInput").value;
            const sortSelect = document.getElementById("sortSelect").value;

            let params = new URLSearchParams(window.location.search);

            // Jika ada perubahan pada search atau sort, kembali ke halaman 1
            const urlSearch = params.get('search') || '';
            const urlSort = params.get('sort') || '';
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

            let newUrl = window.location.pathname + '?' + params.toString();
            window.location.href = newUrl;
        }

        function clearFilterSort() {
            let params = new URLSearchParams(window.location.search);
            params.delete('search');
            params.delete('sort');
            // Pertahankan halaman saat ini jika tidak ada search/sort yang aktif
            // Jika ingin selalu kembali ke halaman 1 saat clear: params.set('page', 1);
            window.location.href = window.location.pathname + '?' + params.toString();
        }

        // Handle Enter key press in search input
        function handleSearchKey(event) {
            if (event.key === 'Enter') {
                event.preventDefault(); // Prevent default form submission
                applyFilterSort();
            }
        }

        window.applyFilterSort = applyFilterSort;
        window.clearFilterSort = clearFilterSort;


        // --- Main Document Ready Logic (Modal, Status Click) ---
        document.addEventListener('DOMContentLoaded', function() {

            // Check for SweetAlert message from PHP session
            <?php if (isset($_SESSION['swal_message'])): ?>
                Swal.fire({
                    title: '<?php echo $_SESSION['swal_message']['title']; ?>',
                    text: '<?php echo $_SESSION['swal_message']['text']; ?>',
                    icon: '<?php echo $_SESSION['swal_message']['icon']; ?>',
                    confirmButtonText: 'OK'
                });
                <?php unset($_SESSION['swal_message']); // Clear the session variable ?>
            <?php endif; ?>


            const pesananDetailSidebarElement = document.getElementById('pesananDetailSidebar');
            let pesananDetailSidebarModal = null;
            const modalBody = pesananDetailSidebarElement ? pesananDetailSidebarElement.querySelector('.modal-body') : null;
            const tableBody = document.querySelector("#pesananTable tbody");

            // Initialize Bootstrap Modal
            if (pesananDetailSidebarElement) {
                try {
                    // Bootstrap 5 uses new bootstrap.Modal()
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        pesananDetailSidebarModal = new bootstrap.Modal(pesananDetailSidebarElement, {
                            keyboard: true,
                            backdrop: true
                        });
                    } else {
                        console.error("Bootstrap Modal JS (Bootstrap 5) not found or failed to initialize!");
                    }
                } catch(e) {
                    console.error("Error initializing Bootstrap Modal:", e);
                }
            } else {
                console.error("Modal element #pesananDetailSidebar not found!");
            }


            // --- Detail Button Click (Open Modal Sidebar) ---
            if (tableBody && modalBody && pesananDetailSidebarModal) {
                tableBody.addEventListener('click', function(event) {
                    const detailButton = event.target.closest('.detail-btn');
                    if (detailButton) {
                        event.preventDefault();
                        const pesananId = detailButton.getAttribute('data-id');

                        modalBody.innerHTML = '<p class="text-center p-5"><i class="fas fa-spinner fa-spin me-2"></i>Memuat...</p>'; // Clear and show loading
                        pesananDetailSidebarModal.show(); // Show the modal

                        fetch('get_pesanan_detail.php?id=' + encodeURIComponent(pesananId))
                        .then(response => {
                            if (!response.ok) {
                                // Attempt to parse JSON error if content type is json
                                const contentType = response.headers.get("content-type");
                                if (contentType && contentType.indexOf("application/json") !== -1) {
                                    return response.json().then(err => {
                                        throw new Error(err.message || `Server error ${response.status}.`);
                                    });
                                } else {
                                    // Otherwise, just show generic error
                                    return response.text().then(text => {
                                        console.error("Non-JSON body:", text);
                                        throw new Error(`HTTP error! Status: ${response.status}. Response was not JSON.`);
                                    });
                                }
                            }
                            return response.text(); // Expecting HTML
                        })
                        .then(htmlContent => {
                            modalBody.innerHTML = htmlContent;
                        })
                        .catch(error => {
                            console.error('Fetch Error Detail:', error);
                            modalBody.innerHTML = `<div class="alert alert-danger m-3" role='alert'>Gagal memuat detail pesanan:<br>${error.message}</div>`;
                        });
                    }
                }); // End detailButton click

                // --- Status Badge Click (Open SweetAlert for Status Change) ---
                tableBody.addEventListener('click', function(event) {
                    const statusSpan = event.target.closest('.status-clickable');
                    if (statusSpan) {
                        event.preventDefault();
                        const pesananId = statusSpan.getAttribute('data-id');
                        const currentStatus = statusSpan.getAttribute('data-status'); // Get raw status value

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
                            inputValue: currentStatus,
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
                                // Visual feedback for loading state
                                statusSpan.style.opacity = '0.5';
                                statusSpan.style.pointerEvents = 'none'; // Disable clicks during fetch

                                return fetch('index.php', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/x-www-form-urlencoded',
                                            'X-Requested-With': 'XMLHttpRequest' // Indicate AJAX request
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
                                                return response.text().then(text => {
                                                    console.error("Non-JSON Status Update Response:", text);
                                                    throw new Error(`HTTP error! Status: ${response.status}. Response was not JSON.`);
                                                });
                                            }
                                        }
                                        const contentType = response.headers.get("content-type");
                                        if (contentType && contentType.indexOf("application/json") !== -1) {
                                            return response.json(); // Expected JSON response
                                        } else {
                                            return response.text().then(text => {
                                                console.error("OK non-JSON Status Update Response:", text);
                                                throw new Error('Invalid response from server (expected JSON).');
                                            });
                                        }
                                    })
                                    .then(data => {
                                        if (data.success) {
                                            return data;
                                        } else {
                                            throw new Error(data.message || 'Gagal memperbarui status dari server.');
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Fetch Status Update Error:', error);
                                        Swal.showValidationMessage(`Update gagal: ${error.message || error}`);
                                        return false; // Prevent closing the modal
                                    })
                                    .finally(() => {
                                        statusSpan.style.opacity = '1';
                                        statusSpan.style.pointerEvents = 'auto'; // Re-enable clicks
                                    });
                            }
                        }).then((result) => {
                            if (result.isConfirmed && result.value && result.value.success) {
                                const newStatusValue = result.value.updatedStatusValue || Swal.getPopup().querySelector('input[name="swal2-radio"]:checked').value;
                                const targetSpan = tableBody.querySelector(`.status-clickable[data-id="${pesananId}"]`);
                                if (targetSpan) {
                                    targetSpan.textContent = jsUcfirst(newStatusValue); // Update text using JS helper
                                    targetSpan.classList.remove('status-pending', 'status-diproses', 'status-selesai', 'status-dibatalkan');
                                    targetSpan.classList.add('status-' + newStatusValue);
                                    targetSpan.setAttribute('data-status', newStatusValue); // Update data attribute
                                }
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Diperbarui!',
                                    text: result.value.message || 'Status pesanan telah diperbarui.',
                                    showConfirmButton: false,
                                    timer: 1500
                                });
                            } else if (result.dismiss) {
                                // Modal dismissed, do nothing or show a "cancelled" message
                            }
                        });
                    }
                });


            } else {
                console.warn("Table body or modal elements not found for event listeners. Check your HTML structure.");
            }

        }); // End DOMContentLoaded
    </script>


</body>

</html>
<?php
// Tutup koneksi database
if (isset($conn) && $conn) {
    $conn->close();
}
?>