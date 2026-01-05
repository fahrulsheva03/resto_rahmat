<?php
// File: admin/dashboard/index.php (GABUNGAN)
session_start();
include '../koneksi.php'; // Sesuaikan path ke file koneksi database Anda

// Periksa apakah pengguna sudah login
if (!isset($_SESSION['username'])) {
    header("Location: ../auth/index.php?login_required=true"); // Sesuaikan path ke halaman login
    exit();
}

$username = $_SESSION['username'];
$role = $_SESSION['role'];

// --- Logika Filter Tanggal ---
$current_year = date('Y');
$current_month_numeric = date('m');

$filter_year = isset($_GET['filter_year']) && is_numeric($_GET['filter_year']) ? (int)$_GET['filter_year'] : $current_year;
$filter_month = isset($_GET['filter_month']) && is_numeric($_GET['filter_month']) ? sprintf("%02d", (int)$_GET['filter_month']) : $current_month_numeric;
$use_monthly_filter = (isset($_GET['filter_month']) && isset($_GET['filter_year']));

$selected_filter_year = $filter_year;
$selected_filter_month = $filter_month;

// --- PENGAMBILAN DATA STATISTIK (TIDAK DIPENGARUHI FILTER UTAMA, kecuali pendapatan kartu) ---
// 1. Total Pendapatan (Keseluruhan)
$sql_total_revenue = "SELECT SUM(total_harga) AS total_pendapatan FROM pesanan WHERE status = 'selesai'";
$result_total_revenue = $conn->query($sql_total_revenue);
$total_revenue_data = $result_total_revenue->fetch_assoc();
$total_pendapatan_keseluruhan = $total_revenue_data['total_pendapatan'] ?? 0;

// 2. Total Jumlah Pesanan (Keseluruhan)
$sql_total_orders = "SELECT COUNT(id_pesanan) AS jumlah_pesanan FROM pesanan";
$result_total_orders = $conn->query($sql_total_orders);
$total_orders_data = $result_total_orders->fetch_assoc();
$jumlah_pesanan_total_keseluruhan = $total_orders_data['jumlah_pesanan'] ?? 0;

// 3. Jumlah Pesanan Selesai (Keseluruhan)
$sql_completed_orders = "SELECT COUNT(id_pesanan) AS jumlah_pesanan_selesai FROM pesanan WHERE status = 'selesai'";
$result_completed_orders = $conn->query($sql_completed_orders);
$completed_orders_data = $result_completed_orders->fetch_assoc();
$jumlah_pesanan_selesai_keseluruhan = $completed_orders_data['jumlah_pesanan_selesai'] ?? 0;

// 4. Total Pelanggan (Keseluruhan)
$sql_total_customers = "SELECT COUNT(id_pelanggan) AS jumlah_pelanggan FROM pelanggan";
$result_total_customers = $conn->query($sql_total_customers);
$total_customers_data = $result_total_customers->fetch_assoc();
$jumlah_pelanggan_keseluruhan = $total_customers_data['jumlah_pelanggan'] ?? 0;

// 5. Total Item Menu
$sql_total_menu = "SELECT COUNT(id_menu) AS jumlah_menu FROM menu";
$result_total_menu = $conn->query($sql_total_menu);
$total_menu_data = $result_total_menu->fetch_assoc();
$jumlah_menu_items = $total_menu_data['jumlah_menu'] ?? 0;

// 6. Pesanan Terbaru (LIMIT 5 - tidak dipengaruhi filter)
$sql_recent_orders = "SELECT p.id_pesanan, pl.nama AS nama_pelanggan, p.id_meja, p.total_harga, p.status, p.waktu_pesan
                      FROM pesanan p
                      JOIN pelanggan pl ON p.id_pelanggan = pl.id_pelanggan
                      ORDER BY p.waktu_pesan DESC LIMIT 5";
$result_recent_orders = $conn->query($sql_recent_orders);

// 7. Item Menu Populer (LIMIT 3 - tidak dipengaruhi filter)
$sql_popular_menu = "SELECT nama_menu, gambar, likes, harga, kategori FROM menu ORDER BY likes DESC LIMIT 3";
$result_popular_menu_query = $conn->query($sql_popular_menu); // Ubah nama var agar tidak konflik
$popular_items = [];
if ($result_popular_menu_query && $result_popular_menu_query->num_rows > 0) {
    while ($item_pop = $result_popular_menu_query->fetch_assoc()) {
        $popular_items[] = $item_pop;
    }
}

// 8. Data untuk Doughnut Chart (Status Pesanan - Keseluruhan)
$sql_order_status_counts = "SELECT status, COUNT(id_pesanan) as count FROM pesanan GROUP BY status";
$result_order_status_counts = $conn->query($sql_order_status_counts);
$order_status_labels = [];
$order_status_data = [];
$order_status_raw_counts = [];
$status_colors_map = [
    'pending' => '#ffab00',
    'diproses' => '#2962ff',
    'selesai' => '#00c853',
    'dibatalkan' => '#d50000',
];
$status_display_names_map = [
    'pending' => 'Pending',
    'diproses' => 'Diproses',
    'selesai' => 'Selesai',
    'dibatalkan' => 'Dibatalkan',
];
if ($result_order_status_counts && $result_order_status_counts->num_rows > 0) {
    while ($row_status = $result_order_status_counts->fetch_assoc()) {
        $status_key = strtolower($row_status['status']);
        $order_status_labels[] = $status_display_names_map[$status_key] ?? ucfirst($row_status['status']);
        $order_status_data[] = (int)$row_status['count'];
        $order_status_raw_counts[$status_key] = (int)$row_status['count'];
    }
}

