<?php
session_start();

// --- Hardcoded login untuk demo. Ganti dengan sistem autentikasi Anda. ---
// Jika belum ada sesi username, set sesi untuk demo
if (!isset($_SESSION['username'])) {
    $_SESSION['username'] = 'admin'; // Contoh username
    $_SESSION['role'] = 'admin';    // Contoh role
}
// --- Akhir hardcoded login ---

// Sertakan file koneksi.php (sesuaikan jalur jika diperlukan)
// Asumsi koneksi.php berada di folder yang sama (dapur/)
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

// --- Handle Delete Request (TIDAK ADA TOMBOL DI UI KITCHEN PANEL) ---
if (isset($_GET['delete_pesanan_id'])) {
    if (!isset($_SESSION['username'])) {
        header("Location: ../auth/index.php?login_required=true"); // Redirect ke login
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
    // Set SweetAlert message via session
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
    header("Location: ../auth/index.php?login_required=true"); // Redirect ke login jika belum
    exit();
}
$username = $_SESSION['username'];
$role = $_SESSION['role'];

// --- Logika Paginasi, Pencarian, Pengurutan ---
$rows_per_page = 10; // Jumlah pesanan per halaman
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page = max(1, $current_page); // Pastikan halaman tidak kurang dari 1

$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : '';
$order_by = 'p.waktu_pesan DESC'; // Default order: waktu terbaru di atas

switch ($sort_by) {
    case 'waktu_desc':
        $order_by = 'p.waktu_pesan DESC';
        break;
    case 'waktu_asc':
        $order_by = 'p.waktu_pesan ASC';
        break;
    case 'id_meja_asc':
        $order_by = 'p.id_meja ASC, p.waktu_pesan DESC';
        break;
    case 'id_meja_desc':
        $order_by = 'p.id_meja DESC, p.waktu_pesan DESC';
        break;
    default:
        $order_by = 'p.waktu_pesan DESC';
        $sort_by = ''; // Reset sort_by jika tidak valid
}

// Helper function htmlspecialchars + ucfirst (aman XSS)
function ucfirst_safe_index($string)
{
    if (!is_string($string)) return $string;
    $decoded = htmlspecialchars_decode($string ?? '', ENT_QUOTES);
    return htmlspecialchars(ucfirst($decoded), ENT_QUOTES);
}

// --- Inisialisasi variabel error dan result SELECT ---
$select_error_msg = null;
$result_select = null;
$total_rows = 0;

// Basis query untuk SELECT (ambil detail menu dan nama file gambar)
$base_query_select = "
    SELECT
        p.id_pesanan,
        p.id_meja,
        p.id_pelanggan,
        p.status,
        p.total_harga,
        p.waktu_pesan,
        p.metode_pembayaran,
        pl.nama AS nama_pelanggan,
        GROUP_CONCAT(CONCAT(dp.jumlah, 'x ', m.nama_menu, '||', IFNULL(m.gambar, '')) ORDER BY m.nama_menu ASC SEPARATOR '|||') AS item_details
    FROM
        pesanan p
    LEFT JOIN
        pelanggan pl ON p.id_pelanggan = pl.id_pelanggan
    LEFT JOIN
        detail_pesanan dp ON p.id_pesanan = dp.id_pesanan 
    LEFT JOIN
        menu m ON dp.id_menu = m.id_menu 
";
$base_query_count = "
    FROM
        pesanan p
    LEFT JOIN
        pelanggan pl ON p.id_pelanggan = pl.id_pelanggan
";

// Buat klausa WHERE untuk pencarian DAN FILTER STATUS
$where_clause_parts = [];
$bindings = [];
$binding_types = "";

// --- FILTER UTAMA: Hanya tampilkan status 'diproses', 'selesai', atau 'dibatalkan' ---
// INI ADALAH PERBAIKANNYA
$where_clause_parts[] = "(p.status = ? OR p.status = ? OR p.status = ?)";
$bindings[] = 'diproses';
$bindings[] = 'selesai';
$bindings[] = 'dibatalkan';
$binding_types .= "sss"; // Tiga string untuk diproses, selesai, dan dibatalkan


if (!empty($search_term)) {
    $search_like = '%' . $search_term . '%';
    $searchable_cols = [
        'p.id_pesanan',
        'p.id_meja',
        // 'p.status', // Tidak perlu dicari status jika kita sudah memfilternya
        'p.metode_pembayaran',
        'pl.nama'
    ];
    $search_where_parts = [];
    foreach ($searchable_cols as $col) {
        $search_where_parts[] = "$col LIKE ?";
        $bindings[] = $search_like;
        $binding_types .= "s";
    }

    if (is_numeric($search_term)) {
        $numeric_search_term = (float)$search_term;
        $search_where_parts[] = "p.total_harga = ?";
        $bindings[] = $numeric_search_term;
        $binding_types .= "d";

        $search_where_parts[] = "p.id_pelanggan = ?";
        $bindings[] = (int)$search_term;
        $binding_types .= "i";
    }
    // Gabungkan kondisi pencarian dengan OR, lalu masukkan ke main where_clause_parts
    if (!empty($search_where_parts)) {
        $where_clause_parts[] = "(" . implode(" OR ", $search_where_parts) . ")";
    }
}

// Gabungkan semua bagian klausa WHERE dengan AND
if (!empty($where_clause_parts)) {
    $where_clause = " WHERE " . implode(" AND ", $where_clause_parts);
}


// --- Query COUNT (*) ---
$sql_count = "SELECT COUNT(DISTINCT p.id_pesanan) AS total_rows " . $base_query_count . $where_clause;
$stmt_count = $conn->prepare($sql_count);
if ($stmt_count === false) {
    $select_error_msg = "COUNT Prepare failed: " . $conn->error;
} else {
    // Bind parameter untuk COUNT query
    // Ambil jenis binding (termasuk filter status) dan nilai binding
    $count_bindings_temp = $bindings;
    $count_binding_types_temp = $binding_types;

    if (!empty($count_bindings_temp)) {
        $bind_params_count = [&$count_binding_types_temp];
        foreach($count_bindings_temp as &$bind_value){
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
        if ($result_count) {
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
$total_pages = max(1, $total_pages);
$current_page = min($current_page, $total_pages);
$current_page = max(1, $current_page);
$offset = ($current_page - 1) * $rows_per_page;
$offset = max(0, $offset);

// --- Query SELECT Data dengan GROUP BY untuk item_details ---
$sql_select = $base_query_select . $where_clause . " GROUP BY p.id_pesanan ORDER BY " . $order_by . " LIMIT ?, ?";
$stmt_select = $conn->prepare($sql_select);
$result_select = null;

if ($stmt_select === false) {
    $select_error_msg = "SELECT Prepare failed: " . $conn->error;
} else {
    $select_bindings_full = $bindings; // Gunakan bindings yang sudah termasuk filter status dan pencarian
    $select_bindings_full[] = $offset;
    $select_bindings_full[] = $rows_per_page;
    $select_binding_types_full = $binding_types . "ii"; // Tambahkan "ii" untuk offset dan rows_per_page

    // Bind parameter secara dinamis
    $bind_params_select = [&$select_binding_types_full]; // Referensi untuk string jenis binding
    foreach ($select_bindings_full as &$bind_value) { // Referensi untuk setiap nilai binding
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
        if ($result_select === false) {
            $select_error_msg = "SELECT Get result failed: " . $conn->error;
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
    <title>Panel Dapur - Pesanan</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography,aspect-ratio"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Base styles for status badges */
        .status-badge {
            @apply inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium cursor-pointer transition-colors duration-200 ease-in-out;
        }

        /* Styles for specific statuses (small badges) */
        .status-pending {
            @apply bg-yellow-100 text-yellow-800;
        }

        .status-diproses {
            @apply bg-blue-100 text-blue-800;
        }
        
        /* Modifikasi untuk status Selesai/Dibatalkan agar tampil seperti stempel di pojok kanan atas */
        /* Class ini akan dipadukan dengan status-badge */
        .status-badge.selesai-stamp,
        .status-badge.dibatalkan-stamp {
            position: absolute;
            top: 1rem;
            right: 1rem;
            z-index: 10;
            /* Ukuran dan styling untuk stempel */
            font-size: 1.125rem; /* text-lg */
            padding: 0.5rem 1rem; /* px-4 py-2 */
            font-weight: 700; /* font-bold */
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1); /* shadow-lg */
            border-radius: 9999px; /* rounded-full */
            transform: rotate(8deg); /* Efek miring seperti stempel */
            min-width: fit-content; /* Pastikan teks tidak pecah */
            text-align: center;
        }

        .status-badge.selesai-stamp {
            background-color: #22c55e; /* bg-green-500 */
            color: #ffffff; /* text-white */
        }

        .status-badge.dibatalkan-stamp {
            background-color: #ef4444; /* bg-red-500 */
            color: #ffffff; /* text-white */
        }


        /* Adjust grid column for better sizing on larger screens */
        @media (min-width: 1280px) {
            #pesananGrid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }

        @media (min-width: 1024px) and (max-width: 1279px) {
            #pesananGrid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (min-width: 768px) and (max-width: 1023px) {
            #pesananGrid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767px) {
            #pesananGrid {
                grid-template-columns: repeat(1, minmax(0, 1fr));
            }
        }
    </style>
</head>

<body class="bg-gray-100 font-sans antialiased text-gray-800">

    <div class="min-h-screen flex flex-col">
        <header class="bg-gray-800 text-white p-4 shadow-md flex justify-between items-center">
            <h1 class="text-2xl font-bold">Panel Dapur</h1>
            <div class="flex items-center">
                <span class="text-sm mr-2">Halo, <?php echo htmlspecialchars($username); ?>!</span>
                <a href="../admin/auth/logout.php" class="bg-red-500 hover:bg-red-600 text-white text-sm font-semibold py-1 px-3 rounded-md transition-colors duration-200">Logout</a>
            </div>
        </header>

        <main class="flex-1 p-6">
            <div class="max-w-full mx-auto bg-white rounded-lg shadow-md p-6">
                <div class="flex flex-wrap justify-between items-center mb-6 gap-4">
                    <h2 class="text-3xl font-semibold text-gray-900">Pesanan Masuk</h2>

                    <div class="flex items-center gap-2 flex-wrap">
                        <select id="sortSelect" onchange="applyFilterSort()" class="block w-full md:w-auto px-4 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            <option value="">-- Urutkan --</option>
                            <option value="waktu_desc" <?php if ($sort_by == 'waktu_desc') echo 'selected'; ?>>Terbaru</option>
                            <option value="waktu_asc" <?php if ($sort_by == 'waktu_asc') echo 'selected'; ?>>Terlama</option>
                            <option value="id_meja_asc" <?php if ($sort_by == 'id_meja_asc') echo 'selected'; ?>>Meja Menaik</option>
                            <option value="id_meja_desc" <?php if ($sort_by == 'id_meja_desc') echo 'selected'; ?>>Meja Menurun</option>
                        </select>
                        <div class="relative flex-grow">
                            <input type="text" id="searchInput" class="block w-full pl-4 pr-10 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Cari pesanan..." value="<?php echo htmlspecialchars($search_term); ?>" onkeypress="handleSearchKey(event)">
                            <button type="button" onclick="applyFilterSort()" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                                <span class="sr-only">Cari</span>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                                    <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 100 11 5.5 5.5 0 000-11zM2 9a7 7 0 1112.452 4.391l3.328 3.329a.75.75 0 11-1.06 1.06l-3.329-3.328A7 7 0 012 9z" clip-rule="evenodd" />
                                </svg>
                            </button>
                            <?php if (!empty($search_term)) : ?>
                                <button type="button" onclick="clearFilterSort()" class="absolute inset-y-0 right-8 pr-3 flex items-center text-red-500 hover:text-red-700">
                                    <span class="sr-only">Batal</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                                        <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                                    </svg>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php
                if ($select_error_msg !== null && trim($select_error_msg) !== '') {
                    echo "<div class='bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4' role='alert'>" . htmlspecialchars($select_error_msg) . "</div>";
                } else {
                ?>
                    <div id="pesananGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                        <?php
                        if ($result_select && ($result_select instanceof mysqli_result) && $result_select->num_rows > 0) {
                            // --- PATH GAMBAR YANG BENAR SESUAI PENJELASAN ANDA ---
                            // Path ini harus keluar satu folder dari 'dapur/' lalu masuk ke 'images/'
                            // Contoh: http://localhost/resto_rahmat/images/
                            $base_image_url_prefix = '../images/';

                            // Path untuk placeholder.png. Asumsi: placeholder ada di 'assets/img/' di root proyek.
                            // Contoh: http://localhost/resto_rahmat/assets/img/placeholder.png
                            $placeholder_image_path = '../assets/img/placeholder.png'; // Ubah jika lokasi placeholder berbeda
                            // Jika placeholder ada di folder yang sama dengan gambar menu (../images/):
                            // $placeholder_image_path = '../images/placeholder.png';


                            while ($row = $result_select->fetch_assoc()) {
                                $total_harga_rupiah = "Rp " . number_format($row["total_harga"] ?? 0, 0, ',', '.');
                                $nama_pelanggan_tampil = htmlspecialchars($row["nama_pelanggan"] ?? 'N/A');
                                $id_meja_tampil = htmlspecialchars($row["id_meja"] ?? 'N/A');
                                $current_status_value = htmlspecialchars($row["status"] ?? '');

                                $status_text_display = ucfirst($current_status_value);
                                
                                // Tentukan class tambahan untuk "stempel" jika statusnya selesai/dibatalkan
                                // KARENA KITA SUDAH MEMFILTER QUERY UNTUK HANYA MENAMPILKAN PENDING/DIPROSES,
                                // MAKA BAGIAN INI HANYA AKAN MENGHASILKAN KELAS UNTUK BADGE BIASA.
                                // SAYA AKAN KEMBALIKAN LOGIKA STAMP DI SINI AGAR TETAP SESUAI KEINGINAN ANDA.
                                $status_badge_additional_classes = '';
                                if ($current_status_value === 'selesai') {
                                    $status_badge_additional_classes = 'selesai-stamp'; // Cukup tambahkan kelas stempel saja
                                } elseif ($current_status_value === 'dibatalkan') {
                                    $status_badge_additional_classes = 'dibatalkan-stamp'; // Cukup tambahkan kelas stempel saja
                                } else {
                                    // Untuk pending dan diproses, ini kelas badge normal
                                    $status_badge_additional_classes = 'status-' . $current_status_value; 
                                }


                                $item_details_raw = explode('|||', $row['item_details'] ?? '');
                                $items_for_display = [];
                                foreach ($item_details_raw as $item_raw) {
                                    if (empty($item_raw)) continue;
                                    // PERBAIKAN KRUSIAL: Hapus `. '||'` dari explode untuk menghindari `%7C%7C`
                                    list($qty_name, $image_filename) = explode('||', $item_raw, 2); 
                                    $items_for_display[] = ['qty_name' => $qty_name, 'image_filename' => $image_filename];
                                }
                        ?>
                                <div class="bg-white border border-gray-200 rounded-lg shadow-md p-4 flex flex-col justify-between min-h-[350px] relative overflow-hidden">
                                    
                                    <div class="flex items-center justify-between mb-3">
                                        <h3 class="text-xl font-bold text-gray-900">Order #<?php echo htmlspecialchars($row["id_pesanan"]); ?></h3>
                                        <span class="status-badge <?php echo $status_badge_additional_classes; ?>"
                                              data-id="<?php echo htmlspecialchars($row["id_pesanan"]); ?>"
                                              data-status="<?php echo $current_status_value; ?>">
                                            <?php echo $status_text_display; ?>
                                        </span>
                                    </div>

                                    <div class="text-sm text-gray-600 mb-2">
                                        <p class="mb-1"><strong class="text-gray-800">Meja:</strong> <?php echo $id_meja_tampil; ?></p>
                                        <p class="mb-1"><strong class="text-gray-800">Pelanggan:</strong> <?php echo $nama_pelanggan_tampil; ?></p>
                                        <p class="mb-1"><strong class="text-gray-800">Waktu Pesan:</strong> <?php echo htmlspecialchars($row["waktu_pesan"] ?? 'N/A'); ?></p>
                                        <p class="mb-1"><strong class="text-gray-800">Pembayaran:</strong> <?php echo htmlspecialchars($row["metode_pembayaran"] ?? 'N/A'); ?></p>
                                    </div>

                                    <div class="text-sm text-gray-800 mb-4 flex-grow">
                                        <p class="font-semibold mb-2">Daftar Pesanan:</p>
                                        <ul class="space-y-2">
                                            <?php if (!empty($items_for_display)) {
                                                foreach ($items_for_display as $item) {
                                                    $current_image_filename = trim($item['image_filename']);
                                                    $image_src = '';

                                                    if (empty($current_image_filename)) {
                                                        $image_src = $placeholder_image_path;
                                                    } else {
                                                        // Gunakan path ../images/ + nama file yang di-URL encode
                                                        $image_src = $base_image_url_prefix . rawurlencode($current_image_filename);
                                                    }
                                                    
                                                    echo '<li class="flex items-center">';
                                                    echo '<img src="' . htmlspecialchars($image_src) . '" alt="Menu Image" class="w-20 h-20 object-cover rounded-md mr-2">'; 
                                                    echo '<span>' . htmlspecialchars($item['qty_name']) . '</span>';
                                                    echo '</li>';
                                                }
                                            } else {
                                                echo '<li class="text-gray-500">Tidak ada item dipesan.</li>';
                                            }
                                            ?>
                                        </ul>
                                    </div>

                                    <div class="mt-4 text-right">
                                        <p class="text-lg font-bold text-green-600">Total: <?php echo $total_harga_rupiah; ?></p>
                                    </div>

                                    <?php 
                                    // HANYA TAMPILKAN TOMBOL JIKA STATUS DIPROSES
                                    if ($current_status_value === 'diproses') : ?>
                                        <div class="mt-4 flex flex-col sm:flex-row gap-2">
                                            <button onclick="updateOrderStatus(<?php echo htmlspecialchars($row['id_pesanan']); ?>, 'selesai')"
                                                class="flex-1 bg-green-500 hover:bg-green-600 text-white font-semibold py-2 px-4 rounded-md shadow-md transition-colors duration-200">
                                                Selesai
                                            </button>
                                            <button onclick="updateOrderStatus(<?php echo htmlspecialchars($row['id_pesanan']); ?>, 'dibatalkan')"
                                                class="flex-1 bg-red-500 hover:bg-red-600 text-white font-semibold py-2 px-4 rounded-md shadow-md transition-colors duration-200">
                                                Batalkan
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                        <?php
                            }
                        } else {
                        ?>
                            <div class="col-span-full bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative" role="alert">
                                Tidak ada data pesanan ditemukan<?php echo (!empty($search_term) ? " untuk pencarian '" . htmlspecialchars($search_term) . "'" : ""); ?>.
                            </div>
                        <?php
                        }
                        ?>
                    </div>

                    <?php if ($total_pages > 1) { ?>
                        <nav aria-label="Page navigation" class="mt-6 flex justify-center">
                            <ul class="flex items-center -space-x-px">
                                <li>
                                    <a href="?page=<?php echo max(1, $current_page - 1);
                                                    echo (!empty($search_term) ? "&search=" . urlencode($search_term) : '');
                                                    echo (!empty($sort_by) ? "&sort=" . urlencode($sort_by) : ''); ?>"
                                        class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 leading-5 rounded-l-md hover:bg-gray-50 focus:outline-none focus:ring ring-gray-300 focus:border-blue-300 <?php if ($current_page <= 1) echo 'opacity-50 cursor-not-allowed'; ?>"
                                        <?php if ($current_page <= 1) echo 'tabindex="-1" aria-disabled="true"'; ?>>
                                        Previous
                                    </a>
                                </li>
                                <?php
                                $links_per_side = 2;
                                $start_page_link = max(1, $current_page - $links_per_side);
                                $end_page_link = min($total_pages, $current_page + $links_per_side);

                                if ($start_page_link > 1) {
                                    echo '<li><a href="?page=1';
                                    echo (!empty($search_term) ? "&search=" . urlencode($search_term) : '');
                                    echo (!empty($sort_by) ? "&sort=" . urlencode($sort_by) : '');
                                    echo '" class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 leading-5 hover:bg-gray-50 focus:outline-none focus:ring ring-gray-300 focus:border-blue-300">1</a></li>';
                                    if ($start_page_link > 2) {
                                        echo '<li class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 leading-5">...</li>';
                                    }
                                }

                                for ($i = $start_page_link; $i <= $end_page_link; $i++) { ?>
                                    <li>
                                        <a href="?page=<?php echo $i;
                                                        echo (!empty($search_term) ? "&search=" . urlencode($search_term) : '');
                                                        echo (!empty($sort_by) ? "&sort=" . urlencode($sort_by) : ''); ?>"
                                            class="relative inline-flex items-center px-4 py-2 text-sm font-medium <?php echo ($i == $current_page) ? 'bg-indigo-600 text-white border-indigo-600' : 'text-gray-700 bg-white border border-gray-300 hover:bg-gray-50'; ?> leading-5 focus:outline-none focus:ring ring-gray-300 focus:border-blue-300">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php }

                                if ($end_page_link < $total_pages) {
                                    if ($end_page_link < $total_pages - 1) {
                                        echo '<li class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 leading-5">...</li>';
                                    }
                                    echo '<li><a href="?page=' . $total_pages;
                                    echo (!empty($search_term) ? "&search=" . urlencode($search_term) : '');
                                    echo (!empty($sort_by) ? "&sort=" . urlencode($sort_by) : '');
                                    echo '" class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 leading-5 hover:bg-gray-50 focus:outline-none focus:ring ring-gray-300 focus:border-blue-300">' . $total_pages . '</a></li>';
                                }
                                ?>
                                <li>
                                    <a href="?page=<?php echo min($total_pages, $current_page + 1);
                                                    echo (!empty($search_term) ? "&search=" . urlencode($search_term) : '');
                                                    echo (!empty($sort_by) ? "&sort=" . urlencode($sort_by) : ''); ?>"
                                        class="relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 leading-5 rounded-r-md hover:bg-gray-50 focus:outline-none focus:ring ring-gray-300 focus:border-blue-300 <?php if ($current_page >= $total_pages) echo 'opacity-50 cursor-not-allowed'; ?>"
                                        <?php if ($current_page >= $total_pages) echo 'tabindex="-1" aria-disabled="true"'; ?>>
                                        Next
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php } ?>

                    <?php if ($total_rows >= 0) { ?>
                        <div class="text-center mt-4 text-sm text-gray-600">
                            Menampilkan <?php echo ($result_select && ($result_select instanceof mysqli_result) ? $result_select->num_rows : 0); ?> dari <?php echo $total_rows; ?> total pesanan<?php echo (!empty($search_term) ? " untuk pencarian '" . htmlspecialchars($search_term) . "'" : ""); ?>.
                        </div>
                    <?php } ?>

                <?php } ?>
            </div>
        </main>
    </div>

    <script>
        // Helper function for JS side capitalization + basic html escaping
        function jsUcfirst(string) {
            if (!string) return '';
            const parser = new DOMParser();
            const decoded = parser.parseFromString(`<!doctype html><body>${string}`, 'text/html').body.textContent || string;
            const capitalized = decoded.charAt(0).toUpperCase() + decoded.slice(1);
            const tempDiv = document.createElement('div');
            tempDiv.textContent = capitalized;
            return tempDiv.innerHTML;
        }

        // --- JavaScript Filter, Sort, Pagination via URL (full page reload) ---
        function applyFilterSort() {
            const searchInput = document.getElementById("searchInput").value;
            const sortSelect = document.getElementById("sortSelect").value;

            let params = new URLSearchParams(window.location.search);

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
            window.location.href = window.location.pathname + '?' + params.toString();
        }

        // Handle Enter key press in search input
        function handleSearchKey(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                applyFilterSort();
            }
        }

        // --- Update Order Status Function (triggered by buttons) ---
        function updateOrderStatus(pesananId, newStatus) {
            const currentStatusSpan = document.querySelector(`.status-badge[data-id="${pesananId}"]`);
            if (!currentStatusSpan) {
                console.error('Status badge not found for order ID:', pesananId);
                return;
            }

            Swal.fire({
                title: `Konfirmasi Status Pesanan #${pesananId}`,
                text: `Apakah Anda yakin ingin mengubah status pesanan ini menjadi "${jsUcfirst(newStatus)}"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, Ubah!',
                cancelButtonText: 'Batal',
                reverseButtons: true,
                showLoaderOnConfirm: true,
                customClass: {
                    confirmButton: 'bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-md shadow-md transition-colors duration-200',
                    cancelButton: 'bg-gray-400 hover:bg-gray-500 text-white font-semibold py-2 px-4 rounded-md shadow-md transition-colors duration-200 ml-2'
                },
                buttonsStyling: false,
                preConfirm: () => {
                    currentStatusSpan.style.opacity = '0.5';
                    currentStatusSpan.style.pointerEvents = 'none';

                    return fetch('index.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
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
                            return response.json();
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
                        return false;
                    })
                    .finally(() => {
                        currentStatusSpan.style.opacity = '1';
                        currentStatusSpan.style.pointerEvents = 'auto';
                    });
                }
            }).then((result) => {
                if (result.isConfirmed && result.value && result.value.success) {
                    // Cukup refresh halaman untuk melihat perubahan
                    window.location.reload();
                } else if (result.dismiss) {
                    // User cancelled the action
                }
            });
        }
        window.updateOrderStatus = updateOrderStatus; // Make global
    </script>

</body>

</html>
<?php
// Tutup koneksi database
if (isset($conn) && $conn) {
    $conn->close();
}
?>