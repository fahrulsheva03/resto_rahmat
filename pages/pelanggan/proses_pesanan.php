<?php
// Start Output Buffering
ob_start();

// Load Composer Autoloader (for Midtrans SDK)
require_once dirname(__FILE__) . '/vendor/autoload.php'; // Adjust path as needed

// Database Configuration
$host = "localhost";
$user = "root";
$pass = "";
$db = "restoran";

$con = mysqli_connect($host, $user, $pass, $db);
if (!$con) {
    error_log("DB Connection Failed: " . mysqli_connect_error());
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to connect to database.']);
    exit;
}

// Enable error reporting (TURN OFF IN PRODUCTION)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set JSON header (will be applied after ob_clean)
// header('Content-Type: application/json'); // Removed from here

// Receive and decode POST data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

error_log("Received data: " . print_r($data, true)); // Log received data

// Input data validation
if (empty($data) || !isset($data['meja']) || !isset($data['notelepon']) || !is_array($data['items']) || !isset($data['metodePembayaran']) || !isset($data['id_pelanggan']) || !isset($data['nama_pemesan'])) {
    error_log('Validation Error: Missing input data.');
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Incomplete order data.']);
    exit;
}

// Extract and sanitize input data
$meja = intval($data['meja']);
$notelepon = htmlspecialchars($data['notelepon']);
$items = $data['items'];
$metodePembayaran = htmlspecialchars($data['metodePembayaran']);
$id_pelanggan = intval($data['id_pelanggan']);
$nama_pemesan = htmlspecialchars($data['nama_pemesan']);

// Validate items not empty
if (empty($items)) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No items in order.']);
    exit;
}

// Validate payment method (based on DB ENUM)
if ($metodePembayaran !== 'kasir' && $metodePembayaran !== 'midtrans') {
    error_log('Validation Error: Invalid payment method: ' . $metodePembayaran);
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid payment method selected.']);
    exit;
}

// Start DB transaction
mysqli_autocommit($con, false);

$id_pesanan = null;