// 9. Total Meja
$sql_total_tables = "SELECT COUNT(id_meja) AS jumlah_meja FROM meja";
$result_total_tables = $conn->query($sql_total_tables);
$total_tables_data = $result_total_tables->fetch_assoc();
$jumlah_meja = $total_tables_data['jumlah_meja'] ?? 0;

// 10. Pendapatan Bulan Ini (Untuk Kartu Kecil)
$target_year_card = $use_monthly_filter ? $selected_filter_year : $current_year;
$target_month_card = $use_monthly_filter ? $selected_filter_month : $current_month_numeric;
$sql_revenue_this_month_card = "SELECT SUM(total_harga) AS revenue_month FROM pesanan WHERE status = 'selesai' AND YEAR(waktu_pesan) = '$target_year_card' AND MONTH(waktu_pesan) = '$target_month_card'";
$result_revenue_this_month_card = $conn->query($sql_revenue_this_month_card);
$revenue_this_month_card_data = $result_revenue_this_month_card->fetch_assoc();
$pendapatan_bulan_kartu = $revenue_this_month_card_data['revenue_month'] ?? 0;

// 11. Jumlah Pesanan berdasarkan status (Keseluruhan)
$sql_pending_orders_count = "SELECT COUNT(id_pesanan) AS count_pending FROM pesanan WHERE status = 'pending'";
$result_pending_orders_count = $conn->query($sql_pending_orders_count);
$jumlah_pesanan_pending = ($result_pending_orders_count->fetch_assoc()['count_pending']) ?? 0;

$sql_processing_orders_count = "SELECT COUNT(id_pesanan) AS count_processing FROM pesanan WHERE status = 'diproses'";
$result_processing_orders_count = $conn->query($sql_processing_orders_count);
$jumlah_pesanan_diproses = ($result_processing_orders_count->fetch_assoc()['count_processing']) ?? 0;

$sql_cancelled_orders_count = "SELECT COUNT(id_pesanan) AS count_cancelled FROM pesanan WHERE status = 'dibatalkan'";
$result_cancelled_orders_count = $conn->query($sql_cancelled_orders_count);
$jumlah_pesanan_dibatalkan = ($result_cancelled_orders_count->fetch_assoc()['count_cancelled']) ?? 0;


// --- DATA UNTUK GRAFIK (DIPENGARUHI FILTER TANGGAL) ---
$flot_chart_data_revenue_cat = [];
$flot_chart_ticks_revenue_cat = [];
$revenue_map_flot = [];
$line_chart_labels_customers = [];
$line_chart_data_customers = [];
$customer_data_map = [];

if ($use_monthly_filter) {
    $days_in_selected_month_rev = cal_days_in_month(CAL_GREGORIAN, (int)$selected_filter_month, (int)$selected_filter_year);
    $sql_daily_revenue_flot = "SELECT DAY(waktu_pesan) as hari_ke, SUM(total_harga) as harian
                               FROM pesanan
                               WHERE status='selesai' AND YEAR(waktu_pesan) = '$selected_filter_year' AND MONTH(waktu_pesan) = '$selected_filter_month'
                               GROUP BY DATE(waktu_pesan), hari_ke ORDER BY waktu_pesan ASC";
    $result_daily_revenue_flot_query = $conn->query($sql_daily_revenue_flot); // Query dieksekusi di sini
    if ($result_daily_revenue_flot_query) {
        while ($row = $result_daily_revenue_flot_query->fetch_assoc()) {
            $revenue_map_flot[(int)$row['hari_ke']] = (float)$row['harian'];
        }
    }
    for ($day = 1; $day <= $days_in_selected_month_rev; $day++) {
        $date_obj_for_label = DateTime::createFromFormat('!Y-m-d', "$selected_filter_year-$selected_filter_month-$day");
        $flot_chart_data_revenue_cat[] = [$day, $revenue_map_flot[$day] ?? 0];
        $flot_chart_ticks_revenue_cat[] = [$day, $date_obj_for_label ? $date_obj_for_label->format('d M') : "$day"];
    }

    $days_in_selected_month_cust = $days_in_selected_month_rev;
    $sql_daily_new_customers = "SELECT DAY(waktu_masuk) as hari_ke, COUNT(id_pelanggan) as jumlah_pelanggan_baru
                                FROM pelanggan
                                WHERE YEAR(waktu_masuk) = '$selected_filter_year' AND MONTH(waktu_masuk) = '$selected_filter_month'
                                GROUP BY DATE(waktu_masuk), hari_ke ORDER BY waktu_masuk ASC";
    $result_daily_new_customers_query = $conn->query($sql_daily_new_customers); // Query dieksekusi di sini
    if ($result_daily_new_customers_query) {
        while ($row = $result_daily_new_customers_query->fetch_assoc()) {
            $customer_data_map[(int)$row['hari_ke']] = (int)$row['jumlah_pelanggan_baru'];
        }
    }
    for ($day = 1; $day <= $days_in_selected_month_cust; $day++) {
        $date_obj_for_label = DateTime::createFromFormat('!Y-m-d', "$selected_filter_year-$selected_filter_month-$day");
        $line_chart_labels_customers[] = $date_obj_for_label ? $date_obj_for_label->format('d M') : "$day";
        $line_chart_data_customers[] = $customer_data_map[$day] ?? 0;
    }
} else {
    $sql_daily_revenue_flot_30d = "SELECT DATE(waktu_pesan) as tanggal_key, DATE_FORMAT(waktu_pesan, '%d %b') as tanggal_label, SUM(total_harga) as harian
                                   FROM pesanan
                                   WHERE status='selesai' AND waktu_pesan >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) AND waktu_pesan < CURDATE() + INTERVAL 1 DAY
                                   GROUP BY tanggal_key, tanggal_label ORDER BY tanggal_key ASC";
    $result_daily_revenue_flot_30d_query = $conn->query($sql_daily_revenue_flot_30d); // Query dieksekusi
    if ($result_daily_revenue_flot_30d_query) {
        while ($row = $result_daily_revenue_flot_30d_query->fetch_assoc()) {
            $revenue_map_flot[$row['tanggal_label']] = (float)$row['harian'];
        }
    }
    $index_cat_flot = 0;
    for ($i = 29; $i >= 0; $i--) {
        $date_obj = new DateTime("-$i days");
        $date_label_key = $date_obj->format('d M');
        $flot_chart_data_revenue_cat[] = [$index_cat_flot, $revenue_map_flot[$date_label_key] ?? 0];
        $flot_chart_ticks_revenue_cat[] = [$index_cat_flot, $date_label_key];
        $index_cat_flot++;
    }

    $sql_daily_new_customers_30d = "SELECT DATE(waktu_masuk) as tanggal, COUNT(id_pelanggan) as jumlah_pelanggan_baru
                                    FROM pelanggan
                                    WHERE waktu_masuk >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) AND waktu_masuk < CURDATE() + INTERVAL 1 DAY
                                    GROUP BY DATE(waktu_masuk) ORDER BY tanggal ASC";
    $result_daily_new_customers_30d_query = $conn->query($sql_daily_new_customers_30d); // Query dieksekusi
    if ($result_daily_new_customers_30d_query) {
        while ($row = $result_daily_new_customers_30d_query->fetch_assoc()) {
            $customer_data_map[$row['tanggal']] = (int)$row['jumlah_pelanggan_baru'];
        }
    }
    for ($i = 29; $i >= 0; $i--) {
        $date_obj = new DateTime("-$i days");
        $date_key = $date_obj->format('Y-m-d');
        $line_chart_labels_customers[] = $date_obj->format('d M');
        $line_chart_data_customers[] = $customer_data_map[$date_key] ?? 0;
    }
}

