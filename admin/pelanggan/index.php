<?php
session_start();

// Sertakan file koneksi.php (path relatif ke admin/meja/)
include '../koneksi.php';

// Periksa apakah pengguna sudah login
if (!isset($_SESSION['username'])) {
    header("Location: ../auth/index.php?login_required=true"); // Redirect ke login
    exit();
}

// Ambil informasi pengguna dari sesi
$username = $_SESSION['username'];
$role = $_SESSION['role'];


// --- Handler AJAX untuk mengambil Detail Pelanggan ---
// Ini bagian yang dipanggil oleh JavaScript saat user klik tombol 'Detail'
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' &&
    isset($_GET['action']) && $_GET['action'] == 'get_customer_details' && isset($_GET['id'])) {

    $id_pelanggan = $_GET['id']; // Ambil ID pelanggan dari request AJAX
    $response = array(); // Array untuk menampung data yang akan di-return dalam format JSON

    // Pastikan koneksi database terbuka untuk request AJAX ini
    if (!isset($conn) || !$conn) {
         include '../koneksi.php';
     }

    if ($conn) {
        // --- Ambil Data Dasar Pelanggan dari Tabel 'pelanggan' ---
        // Select * kolom pelanggan berdasarkan ID yang diminta
        $sql_pelanggan = "SELECT id_pelanggan, nama, notelepon, waktu_masuk FROM pelanggan WHERE id_pelanggan = ?";
        $stmt_pelanggan = $conn->prepare($sql_pelanggan);
        $stmt_pelanggan->bind_param("i", $id_pelanggan);
        $stmt_pelanggan->execute();
        $result_pelanggan = $stmt_pelanggan->get_result();

        if ($result_pelanggan->num_rows > 0) {
            // Jika pelanggan ditemukan, ambil datanya
            $response['customer'] = $result_pelanggan->fetch_assoc();
        } else {
            // Jika pelanggan tidak ditemukan, kirim error
            $response['error'] = 'Pelanggan dengan ID ' . htmlspecialchars($id_pelanggan) . ' tidak ditemukan.';
            $stmt_pelanggan->close();
             $conn->close();
             header('Content-Type: application/json');
             echo json_encode($response);
            exit(); // Hentikan eksekusi setelah mengirim error
        }
        $stmt_pelanggan->close();


        // --- Ambil Daftar Pesanan dari Tabel 'pesanan' untuk Pelanggan Ini ---
        // Select kolom-kolom pesanan WHERE id_pelanggan cocok dengan ID pelanggan yang dicari
        $sql_pesanan = "SELECT id_pesanan, id_meja, waktu_pesan, status, total_harga, metode_pembayaran FROM pesanan WHERE id_pelanggan = ? ORDER BY waktu_pesan DESC"; // Urutkan dari yang terbaru
        $stmt_pesanan = $conn->prepare($sql_pesanan);
        $stmt_pesanan->bind_param("i", $id_pelanggan); // Bind ID pelanggan
        $stmt_pesanan->execute();
        $result_pesanan = $stmt_pesanan->get_result();

        $response['orders'] = array(); // Inisialisasi array untuk pesanan
        if ($result_pesanan->num_rows > 0) {
            // Jika ditemukan pesanan untuk pelanggan ini, loop dan tambahkan ke response
            while ($row = $result_pesanan->fetch_assoc()) {
                 // Lakukan formatting data jika diperlukan sebelum dikirim ke JS
                 $row['total_harga_formatted'] = "Rp " . number_format($row["total_harga"], 0, ',', '.');
                 $row['id_meja_display'] = $row['id_meja'] ?? '-'; // Tampilkan '-' jika id_meja NULL
                 // Tambahkan baris pesanan ini ke dalam array 'orders' di response
                 $response['orders'][] = $row;
            }
        }
         // Tidak masalah jika result_pesanan->num_rows == 0, array 'orders' akan kosong [],
         // JavaScript akan menangani ini sebagai 'Tidak ada pesanan'.


        $stmt_pesanan->close();
        $conn->close(); // Tutup koneksi setelah selesai mengambil data AJAX

        // Set header untuk respon JSON
        header('Content-Type: application/json');
        // Kirim data pelanggan dan pesanan dalam format JSON
        echo json_encode($response);

        exit(); // Hentikan eksekusi script setelah mengirim respon JSON

    } else {
        // Error koneksi database saat request AJAX
        $response['error'] = 'Koneksi database gagal untuk detail pelanggan.';
         header('Content-Type: application/json');
         echo json_encode($response);
         exit();
    }

} // --- Akhir Handler AJAX ---