try {
    // 1. Validate & process items, calculate total, prepare data for DB & Midtrans
    $total_harga = 0;
    $midtrans_items = [];
    $processed_items_for_db = [];

    foreach ($items as $item) {
        if (!isset($item['id']) || !isset($item['quantity']) || !isset($item['harga']) || !isset($item['nama'])) {
            error_log("Skipping item with incomplete data: " . print_r($item, true));
            continue; // Skip if essential data is missing
        }

        $id_menu = intval($item['id']);
        $item_quantity = intval($item['quantity']);
        $item_price = floatval($item['harga']);
        $item_name = htmlspecialchars($item['nama']);

        if ($item_quantity <= 0 || $item_price < 0 || $id_menu <= 0) {
            error_log("Skipping item with invalid data (qty, price, or id): " . print_r($item, true));
            continue; // Skip if data is invalid
        }

        $subtotal_item = $item_quantity * $item_price;
        $total_harga += $subtotal_item; // Accumulate total price

        // Data for detail_pesanan table
        $processed_items_for_db[] = [
            'id_menu' => $id_menu,
            'jumlah' => $item_quantity,
            'subtotal' => $subtotal_item,
            'harga_satuan' => $item_price, // Price per unit
        ];

        // Data for Midtrans item_details
        $midtrans_items[] = [
            'id' => (string)$id_menu, // Item ID for Midtrans
            'price' => round($item_price), // Rounded unit price
            'quantity' => $item_quantity,
            'name' => $item_name, // Item name
        ];
    }

    // Check if any valid items were processed
    if (empty($processed_items_for_db)) {
        mysqli_rollback($con); // Rollback transaction
        error_log("No valid items left after processing.");
        throw new Exception("No valid items to process for this order. Transaction rolled back.");
    }

    // Validate total price for Midtrans
    if ($metodePembayaran === 'midtrans' && $total_harga <= 0) {
        mysqli_rollback($con); // Rollback transaction
        error_log("Total price is zero or less for Midtrans payment.");
        throw new Exception("Order total must be more than Rp 0 for Midtrans payment.");
    }


    // 2. Insert main order (pesanan) into DB (status 'pending')
    $status_awal = 'pending';
    $insert_pesanan_query = "INSERT INTO pesanan (id_pelanggan, waktu_pesan, total_harga, status, id_meja, metode_pembayaran) VALUES ('$id_pelanggan', NOW(), '$total_harga', '$status_awal', '$meja', '$metodePembayaran')";

    if (mysqli_query($con, $insert_pesanan_query)) {
        $id_pesanan = mysqli_insert_id($con);
        error_log("Pesanan main entry created with ID: " . $id_pesanan);
    } else {
        mysqli_rollback($con); // Rollback on failure
        error_log("Failed to insert main pesanan: " . mysqli_error($con));
        throw new Exception("Failed to create main order in database.");
    }

    // 3. Insert order details (detail_pesanan) into DB
    foreach ($processed_items_for_db as $item_detail) {
        $id_menu = $item_detail['id_menu'];
        $jumlah = $item_detail['jumlah'];
        $subtotal = $item_detail['subtotal'];
        $harga_satuan = $item_detail['harga_satuan'];

        // Insert detail including id_pesanan, id_menu, jumlah, subtotal, and harga_satuan
        $insert_detail_query = "INSERT INTO detail_pesanan (id_pesanan, id_menu, jumlah, subtotal, harga_satuan) VALUES ('$id_pesanan', '$id_menu', '$jumlah', '$subtotal', '$harga_satuan')";

        if (!mysqli_query($con, $insert_detail_query)) {
            error_log("Failed to insert detail for pesanan ID " . $id_pesanan . ", menu ID " . $id_menu . ": " . mysqli_error($con));
            mysqli_rollback($con); // Rollback on failure
            throw new Exception("Failed to create order detail for menu ID $id_menu.");
        }
        error_log("Pesanan detail inserted for pesanan ID " . $id_pesanan . ", menu ID " . $id_menu);
    }

    // Commit the database transaction
    if (!mysqli_commit($con)) {
        error_log("Failed to commit DB transaction for pesanan ID " . $id_pesanan . ": " . mysqli_error($con));
        throw new Exception("Failed to save order changes to database.");
    }
    error_log("DB transaction committed for pesanan ID " . $id_pesanan);


    // Database insert success. Handle Midtrans payment if selected.
    if ($metodePembayaran === 'midtrans') {
        // 4. Midtrans SDK Configuration
        Midtrans\Config::$serverKey = 'SB-Mid-server-5ZxhJlLGX3qIpum44kFMgHgB'; // CHANGE ME IN PRODUCTION
        Midtrans\Config::$isProduction = false; // CHANGE ME TO TRUE IN PRODUCTION
        Midtrans\Config::$isSanitized = true;
        Midtrans\Config::$is3ds = true;

        // 5. Prepare customer details from input
        $nama_parts = explode(' ', $nama_pemesan, 2);
        $customer_details = [
            'first_name' => isset($nama_parts[0]) ? trim($nama_parts[0]) : 'Pelanggan',
            'phone' => $notelepon,
            // Add email if available from input or DB
        ];
        if (empty($customer_details['last_name']) && !empty($customer_details['first_name'])) {
            $customer_details['last_name'] = $customer_details['first_name'];
        }


        // 6. Prepare Midtrans Transaction Parameters
        // Order ID must be unique. Using order ID from DB + suffix
        $midtrans_order_id = (string)$id_pesanan . '-' . uniqid();

        $params = [
            'transaction_details' => [
                'order_id' => $midtrans_order_id,
                'gross_amount' => round($total_harga), // Round gross amount
            ],
            'item_details' => $midtrans_items,
            'customer_details' => $customer_details,
            // Set Notification URL in Midtrans Dashboard! Callback URLs below are optional client-side redirect.
            // 'callbacks' => [ ... ]
        ];

        // 7. Get Snap Token from Midtrans API
        try {
            $snapToken = Midtrans\Snap::getSnapToken($params);
            error_log("Snap Token generated for pesanan ID " . $id_pesanan);

            // --- SUCCESS RESPONSE: MIDTRANS ---
            ob_clean();
            header('Content-Type: application/json');
            $response = [
                'success' => true,
                'message' => 'Order created successfully.',
                'metode_pembayaran' => 'midtrans',
                'id_pesanan' => $id_pesanan,
                'snap_token' => $snapToken,
                'total_harga' => $total_harga,
                'midtrans_order_id' => $midtrans_order_id,
            ];
            echo json_encode($response);
            exit; // Stop script execution

        } catch (Exception $e) {
            ob_clean();
            header('Content-Type: application/json');
            error_log("Midtrans General Exception for pesanan ID " . $id_pesanan . ": " . $e->getMessage());
            // Note: DB is committed. Need manual action if Midtrans API fails here.
            echo json_encode(['success' => false, 'message' => 'Error contacting payment gateway.']);
            exit; // Stop script execution
        }
    } else { // Payment method is 'kasir'
        // DB transaction committed above. Status is 'pending'. Admin processes manually.

        // --- SUCCESS RESPONSE: KASIR ---
        ob_clean();
        header('Content-Type: application/json');
        $response = [
            'success' => true,
            'message' => 'Cashier order created successfully.',
            'metode_pembayaran' => 'kasir',
            'id_pesanan' => $id_pesanan,
            'total_harga' => $total_harga,
        ];
        echo json_encode($response);
        exit; // Stop script execution
    }
} catch (Exception $e) {
    // Catch any unhandled exceptions (validation, DB errors BEFORE commit)
    if ($con && mysqli_ping($con)) {
        mysqli_rollback($con); // Attempt rollback if transaction might be active
        error_log("DB transaction potentially rolled back for pesanan ID (if exists) " . ($id_pesanan ?? 'N/A') . " due to exception: " . $e->getMessage());
    }

    error_log("Global Exception Caught for pesanan ID (if exists) " . ($id_pesanan ?? 'N/A') . ": " . $e->getMessage());
    // Log stack trace for debugging if needed: error_log("Trace: " . $e->getTraceAsString());

    // --- GLOBAL ERROR RESPONSE ---
    ob_clean();
    header('Content-Type: application/json');
    $errorMessageForUser = 'An internal server error occurred.';
    if (ini_get('display_errors')) { // Include technical detail only in dev mode
        $errorMessageForUser .= ' Detail: ' . $e->getMessage();
    }
    echo json_encode(['success' => false, 'message' => $errorMessageForUser]);
    exit; // Stop script execution

} finally {
    // Ensure DB connection is closed if it's still active.
    // Due to 'exit;' this block's execution might not be guaranteed in older PHP versions/SAPIs.
    // However, closing manually is good practice.
    if ($con && mysqli_ping($con)) {
        mysqli_autocommit($con, true); // Restore default autocommit
        mysqli_close($con);
        error_log("DB connection closed.");
    }
    // ob_end_flush() or ob_end_clean() here if you were NOT using exit in all paths.
}

// Make sure no whitespace or other output follows this closing tag (or omit closing tag)