// 14. Data untuk Bar Chart (Item Menu Terlaris - tidak dipengaruhi filter)
$sql_top_menu_items = "SELECT m.nama_menu, SUM(dp.jumlah) as total_dipesan
                       FROM detail_pesanan dp JOIN menu m ON dp.id_menu = m.id_menu
                       GROUP BY dp.id_menu, m.nama_menu ORDER BY total_dipesan DESC LIMIT 7";
$result_top_menu_items_query = $conn->query($sql_top_menu_items); // Query dieksekusi
$bar_chart_labels_menu = [];
$bar_chart_data_menu = [];
if ($result_top_menu_items_query && $result_top_menu_items_query->num_rows > 0) {
    while ($row = $result_top_menu_items_query->fetch_assoc()) {
        $bar_chart_labels_menu[] = $row['nama_menu'];
        $bar_chart_data_menu[] = (int)$row['total_dipesan'];
    }
}

// 15. Data untuk Column Chart (Pendapatan Harian untuk kartu)
$column_chart_data_flot = [];
$sql_monthly_daily_revenue_card_graph = "SELECT DAY(waktu_pesan) as hari, SUM(total_harga) as harian
                                   FROM pesanan WHERE status='selesai' AND MONTH(waktu_pesan) = '$target_month_card' AND YEAR(waktu_pesan) = '$target_year_card'
                                   GROUP BY DAY(waktu_pesan) ORDER BY hari ASC";
$result_monthly_daily_revenue_card_graph = $conn->query($sql_monthly_daily_revenue_card_graph); // Query dieksekusi
$days_in_target_month_card = cal_days_in_month(CAL_GREGORIAN, (int)$target_month_card, (int)$target_year_card);
$revenue_by_day_col = [];
if ($result_monthly_daily_revenue_card_graph) {
    while ($row = $result_monthly_daily_revenue_card_graph->fetch_assoc()) {
        $revenue_by_day_col[(int)$row['hari']] = (float)$row['harian'];
    }
}
for ($i = 1; $i <= $days_in_target_month_card; $i++) {
    $column_chart_data_flot[] = [$i, $revenue_by_day_col[$i] ?? 0];
}


// Siapkan array bulan dan tahun untuk dropdown filter
$months_for_filter = [];
for ($m = 1; $m <= 12; $m++) {
    $months_for_filter[$m] = DateTime::createFromFormat('!m', $m)->format('F');
}
$years_for_filter = range(date('Y') - 4, date('Y'));