// --- Jika Bukan Request AJAX (Tampilkan Halaman Utama Daftar Pelanggan HTML) ---
// Bagian ini dieksekusi jika halaman diakses langsung oleh browser
// Buka koneksi untuk menampilkan data tabel HTML utama
 if (!isset($conn) || !$conn) {
    include '../koneksi.php';
 }


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Resto-Rika - Daftar Pelanggan</title> <!-- Judul halaman -->
    <!-- plugins:css (path relatif ke admin/meja/) -->
    <link rel="stylesheet" href="../assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../assets/vendors/flag-icon-css/css/flag-icon.min.css">
    <link rel="stylesheet" href="../assets/vendors/css/vendor.bundle.base.css">
    <!-- SweetAlert2 (tetap ada, mungkin untuk pesan lain nanti) -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Font Awesome CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- endinject -->
    <!-- inject:css -->
    <link rel="stylesheet" href="../assets/css/demo_2/style.css" /> <!-- Path relatif -->
    <!-- End layout styles -->
    <link rel="shortcut icon" href="../assets/images/logo.png" /> <!-- Path relatif -->
    <style>
        /* Gaya yang tidak relevan dihapus atau disembunyikan */
        .image-popup { display: none; }

        /* --- STYLE untuk Panel Detail yang Muncul dari Kanan (Sama seperti sebelumnya) --- */
        .detail-panel {
            position: fixed; top: 0; right: -400px; width: 400px; height: 100%;
            background-color: #f8f9fa; box-shadow: -5px 0 15px rgba(0, 0, 0, 0.2); z-index: 1050;
            transition: right 0.3s ease-in-out; overflow-y: auto; padding: 20px;
            display: flex; flex-direction: column;
        }
         .detail-panel.open { right: 0; }
         .detail-panel .close-btn { position: absolute; top: 10px; right: 10px; font-size: 1.5rem; background: none; border: none; cursor: pointer; color: #333; }
         .detail-panel .loading-state { text-align: center; padding: 20px; }
         .detail-panel .content { flex-grow: 1; }
         .detail-panel .customer-info h5 { margin-top: 0; margin-bottom: 10px; }
         .detail-panel .customer-info p { margin-bottom: 5px; font-size: 0.9rem; }
         .detail-panel .order-list { margin-top: 20px; }
          .detail-panel .order-list h5 { margin-bottom: 10px; }
          .detail-panel .order-table { font-size: 0.85rem; }
           .detail-panel .order-table th, .detail-panel .order-table td { padding: 8px; vertical-align: middle; } /* Tambah vertical-align */

          /* Gaya untuk badge status */
         .status-badge { padding: .35em .65em; font-size: 75%; font-weight: 700; line-height: 1; text-align: center; white-space: nowrap; vertical-align: baseline; border-radius: .25rem; color: #fff; }
         .status-pending { background-color: #ffc107; color: #212529; }
         .status-diproses { background-color: #007bff; }
         .status-selesai { background-color: #28a745; }
         .status-dibatalkan { background-color: #dc3545; }
         .status-sudah_bayar { background-color: #17a2b8; } /* Sesuaikan nama status di sini dan di JS */
         .status-default { background-color: #6c757d; }

        /* Overlay (Sama seperti sebelumnya) */
         .overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 1040; display: none; }
         .overlay.visible { display: block; }

        /* Jarak antar tombol/elemen tabel jika perlu */
         .table th, .table td { vertical-align: middle; } /* Agar konten di tengah sel */

    </style>
</head>

<body>
    <div class="container-scroller">

        <?php include '../partials/_navbar.php'; // Path relatif ?>

        <div class="container-fluid page-body-wrapper">
            <div class="main-panel">
                <div class="content-wrapper">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                        <div class="header-left">
                            <!-- Tombol tambah pelanggan dihapus -->
                            <h4>Daftar Pelanggan</h4>
                        </div>

                        <!-- Search dan Sort (Sama seperti sebelumnya) -->
                        <div class="d-flex align-items-center">
                            <select id="sortSelect" class="form-control form-control-sm mr-2" onchange="sortTable()" style="width: 150px;">
                                <option value="">-- Urutkan --</option>
                                <option value="id_asc">ID Terendah</option>
                                <option value="id_desc">ID Tertinggi</option>
                                <option value="nama_asc">Nama A-Z</option>
                                <option value="nama_desc">Nama Z-A</option>
                                 <option value="waktu_desc">Terbaru (Waktu Masuk)</option>
                                <option value="waktu_asc">Terlama (Waktu Masuk)</option>
                            </select>
                            <div class="input-group input-group-sm" style="width: 250px;">
                                <input type="text" id="searchInput" class="form-control" placeholder="Cari nama/telp...">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="button" onclick="searchTable()">Cari</button>
                                    <button class="btn btn-danger" type="button" onclick="clearSearch()">Batal</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabel Data Pelanggan -->
                    <div class='grid-margin stretch-card'>
                        <div class='card'>
                            <div class='card-body'>
                                <div class='table-responsive'>
                                    <table class='table table-hover' id="pelangganTable"> <!-- Tambahkan ID untuk JS -->
                                        <thead class='text-center'>
                                            <tr>
                                                <th>NO</th>
                                                <th>ID Pelanggan</th>
                                                <th>Nama</th>
                                                <th>No. Telepon</th>
                                                <th>Waktu Masuk</th>
                                                <!-- <th>Action</th> Tetap Kolom Action -->
                                            </tr>
                                        </thead>
                                        <tbody class='text-center'>
                                            <?php
                                            // Buka koneksi untuk menampilkan data tabel
                                            // Pastikan $conn sudah ada, jika tidak, buka lagi. Koneksi AJAX handler sudah ditutup.
                                             if (!isset($conn) || !$conn) {
                                                include '../koneksi.php';
                                             }

                                            // Ambil semua data pelanggan, urutkan DESC
                                            $sql = "SELECT * FROM pelanggan ORDER BY id_pelanggan DESC";
                                            $result = $conn->query($sql);

                                            $row_number = 1;

                                            if ($result && $result->num_rows > 0) {
                                                while ($row = $result->fetch_assoc()) {
                                                    echo "<tr>";
                                                    echo "<td>" . $row_number . "</td>";
                                                    echo "<td>" . htmlspecialchars($row["id_pelanggan"]) . "</td>";
                                                    echo "<td>" . htmlspecialchars($row["nama"]) . "</td>";
                                                    echo "<td>" . htmlspecialchars($row["notelepon"]) . "</td>";
                                                    echo "<td>" . htmlspecialchars($row["waktu_masuk"]) . "</td>";
                                                    echo "</tr>";
                                                    $row_number++; // Increment nomor baris
                                                }
                                            } else {
                                                 // Tampilkan pesan jika tidak ada data
                                                 $colspan_count = 6;
                                                echo "<tr><td colspan='" . $colspan_count . "'>";
                                                 if (!$result) {
                                                     echo "Error mengambil data: " . $conn->error;
                                                 } else {
                                                    echo "Tidak ada data pelanggan ditemukan.";
                                                 }
                                                echo "</td></tr>";
                                            }

                                             // Tutup koneksi database di akhir skrip
                                            if (isset($conn) && $conn) {
                                               $conn->close();
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Akhir Tabel Data Pelanggan -->

                </div>
                <!-- image-popup tetap ada -->
                <div class="image-popup" id="imagePopup">
                    <img src="" alt="Full Size Image" id="popupImage">
                </div>
            </div>
            <!-- main-panel ends -->
        </div>
        <!-- page-body-wrapper ends -->
    </div>
    <!-- container-scroller -->

    <!-- --- PANEL DETAIL yang muncul dari kanan --- -->
     <!-- Lokasinya di luar .container-scroller agar fixed position bekerja baik -->
     <div id="customerDetailPanel" class="detail-panel">
         <button class="close-btn">×</button> <!-- Tombol tutup -->
         <div class="content">
              <div class="loading-state">
                  <i class="fas fa-spinner fa-spin"></i> Memuat detail...
              </div>
              <div class="customer-info" style="display: none;">
                  <h5>Informasi Pelanggan</h5>
                  <!-- Span ini akan diisi oleh JS -->
                  <p><strong>ID:</strong> <span id="detail-id_pelanggan"></span></p>
                  <p><strong>Nama:</strong> <span id="detail-nama"></span></p>
                  <p><strong>Telepon:</strong> <span id="detail-notelepon"></span></p>
                  <p><strong>Masuk:</strong> <span id="detail-waktu_masuk"></span></p>
              </div>
              <div class="order-list" style="display: none;">
                  <h5>Pesanan Pelanggan</h5>
                   <div id="orders-table-container">
                       <!-- Tabel pesanan dari tabel 'pesanan' akan di-generate dan dimasukkan di sini oleh JavaScript -->
                       <p id="no-orders-message" style="display: none;">Tidak ada pesanan ditemukan untuk pelanggan ini.</p>
                   </div>
                   <!-- Catatan: Detail item per pesanan dari 'detail_pesanan' TIDAK ditambilkan di sini -->
              </div>
              <div class="error-state" style="display: none;">
                  <p class="text-danger">Terjadi kesalahan: <span id="detail-error-message"></span></p>
              </div>
         </div>
     </div>
    <!-- --- OVERLAY (untuk latar belakang gelap) --- -->
    <div class="overlay" id="panelOverlay"></div>


    <!-- plugins:js (path relatif ke admin/meja/) -->
    <script src="../assets/vendors/js/vendor.bundle.base.js"></script>
    <!-- endinject -->
    <!-- inject:js -->
    <!-- Path relatif sama dengan CSS -->
    <script src="../assets/js/off-canvas.js"></script>
    <script src="../assets/js/hoverable-collapse.js"></script>
    <script src="../assets/js/misc.js"></script>
    <script src="../assets/js/settings.js"></script>
    <script src="../assets/js/todolist.js"></script>
    <!-- endinject -->

    <script>
        // --- Script untuk Panel Detail AJAX ---
        const detailPanel = document.getElementById('customerDetailPanel');
        const closeBtn = detailPanel.querySelector('.close-btn');
        const loadingState = detailPanel.querySelector('.loading-state');
        const customerInfo = detailPanel.querySelector('.customer-info');
        const orderList = detailPanel.querySelector('.order-list');
        const ordersTableContainer = detailPanel.querySelector('#orders-table-container');
        const noOrdersMessage = detailPanel.querySelector('#no-orders-message');
         const errorState = detailPanel.querySelector('.error-state');
        const detailErrorMessage = detailPanel.querySelector('#detail-error-message');
         const overlay = document.getElementById('panelOverlay'); // Elemen overlay

        // Function untuk menampilkan panel
        function openPanel() {
            detailPanel.classList.add('open');
             overlay.classList.add('visible'); // Tampilkan overlay
             document.body.style.overflow = 'hidden'; // Optional: mencegah scrolling body utama saat panel terbuka
        }

        // Function untuk menyembunyikan panel
        function closePanel() {
            detailPanel.classList.remove('open');
             overlay.classList.remove('visible'); // Sembunyikan overlay
             document.body.style.overflow = ''; // Optional: kembalikan scrolling body
            // Reset konten panel kembali ke loading state
             loadingState.style.display = 'none'; // Awalnya loading tidak ditampilkan
             customerInfo.style.display = 'none';
             orderList.style.display = 'none';
             errorState.style.display = 'none';
             detailErrorMessage.innerText = '';
            ordersTableContainer.innerHTML = ''; // Kosongkan kontainer tabel pesanan
             noOrdersMessage.style.display = 'none';
        }

        // Event listener untuk tombol close panel
        closeBtn.addEventListener('click', closePanel);
         // Event listener untuk overlay agar panel tertutup saat klik di luar panel
         overlay.addEventListener('click', closePanel);
        // Event listener untuk menekan tombol ESC (Opsional)
         document.addEventListener('keydown', function(event) {
             if (event.key === 'Escape' && detailPanel.classList.contains('open')) {
                 closePanel();
             }
         });


        // Event listener utama pada tbody tabel untuk klik tombol 'Detail'
        // Menggunakan event delegation agar lebih efisien dan bekerja untuk baris yang ditambahkan/diubah oleh sort/search
        document.getElementById('pelangganTable').addEventListener('click', function(event) {
            const target = event.target.closest('.btn-detail'); // Cari elemen terdekat yang cocok dengan '.btn-detail' (bisa tombolnya itu sendiri atau icon di dalamnya)

            if (target) {
                const customerId = target.dataset.id; // Ambil ID pelanggan dari atribut data-id tombol

                if (customerId) {
                    // Tampilkan loading state sebelum memuat data
                    loadingState.style.display = 'block';
                    customerInfo.style.display = 'none';
                    orderList.style.display = 'none';
                    errorState.style.display = 'none';
                     ordersTableContainer.innerHTML = ''; // Kosongkan konten sebelumnya
                     noOrdersMessage.style.display = 'none';


                    // Buka panel slide-out
                    openPanel();

                    // Ambil data detail via AJAX (menggunakan Fetch API)
                    // Mengirim request GET ke URL script PHP ini sendiri (index.php)
                    // dengan parameter action=get_customer_details dan id=customerId
                    fetch(`index.php?action=get_customer_details&id=${customerId}`)
                        .then(response => {
                             // Periksa respons HTTP (status ok dan Content-Type JSON)
                             const contentType = response.headers.get('content-type');
                             if (!response.ok) { // Cek status HTTP (misal 404, 500)
                                throw new Error(`HTTP error! status: ${response.status}`);
                            }
                            if (!contentType || !contentType.includes('application/json')) {
                                // Tangani jika respon bukan JSON (mungkin error PHP yang tidak tertangkap di handler AJAX)
                                 console.error('Invalid Content-Type:', contentType, 'Response Text:', response.text ? response.text() : 'N/A'); // Log respons mentah
                                throw new Error('Respon dari server bukan JSON. Mohon periksa error log.');
                             }
                            return response.json(); // Parse respon sebagai objek JSON
                        })
                        .then(data => {
                            loadingState.style.display = 'none'; // Sembunyikan loading

                            // Cek apakah data JSON berisi pesan error dari server PHP handler
                            if (data.error) {
                                errorState.style.display = 'block';
                                detailErrorMessage.innerText = data.error; // Tampilkan pesan error dari PHP
                            } else {
                                // Data detail pelanggan berhasil diambil dan tidak ada error dari PHP
                                customerInfo.style.display = 'block'; // Tampilkan bagian info pelanggan

                                // Isi elemen HTML dengan data pelanggan
                                detailPanel.querySelector('#detail-id_pelanggan').innerText = data.customer.id_pelanggan;
                                detailPanel.querySelector('#detail-nama').innerText = data.customer.nama;
                                detailPanel.querySelector('#detail-notelepon').innerText = data.customer.notelepon;
                                detailPanel.querySelector('#detail-waktu_masuk').innerText = data.customer.waktu_masuk; // Bisa tambahkan library JS untuk format tanggal jika perlu


                                // Bagian daftar pesanan
                                orderList.style.display = 'block'; // Tampilkan bagian daftar pesanan
                                if (data.orders && data.orders.length > 0) {
                                    // Jika ada pesanan, buat tabel pesanan secara dinamis dari data.orders
                                    let orderTableHTML = '<table class="table table-bordered table-striped order-table">';
                                    // Header Tabel Pesanan (sesuai kolom dari tabel 'pesanan')
                                    orderTableHTML += '<thead class="text-center"><tr><th>ID Pesanan</th><th>Meja</th><th>Waktu</th><th>Status</th><th>Total</th><th>Metode</th><th>Aksi</th></tr></thead>'; // Kolom Aksi bisa untuk detail item

                                    orderTableHTML += '<tbody>';
                                    // Loop melalui setiap objek pesanan di array data.orders
                                    data.orders.forEach(order => {
                                         // Tentukan CSS class untuk badge status
                                         let statusClass = 'status-default';
                                          switch (order.status) {
                                             case 'pending': statusClass = 'status-pending'; break;
                                             case 'diproses': statusClass = 'status-diproses'; break;
                                             case 'selesai': statusClass = 'status-selesai'; break;
                                             case 'dibatalkan': statusClass = 'status-dibatalkan'; break;
                                             case 'sudah bayar': statusClass = 'status-sudah_bayar'; break; // Pastikan nama status sesuai dengan DB Anda
                                             // Tambahkan case untuk status lain
                                         }


                                        // Buat baris tabel (tr) untuk setiap pesanan
                                        orderTableHTML += `<tr>
                                             <td>${order.id_pesanan}</td>
                                             <td>${order.id_meja_display}</td> <!-- Tampilkan ID Meja (handle NULL) -->
                                             <td>${order.waktu_pesan}</td> <!-- Tampilkan Waktu Pesan -->
                                              <td><span class="status-badge ${statusClass}">${order.status}</span></td> <!-- Tampilkan Status dengan Badge -->
                                             <td>${order.total_harga_formatted}</td> <!-- Tampilkan Total Harga yang sudah diformat Rp -->
                                             <td>${order.metode_pembayaran ?? '-'}</td> <!-- Tampilkan Metode Pembayaran (handle NULL) -->

                                              <!-- Kolom Aksi per Pesanan (placeholder saat ini) -->
                                             <td>
                                                  <!-- Jika Anda punya halaman detail untuk item pesanan (misal admin/pesanan/detail_item.php), uncomment link berikut -->
                                                  <!-- <a href="../pesanan/detail_item.php?id=" + order.id_pesanan + "' class='btn btn-sm btn-info'><i class='fas fa-list-alt'></i> Item</a> -->
                                                  - <!-- Placeholder jika tidak ada aksi -->
                                              </td>
                                         </tr>`;
                                    });
                                    orderTableHTML += '</tbody></table>';
                                    // Masukkan HTML tabel yang dibuat ke dalam kontainer di panel
                                    ordersTableContainer.innerHTML = orderTableHTML;
                                    noOrdersMessage.style.display = 'none'; // Pastikan pesan "tidak ada pesanan" tersembunyi

                                } else {
                                    // Jika array data.orders kosong atau null (tidak ada pesanan)
                                     ordersTableContainer.innerHTML = ''; // Kosongkan kontainer tabel
                                     noOrdersMessage.style.display = 'block'; // Tampilkan pesan "tidak ada pesanan"
                                }
                            }
                        })
                        .catch(error => {
                            // Tangani error saat proses fetch (masalah jaringan, parsing error, throw Error dari atas)
                            console.error('Error fetching customer details:', error);
                            loadingState.style.display = 'none'; // Sembunyikan loading
                            errorState.style.display = 'block'; // Tampilkan state error
                            detailErrorMessage.innerText = 'Tidak dapat memuat detail. Error teknis. Info: ' + error.message;
                             ordersTableContainer.innerHTML = ''; // Pastikan kontainer tabel kosong
                             noOrdersMessage.style.display = 'none';
                        });
                }
            }
        });

        // Script Search Table (Sama seperti sebelumnya, mencari di Nama dan No. Telp)
         function searchTable() { /* ... function body ... */
              var input, filter, table, tr, td_nama, td_telp, i, txtValue_nama, txtValue_telp;
            input = document.getElementById("searchInput");
            filter = input.value.toLowerCase();
            table = document.getElementById("pelangganTable"); // Gunakan ID tabel
            tbody = table.getElementsByTagName("tbody")[0];
            tr = tbody.getElementsByTagName("tr");

            for (i = 0; i < tr.length; i++) {
                 // Pastikan baris ini punya cukup sel (td)
                 if (tr[i].cells.length < 4) { continue; } // Kolom ke-3 (Nama) atau ke-4 (Telepon) ada?

                td_nama = tr[i].getElementsByTagName("td")[2]; // Nama ada di index 2
                td_telp = tr[i].getElementsByTagName("td")[3]; // Telepon ada di index 3

                let found = false;
                if (td_nama) {
                    txtValue_nama = td_nama.textContent || td_nama.innerText;
                    if (txtValue_nama.toLowerCase().indexOf(filter) > -1) {
                        found = true;
                    }
                }
                if (!found && td_telp) { // Cari di No. Telepon hanya jika belum ketemu di Nama
                     txtValue_telp = td_telp.textContent || td_telp.innerText;
                     let cleaned_telp = txtValue_telp.replace(/\D/g, ''); // Bersihkan dari non-digit
                     let cleaned_filter = filter.replace(/\D/g, '');

                     if (cleaned_telp.indexOf(cleaned_filter) > -1) {
                        found = true;
                    }
                }
                tr[i].style.display = found ? "" : "none";
            }
         }

         function clearSearch() { /* ... function body ... */
              document.getElementById("searchInput").value = "";
              searchTable();
         }

         // Script Sort Table (Sama seperti sebelumnya, disesuaikan untuk kolom pelanggan)
        function sortTable() { /* ... function body ... */
             const sortType = document.getElementById('sortSelect').value;
             const table = document.getElementById('pelangganTable'); // Gunakan ID tabel
             const tbody = table.querySelector('tbody');
             let rows = Array.from(tbody.querySelectorAll('tr')).filter(tr => tr.style.display !== 'none' && tr.cells.length > 1);

             rows.sort(function (a, b) {
                 let valA, valB;
                  // Index Kolom: NO(0), ID Pelanggan(1), Nama(2), No. Telp(3), Waktu Masuk(4), Action(5)

                 if (sortType === "id_asc") {
                      valA = parseInt(a.children[1].innerText) || 0; // ID Pelanggan di index 1
                      valB = parseInt(b.children[1].innerText) || 0;
                      return valA - valB;
                  } else if (sortType === "id_desc") {
                      valA = parseInt(a.children[1].innerText) || 0; // ID Pelanggan di index 1
                      valB = parseInt(b.children[1].innerText) || 0;
                      return valB - valA;
                  } else if (sortType === "nama_asc") {
                      valA = a.children[2].innerText.toLowerCase(); // Nama di index 2
                      valB = b.children[2].innerText.toLowerCase();
                      return valA.localeCompare(valB);
                  } else if (sortType === "nama_desc") {
                      valA = a.children[2].innerText.toLowerCase(); // Nama di index 2
                      valB = b.children[2].innerText.toLowerCase();
                      return valB.localeCompare(valA);
                  } else if (sortType === "waktu_asc") {
                      valA = new Date(a.children[4].innerText); // Waktu Masuk di index 4
                      valB = new Date(b.children[4].innerText);
                       if (isNaN(valA.getTime()) && isNaN(valB.getTime())) return 0;
                       if (isNaN(valA.getTime())) return 1;
                       if (isNaN(valB.getTime())) return -1;
                      return valA - valB;
                  } else if (sortType === "waktu_desc") {
                       valA = new Date(a.children[4].innerText); // Waktu Masuk di index 4
                       valB = new Date(b.children[4].innerText);
                        if (isNaN(valA.getTime()) && isNaN(valB.getTime())) return 0;
                        if (isNaN(valA.getTime())) return 1;
                        if (isNaN(valB.getTime())) return -1;
                      return valB - valA;
                  } else {
                     return 0;
                 }
             });

             while (tbody.firstChild) { tbody.removeChild(tbody.firstChild); }
              rows.forEach(row => tbody.appendChild(row));

              let current_row_number = 1;
              rows.forEach(row => {
                  row.children[0].innerText = current_row_number; // Kolom NO di index 0
                  current_row_number++;
              });
        }

    </script>


</body>

</html>