$js_chart_data_bundle = [
    'orderStatusData' => $order_status_data,
    'orderStatusLabels' => $order_status_labels,
    'orderStatusRawCounts' => $order_status_raw_counts,
    'statusColorsMap' => $status_colors_map,
    'flotRevenueCatData' => $flot_chart_data_revenue_cat,
    'flotRevenueCatTicks' => $flot_chart_ticks_revenue_cat,
    'customerLineLabels' => $line_chart_labels_customers,
    'customerLineData' => $line_chart_data_customers,
    'menuBarLabels' => $bar_chart_labels_menu,
    'menuBarData' => $bar_chart_data_menu,
    'columnChartFlotData' => $column_chart_data_flot,
    'isMonthlyFilterActive' => $use_monthly_filter
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Restoran Admin - Dashboard (<?php echo htmlspecialchars($username); ?>)</title>
    <link rel="stylesheet" href="../assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../assets/vendors/flag-icon-css/css/flag-icon.min.css">
    <link rel="stylesheet" href="../assets/vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="../assets/vendors/jquery-bar-rating/css-stars.css" />
    <link rel="stylesheet" href="../assets/vendors/font-awesome/css/font-awesome.min.css" />
    <link rel="stylesheet" href="../assets/css/demo_2/style.css" />
    <link rel="shortcut icon" href="../assets/images/logo.png" />
</head>

<body>
    <div class="container-scroller">
        <?php include '../partials/_navbar.php'; // Sesuaikan path jika perlu 
        ?>
        <div class="container-fluid page-body-wrapper">
            <div class="main-panel">
                <div class="content-wrapper pb-0">
                    <div class="page-header flex-wrap">
                        <div class="header-left">
                            <a href="../pesanan/index.php" class="btn btn-primary mb-2 mb-md-0 mr-2">Lihat Semua Pesanan</a>
                            <a href="../meja/index.php" class="btn btn-outline-primary mb-2 mb-md-0">Kelola Meja</a>
                        </div>
                        <div class="header-right d-flex flex-wrap mt-md-2 mt-lg-0">
                            <div class="d-flex align-items-center">
                                <a href="#">
                                    <p class="m-0 pr-3">Dashboard</p>
                                </a>
                                <a class="pl-3 mr-4" href="#">
                                    <p class="m-0">Selamat Datang, <?php echo htmlspecialchars($username); ?> (<?php echo htmlspecialchars($role); ?>)</p>
                                </a>
                            </div>
                            <a href="../tambah_menu.php" class="btn btn-primary mt-2 mt-sm-0 btn-icon-text"><i class="mdi mdi-silverware-fork-knife"></i> Tambah Menu</a>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body py-3 px-4">
                                    <form method="GET" action="" class="form-row align-items-center">
                                        <div class="col-auto">
                                            <label class="sr-only" for="filter_month">Bulan</label>
                                            <select name="filter_month" id="filter_month" class="form-control form-control-sm">
                                                <?php foreach ($months_for_filter as $num => $name): ?>
                                                    <option value="<?php echo $num; ?>" <?php echo ((int)$num == (int)$selected_filter_month) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($name); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-auto">
                                            <label class="sr-only" for="filter_year">Tahun</label>
                                            <select name="filter_year" id="filter_year" class="form-control form-control-sm">
                                                <?php foreach ($years_for_filter as $year_val): ?>
                                                    <option value="<?php echo $year_val; ?>" <?php echo ($year_val == $selected_filter_year) ? 'selected' : ''; ?>>
                                                        <?php echo $year_val; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-auto">
                                            <button type="submit" class="btn btn-primary btn-sm">Terapkan</button>
                                        </div>
                                        <div class="col-auto">
                                            <a href="index.php" class="btn btn-outline-secondary btn-sm">Reset (30 Hari)</a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-xl-12 stretch-card grid-margin">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between flex-wrap">
                                        <div>
                                            <div class="card-title mb-0">
                                                Pendapatan Penjualan
                                                <span class="text-muted font-weight-normal small">
                                                    <?php if ($use_monthly_filter): ?>
                                                        (<?php echo DateTime::createFromFormat('!m', $selected_filter_month)->format('F') . ' ' . $selected_filter_year; ?>)
                                                    <?php else: ?>
                                                        (30 Hari Terakhir)
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                            <?php
                                            $total_pendapatan_periode_grafik = 0;
                                            if (!empty($js_chart_data_bundle['flotRevenueCatData'])) {
                                                foreach ($js_chart_data_bundle['flotRevenueCatData'] as $dataPoint) {
                                                    $total_pendapatan_periode_grafik += $dataPoint[1];
                                                }
                                            }
                                            ?>
                                            <h3 class="font-weight-bold mb-0">Rp <?php echo number_format($total_pendapatan_periode_grafik, 0, ',', '.'); ?></h3>
                                        </div>
                                        <div class="pt-2">
                                            <div class="d-flex mb-2">
                                                <div class="mr-3"><button type="button" class="btn btn-social-icon btn-inverse-info"><i class="mdi mdi-cart-outline"></i></button></div>
                                                <div>
                                                    <h4 class="mb-0 font-weight-semibold head-count"><?php echo $jumlah_pesanan_total_keseluruhan; ?></h4><span class="font-10 font-weight-semibold text-muted">TOTAL PESANAN</span>
                                                </div>
                                            </div>
                                            <div class="d-flex">
                                                <div class="mr-3"><button type="button" class="btn btn-social-icon btn-inverse-success"><i class="mdi mdi-check-circle-outline"></i></button></div>
                                                <div>
                                                    <h4 class="mb-0 font-weight-semibold head-count"><?php echo $jumlah_pesanan_selesai_keseluruhan; ?></h4><span class="font-10 font-weight-semibold text-muted">PESANAN SELESAI</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flot-chart-wrapper mt-3">
                                        <div id="flotChart" class="flot-chart" style="height: 320px;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-sm-12 stretch-card grid-margin">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div class="card-title">Pelanggan Baru
                                            <span class="text-muted font-weight-normal small">
                                                <?php if ($use_monthly_filter): ?>
                                                    (<?php echo DateTime::createFromFormat('!m', $selected_filter_month)->format('F') . ' ' . $selected_filter_year; ?>)
                                                <?php else: ?> (30 Hari Terakhir) <?php endif; ?>
                                            </span>
                                            <small class="d-block text-muted">Total Pelanggan: <?php echo $jumlah_pelanggan_keseluruhan; ?></small>
                                        </div>
                                    </div>
                                    <div class="line-chart-wrapper mt-2" style="height: 280px;"><canvas id="linechart"></canvas></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-sm-12 stretch-card grid-margin">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div class="card-title">Item Menu Terlaris<small class="d-block text-muted">Top 7 (Keseluruhan)</small></div>
                                    </div>
                                    <div class="bar-chart-wrapper mt-2" style="height: 280px;"><canvas id="barchart"></canvas></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12 mb-2">
                            <h4 class="card-title">Menu Populer (Berdasarkan Likes)</h4>
                        </div>
                        <?php if (!empty($popular_items)): ?>
                            <?php foreach ($popular_items as $p_item): ?>
                                <div class="col-sm-6 col-md-4 stretch-card grid-margin">
                                    <div class="card">
                                        <div class="card-body p-0"><img class="img-fluid w-100" style="height: 200px; object-fit: cover;" src="../../images/<?php echo htmlspecialchars($p_item['gambar']); ?>" alt="<?php echo htmlspecialchars($p_item['nama_menu']); ?>" onerror="this.onerror=null;this.src='../../assets/images/default-food.png';"></div>
                                        <div class="card-body px-3 py-2 text-dark">
                                            <div class="d-flex justify-content-between">
                                                <p class="text-muted font-13 mb-0"><?php echo htmlspecialchars(ucfirst($p_item['kategori'])); ?></p>
                                                <div><i class="mdi mdi-heart text-danger"></i> <?php echo htmlspecialchars($p_item['likes']); ?></div>
                                            </div>
                                            <h5 class="font-weight-semibold" style="min-height: 40px;"><?php echo htmlspecialchars($p_item['nama_menu']); ?></h5>
                                            <div class="d-flex justify-content-between font-weight-semibold">
                                                <p class="mb-0"></p>
                                                <p class="mb-0">Rp <?php echo number_format($p_item['harga'], 0, ',', '.'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <p class="text-center text-muted">Tidak ada item menu populer.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="row">
                        <div class="col-xl-4 grid-margin">
                            <div class="card card-stat stretch-card mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div class="text-white">
                                            <h3 class="font-weight-bold mb-0">Rp <?php echo number_format($pendapatan_bulan_kartu, 0, ',', '.'); ?></h3>
                                            <h6>Pendapatan <?php echo $use_monthly_filter ? DateTime::createFromFormat('!m', $selected_filter_month)->format('F') . ' ' . $selected_filter_year : "Bulan Ini"; ?></h6>
                                        </div>
                                        <div class="flot-bar-wrapper">
                                            <div id="column-chart" class="flot-chart" style="height: 80px;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card stretch-card mb-3">
                                <div class="card-body d-flex flex-wrap justify-content-between">
                                    <div>
                                        <h4 class="font-weight-semibold mb-1 text-black">Pesanan Pending</h4>
                                        <h6 class="text-muted">Menunggu Konfirmasi</h6>
                                    </div>
                                    <h3 class="text-warning font-weight-bold"><?php echo $jumlah_pesanan_pending; ?></h3>
                                </div>
                            </div>
                            <div class="card stretch-card mb-3">
                                <div class="card-body d-flex flex-wrap justify-content-between">
                                    <div>
                                        <h4 class="font-weight-semibold mb-1 text-black">Pesanan Diproses</h4>
                                        <h6 class="text-muted">Sedang Disiapkan</h6>
                                    </div>
                                    <h3 class="text-info font-weight-bold"><?php echo $jumlah_pesanan_diproses; ?></h3>
                                </div>
                            </div>
                            <div class="card mt-3">
                                <div class="card-body d-flex flex-wrap justify-content-between">
                                    <div>
                                        <h4 class="font-weight-semibold mb-1 text-black">Pesanan Dibatalkan</h4>
                                        <h6 class="text-muted">Order Dibatalkan</h6>
                                    </div>
                                    <h3 class="text-danger font-weight-bold"><?php echo $jumlah_pesanan_dibatalkan; ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-8 stretch-card grid-margin">
                            <div class="card">
                                <div class="card-body pb-0">
                                    <h4 class="card-title">Pesanan Terbaru</h4>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table custom-table text-dark">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Pelanggan</th>
                                                    <th>Meja</th>
                                                    <th>Total</th>
                                                    <th>Status</th>
                                                    <th>Waktu</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                if ($result_recent_orders && $result_recent_orders->num_rows > 0) {
                                                    while ($order = $result_recent_orders->fetch_assoc()) {
                                                        echo "<tr>";
                                                        echo "<td>#" . htmlspecialchars($order['id_pesanan']) . "</td>";
                                                        echo "<td>" . htmlspecialchars($order['nama_pelanggan']) . "</td>";
                                                        echo "<td>Meja " . htmlspecialchars($order['id_meja']) . "</td>";
                                                        echo "<td>Rp " . number_format($order['total_harga'], 0, ',', '.') . "</td>";
                                                        $status_class = '';
                                                        switch (strtolower($order['status'])) {
                                                            case 'pending':
                                                                $status_class = 'badge-warning';
                                                                break;
                                                            case 'diproses':
                                                                $status_class = 'badge-info';
                                                                break;
                                                            case 'selesai':
                                                                $status_class = 'badge-success';
                                                                break;
                                                            case 'dibatalkan':
                                                                $status_class = 'badge-danger';
                                                                break;
                                                            default:
                                                                $status_class = 'badge-secondary';
                                                        }
                                                        echo "<td><label class='badge " . $status_class . "'>" . htmlspecialchars(ucfirst($order['status'])) . "</label></td>";
                                                        echo "<td>" . htmlspecialchars(date('d M Y, H:i', strtotime($order['waktu_pesan']))) . "</td>";
                                                        echo "</tr>";
                                                    }
                                                } else {
                                                    echo "<tr><td colspan='6' class='text-center text-muted'>Tidak ada pesanan terbaru.</td></tr>";
                                                }
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <?php if ($result_recent_orders && $result_recent_orders->num_rows > 0 && $jumlah_pesanan_total_keseluruhan > 5): ?>
                                        <a class="text-black d-block pl-4 pt-2 pb-2 pb-lg-0 font-13 font-weight-bold" href="../data_pesanan.php">Lihat Semua Pesanan</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 stretch-card grid-margin">
                            <div class="card border-0">
                                <div class="card-body">
                                    <div class="card-title">Distribusi Status Pesanan</div>
                                    <div class="doughnut-chart-container" style="position: relative; height:220px; width:100%"><canvas id="doughnutChart1"></canvas></div>
                                    <div id="doughnut-chart-legend" class="mt-3 rounded-legend align-self-center flex-grow legend-vertical legend-bottom-left"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php include '../partials/_footer.html'; // Sesuaikan path jika perlu 
                ?>
            </div>
        </div>
    </div>
    <script src="../assets/vendors/js/vendor.bundle.base.js"></script>
    <script src="../assets/vendors/jquery-bar-rating/jquery.barrating.min.js"></script>
    <script src="../assets/vendors/chart.js/Chart.min.js"></script>
    <script src="../assets/vendors/flot/jquery.flot.js"></script>
    <script src="../assets/vendors/flot/jquery.flot.resize.js"></script>
    <script src="../assets/vendors/flot/jquery.flot.categories.js"></script>
    <script src="../assets/vendors/flot/jquery.flot.fillbetween.js"></script>
    <script src="../assets/vendors/flot/jquery.flot.stack.js"></script>
    <script src="../assets/vendors/flot/jquery.flot.tooltip.min.js"></script>
    <script src="../assets/js/off-canvas.js"></script>
    <script src="../assets/js/hoverable-collapse.js"></script>
    <script src="../assets/js/misc.js"></script>
    <script src="../assets/js/settings.js"></script>
    <script src="../assets/js/todolist.js"></script>

    <script id="dashboard-chart-data" type="application/json">
        <?php echo json_encode($js_chart_data_bundle); ?>
    </script>
    <script>
        // KODE JAVASCRIPT DARI assets/js/dashboard_charts.js DITEMPEL DI SINI
        $(function() {
            const chartDataBundle = JSON.parse(document.getElementById('dashboard-chart-data').textContent);

            const colorPrimary = '#007bff';
            const colorPrimaryLight = 'rgba(0, 123, 255, 0.1)';
            const colorPrimaryDark = '#0056b3';
            const colorSuccess = '#28a745';
            const colorWarning = '#ffab00';
            const colorMuted = '#6c757d';

            function createGradient(ctx, color1, color2) {
                const gradient = ctx.createLinearGradient(0, 0, 0, ctx.canvas.height);
                gradient.addColorStop(0, color1);
                gradient.addColorStop(1, color2);
                return gradient;
            }

            // 1. Doughnut Chart (Status Pesanan)
            if (chartDataBundle.orderStatusData && chartDataBundle.orderStatusData.length > 0 && $("#doughnutChart1").length) {
                const doughnutCtx = $("#doughnutChart1").get(0).getContext("2d");
                const data_values_doughnut = chartDataBundle.orderStatusData; // Ubah nama variabel agar unik
                const data_labels_doughnut = chartDataBundle.orderStatusLabels;

                const status_keys_for_color_doughnut = Object.keys(chartDataBundle.orderStatusRawCounts);
                const background_colors_doughnut = status_keys_for_color_doughnut.map(function(key) {
                    return chartDataBundle.statusColorsMap[key.toLowerCase()] || colorMuted;
                });

                const doughnutPieData = {
                    datasets: [{
                        data: data_values_doughnut,
                        backgroundColor: background_colors_doughnut,
                        borderColor: '#fff',
                        borderWidth: 2,
                        hoverOffset: 8
                    }],
                    labels: data_labels_doughnut
                };
                const doughnutPieOptions = {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        animateScale: true,
                        animateRotate: true,
                        duration: 1000
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0,0,0,0.7)',
                            titleFont: {
                                weight: 'bold'
                            },
                            bodyFont: {
                                size: 13
                            },
                            callbacks: {
                                label: function(context) {
                                    let l = context.label || '';
                                    if (l) {
                                        l += ': ';
                                    }
                                    if (context.parsed !== null) {
                                        l += context.parsed + ' Pesanan';
                                    }
                                    return l;
                                }
                            }
                        }
                    },
                    cutout: '70%'
                };
                if (window.doughnutChartInstance1) {
                    window.doughnutChartInstance1.destroy();
                }
                window.doughnutChartInstance1 = new Chart(doughnutCtx, {
                    type: 'doughnut',
                    data: doughnutPieData,
                    options: doughnutPieOptions
                });

                if (document.getElementById('doughnut-chart-legend')) {
                    let legendHtml = '<ul class="chart-legend legend-vertical legend-bottom-left">';
                    doughnutPieData.labels.forEach((label, i) => {
                        legendHtml += `<li><span class="legend-dots" style="background-color:${doughnutPieData.datasets[0].backgroundColor[i]}"></span>${label} (${doughnutPieData.datasets[0].data[i]})</li>`;
                    });
                    legendHtml += '</ul>';
                    $('#doughnut-chart-legend').html(legendHtml);
                }
            } else if ($("#doughnutChart1").length) {
                $("#doughnutChart1").closest('.doughnut-chart-container').html("<p class='text-center text-muted p-3'>Data status pesanan tidak tersedia.</p>");
            }

            // 2. Flot Chart (Sales Revenue)
            if (chartDataBundle.flotRevenueCatData && chartDataBundle.flotRevenueCatData.length > 0 && $("#flotChart").length) {
                const salesRevenueDataCat = chartDataBundle.flotRevenueCatData;
                const salesRevenueTicksCat = chartDataBundle.flotRevenueCatTicks;
                $("#flotChart").empty();
                const flotOptions = {
                    /* ... Opsi Flot dari jawaban sebelumnya ... */
                    series: {
                        lines: {
                            show: true,
                            lineWidth: 2,
                            fill: true,
                            fillColor: {
                                colors: [{
                                    opacity: 0.3
                                }, {
                                    opacity: 0.05
                                }]
                            }
                        },
                        points: {
                            show: true,
                            radius: 4,
                            lineWidth: 2,
                            fill: true,
                            fillColor: "#ffffff",
                            symbol: "circle"
                        },
                        shadowSize: 0,
                        highlightColor: colorPrimaryDark
                    },
                    grid: {
                        hoverable: true,
                        clickable: true,
                        borderColor: "#f0f0f0",
                        borderWidth: {
                            top: 0,
                            right: 0,
                            bottom: 1,
                            left: 1
                        },
                        tickColor: "rgba(0,0,0,0.05)",
                        color: colorMuted,
                        labelMargin: 10
                    },
                    xaxis: {
                        ticks: salesRevenueTicksCat,
                        tickLength: 0,
                        font: {
                            size: 10,
                            family: "inherit",
                            color: colorMuted,
                            weight: "500"
                        }
                    },
                    yaxis: {
                        tickFormatter: function(v, a) {
                            if (v >= 1E6) return "Rp " + (v / 1E6).toFixed(1) + " Jt";
                            if (v >= 1E3) return "Rp " + (v / 1E3).toFixed(0) + " Rb";
                            return "Rp " + v.toLocaleString()
                        },
                        font: {
                            size: 10,
                            family: "inherit",
                            color: colorMuted,
                            weight: "500"
                        },
                        min: 0,
                        tickDecimals: 0,
                        autoscaleMargin: 0.02
                    },
                    legend: {
                        show: true,
                        position: "nw",
                        margin: [10, 10],
                        backgroundColor: "rgba(255, 255, 255, 0.9)",
                        labelBoxBorderColor: "transparent",
                        noColumns: 1
                    },
                    tooltip: true,
                    tooltipOpts: {
                        content: function(l, x, y, fi) {
                            var dL = "";
                            var t = salesRevenueTicksCat.find(tk => tk[0] === x);
                            if (t) dL = t[1];
                            return `<div style="padding:8px 12px;background-color:rgba(0,0,0,0.75);color:white;border-radius:5px;box-shadow:0 2px 5px rgba(0,0,0,0.2);"><div style="font-size:0.9em;margin-bottom:4px;">${dL}</div><strong style="font-size:1.1em;">Rp ${y.toLocaleString()}</strong></div>`;
                        },
                        defaultTheme: false,
                        shifts: {
                            x: 15,
                            y: -35
                        },
                        onHover: function(fi, $tEl) {
                            $tEl.css({
                                opacity: 0
                            }).stop(true, true).animate({
                                opacity: 1
                            }, 200);
                        }
                    }
                };
                const flotSeries = [{
                    data: salesRevenueDataCat,
                    label: "Pendapatan Harian",
                    color: colorPrimary
                }];
                $.plot("#flotChart", flotSeries, flotOptions);
            } else if ($("#flotChart").length) {
                $("#flotChart").html("<p class='text-center text-muted p-5'>Data pendapatan tidak tersedia.</p>");
            }

            // 3. Line Chart (New Customers)
            if (chartDataBundle.customerLineLabels && chartDataBundle.customerLineLabels.length > 1 && $("#linechart").length) {
                const customerLineLabels = chartDataBundle.customerLineLabels;
                const customerLineData = chartDataBundle.customerLineData;
                const lineChartCtx = $("#linechart").get(0).getContext("2d");
                const gradientFillCustomers = createGradient(lineChartCtx, Chart.helpers.color(colorSuccess).alpha(0.4).rgbString(), Chart.helpers.color(colorSuccess).alpha(0.05).rgbString());
                const customerChartData = {
                    /* ... Opsi Line Chart.js dari jawaban sebelumnya ... */
                    labels: customerLineLabels,
                    datasets: [{
                        label: 'Pelanggan Baru',
                        data: customerLineData,
                        borderColor: colorSuccess,
                        backgroundColor: gradientFillCustomers,
                        borderWidth: 2.5,
                        fill: true,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: colorSuccess,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointHoverBorderWidth: 2,
                        tension: 0.4
                    }]
                };
                const customerChartOptions = {
                    /* ... Opsi Line Chart.js ... */
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: colorMuted,
                                autoSkip: false,
                                maxRotation: 0, // <-- UBAH INI
                                minRotation: 0
                            },
                            grid: {
                                color: "#e9ecef",
                                drawBorder: false
                            }
                        },
                        x: {
                            ticks: {
                                color: colorMuted,
                                autoSkip: false,
                                maxRotation: 0, // <-- UBAH INI
                                minRotation: 0
                            },
                            grid: {
                                display: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                color: colorMuted,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0,0,0,0.7)',
                            titleFont: {
                                weight: 'bold'
                            },
                            bodyFont: {
                                size: 13
                            },
                            callbacks: {
                                label: function(c) {
                                    return ' Pelanggan: ' + c.parsed.y;
                                }
                            }
                        }
                    },
                    interaction: {
                        mode: 'index',
                        intersect: false
                    }
                };
                if (window.customerLineChartInstance) {
                    window.customerLineChartInstance.destroy();
                }
                window.customerLineChartInstance = new Chart(lineChartCtx, {
                    type: 'line',
                    data: customerChartData,
                    options: customerChartOptions
                });
            } else if ($("#linechart").length) {
                $("#linechart").parent().html("<p class='text-center text-muted p-5'>Data pelanggan tidak cukup.</p>");
            }

            // 4. Bar Chart (Top Menu Items)
            if (chartDataBundle.menuBarLabels && chartDataBundle.menuBarLabels.length > 0 && $("#barchart").length) {
                const menuBarLabels = chartDataBundle.menuBarLabels;
                const menuBarData = chartDataBundle.menuBarData;
                const barChartCtx = $("#barchart").get(0).getContext("2d");
                const menuChartData = {
                    /* ... Opsi Bar Chart.js dari jawaban sebelumnya ... */
                    labels: menuBarLabels,
                    datasets: [{
                        label: 'Jumlah Dipesan',
                        data: menuBarData,
                        backgroundColor: colorWarning,
                        borderColor: colorWarning,
                        borderWidth: 1,
                        borderRadius: 5,
                        hoverBackgroundColor: Chart.helpers.color(colorWarning).alpha(0.8).rgbString()
                    }]
                };
                const menuChartOptions = {
                    /* ... Opsi Bar Chart.js ... */
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: colorMuted,
                                autoSkip: false,
                                maxRotation: 0, // <-- UBAH INI
                                minRotation: 0
                            },
                            grid: {
                                color: "#e9ecef",
                                drawBorder: false
                            }
                        },
                        x: {
                            ticks: {
                                color: colorMuted,
                                autoSkip: false,
                                maxRotation: 0, // <-- UBAH INI
                                minRotation: 0
                            },
                            grid: {
                                display: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                color: colorMuted,
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0,0,0,0.7)',
                            titleFont: {
                                weight: 'bold'
                            },
                            bodyFont: {
                                size: 13
                            },
                            callbacks: {
                                label: function(c) {
                                    return ' Dipesan: ' + c.parsed.y + ' kali';
                                }
                            }
                        }
                    }
                };
                if (window.menuBarChartInstance) {
                    window.menuBarChartInstance.destroy();
                }
                window.menuBarChartInstance = new Chart(barChartCtx, {
                    type: 'bar',
                    data: menuChartData,
                    options: menuChartOptions
                });
            } else if ($("#barchart").length) {
                $("#barchart").parent().html("<p class='text-center text-muted p-5'>Data menu terlaris tidak tersedia.</p>");
            }

            // 5. Column Chart (Flot Bar)
            if (chartDataBundle.columnChartFlotData && chartDataBundle.columnChartFlotData.length > 0 && $("#column-chart").length) {
                const dailyRevenueMonthData = chartDataBundle.columnChartFlotData;
                $("#column-chart").empty();
                $.plot("#column-chart", [{
                    data: dailyRevenueMonthData,
                    color: "#ffffff",
                    label: "Pendapatan Harian"
                }], {
                    /* ... Opsi Flot Column Chart ... */
                    series: {
                        bars: {
                            show: true,
                            barWidth: 0.5,
                            fill: 1,
                            align: "center",
                            lineWidth: 0
                        },
                        shadowSize: 0
                    },
                    grid: {
                        show: false,
                        hoverable: true
                    },
                    xaxis: {
                        mode: null,
                        show: false,
                        tickLength: 0
                    },
                    yaxis: {
                        show: false
                    },
                    legend: {
                        show: false
                    },
                    tooltip: true,
                    tooltipOpts: {
                        content: function(l, x, y, fi) {
                            return `<div style="padding:3px 5px;background-color:rgba(0,0,0,0.6);color:white;border-radius:3px;font-size:0.9em;">Tgl ${Math.round(x)}: <strong>Rp ${y.toLocaleString()}</strong></div>`;
                        },
                        defaultTheme: false,
                        shifts: {
                            x: 10,
                            y: -25
                        }
                    }
                });
            } else if ($("#column-chart").length) {
                $("#column-chart").html("<div class='text-white text-center small' style='padding-top:30px;'>No data</div>");
            }
       
        });
    </script>
</body>

</html>