<?php
session_start();

// Sertakan file koneksi.php
include '../koneksi.php';

// Periksa apakah pengguna sudah login
if (!isset($_SESSION['username'])) {
  header("Location: ../auth/index.php?login_required=true"); // Redirect ke login dengan pemberitahuan
  exit();
}

// Ambil informasi pengguna dari sesi
$username = $_SESSION['username'];
$role = $_SESSION['role']; // Ambil juga role pengguna

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Resto-Rahmat - Menu</title>
    <!-- plugins:css -->
    <link rel="stylesheet" href="../assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../assets/vendors/flag-icon-css/css/flag-icon.min.css">
    <link rel="stylesheet" href="../assets/vendors/css/vendor.bundle.base.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" async defer></script>

    <!-- Font Awesome CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" integrity="sha512-9usAa10IRO0HhonpyAIVpjrylPvoDwiPUiKdWk5t3PyolY1cOd4DSE0Ga+ri4AuTroPR5aQvXU9xC6qOPnzFeg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- endinject -->
    <!-- Plugin css for this page -->
    <!-- End plugin css for this page -->
    <!-- inject:css -->
    <link rel="stylesheet" href="../assets/css/demo_2/style.css" />
    <!-- End layout styles -->
    <link rel="shortcut icon" href="../assets/images/logo.png" />
    <style>
        .image-popup {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .image-popup img {
            max-width: 90%;
            max-height: 90%;
        }
    </style>
</head>

<body>
    <div class="container-scroller">

        <?php include '../partials/_navbar.php' ?>

        <div class="container-fluid page-body-wrapper">
            <div class="main-panel">
                <div class="content-wrapper">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                        <div class="header-left">
                            <a href="tambah_menu.php" class="btn btn-primary mb-2 mb-md-0 mr-2">Tambah Menu Baru</a>

                        </div>

                        <div class="d-flex align-items-center">
                            <select id="sortSelect" class="form-control form-control-sm" onchange="sortTable()" style="width: 200px;">
                                <option value="">-- Urutkan --</option>
                                <option value="likes_desc">Like Terbanyak</option>
                                <option value="harga_asc">Harga Terendah</option>
                                <option value="harga_desc">Harga Tertinggi</option>
                            </select>
                            <div class="input-group input-group-sm mr-2" style="width: 250px;">
                                <input type="text" id="searchInput" class="form-control" placeholder="Cari menu...">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="button" onclick="searchTable()">Cari</button>
                                    <button class="btn btn-danger" type="button" onclick="clearSearch()">Batal</button>
                                </div>
                            </div>

                        </div>
                    </div>


                    <?php
                    include '../koneksi.php';

                    // Delete Menu Item (if requested)
                    if (isset($_GET['delete_id'])) {
                        $delete_id = $_GET['delete_id'];
                        $sql_delete = "DELETE FROM menu WHERE id_menu = ?";
                        $stmt_delete = $conn->prepare($sql_delete);
                        $stmt_delete->bind_param("i", $delete_id);

                        if ($stmt_delete->execute()) {
                            echo "<script>
                            document.addEventListener('DOMContentLoaded', function() {
                                function showDeleteAlert() {
                                    Swal.fire({
                                        title: 'Berhasil!',
                                        text: 'Menu berhasil dihapus!',
                                        icon: 'success',
                                        confirmButtonText: 'OK'
                                    }).then(() => {
                                        window.location.href = 'index.php'; // Refresh the page
                                    });
                                }

                                if (typeof Swal !== 'undefined') {
                                    showDeleteAlert();
                                } else {
                                    setTimeout(showDeleteAlert, 50);
                                }
                            });
                            </script>";
                        } else {
                            echo "<script>
                            document.addEventListener('DOMContentLoaded', function() {
                                function showErrorAlert() {
                                    Swal.fire({
                                        title: 'Error!',
                                        text: 'Gagal menghapus menu: " . $stmt_delete->error . "',
                                        icon: 'error',
                                        confirmButtonText: 'OK'
                                    });
                                }

                                if (typeof Swal !== 'undefined') {
                                    showErrorAlert();
                                } else {
                                    setTimeout(showErrorAlert, 50);
                                }
                            });
                            </script>";
                        }
                    }

                    // Function to display menu items for a given category
                    function displayMenuItems($conn, $category)
                    {
                        $category_icon = ($category == "makanan") ? " " : " ";
                        echo "<h3>" . $category_icon . htmlspecialchars(ucfirst($category)) . "</h3>";
                        echo "<div class='grid-margin stretch-card'>";
                        echo "<div class='card'>";
                        echo "<div class='card-body'>";
                        echo "<div class='table-responsive'>";
                        echo "<table class='table table-hover'>";
                        echo "<thead class='text-center'>";
                        echo "<tr><th>NO</th><th>Gambar</th><th>Nama Menu</th><th>Harga</th><th>Likes</th><th>Action</th></tr>";
                        echo "</thead>";
                        echo "<tbody class='text-center'>";

                        $sql = "SELECT * FROM menu WHERE kategori = ? ORDER BY waktu DESC";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("s", $category);
                        $stmt->execute();
                        $result = $stmt->get_result();

                        $row_number = 1;  // Initialize row counter for this category

                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                // Format angka Rupiah
                                $harga_rupiah = "Rp " . number_format($row["harga"], 0, ',', '.');

                                echo "<tr>";
                                echo "<td>" . $row_number . "</td>";  // Use the row counter
                                echo "<td><a href='#' onclick='showImage(\"../../images/" . $row["gambar"] . "\", \"" . $row["nama_menu"] . "\"); return false;'><img src='../../images/" . $row["gambar"] . "' alt='" . $row["nama_menu"] . "' style='width: 150px; height: 150px;'></a></td>";
                                echo "<td>" . $row["nama_menu"] . "</td>";
                                echo "<td>" . $harga_rupiah . "</td>"; // Tampilkan harga yang sudah diformat
                                echo "<td><i class='fas fa-heart' style='color: red;'></i> " . $row["likes"] . "</td>";
                                echo "<td>
                                    <a href='edit.php?id=" . $row["id_menu"] . "' class='btn btn-sm btn-primary'><i class='fas fa-edit'></i> Edit</a>
                                    <button onclick='confirmDelete(" . $row["id_menu"] . ")' class='btn btn-sm btn-danger'><i class='fas fa-trash'></i> Delete</button>

                                </td>";
                                echo "</tr>";
                                $row_number++; // Increment the counter
                            }
                        } else {
                            echo "<tr><td colspan='6'>No " . htmlspecialchars($category) . " items found.</td></tr>";
                        }

                        echo "</tbody>";
                        echo "</table>";
                        echo "</div>";
                        echo "</div>";
                        echo "</div>";
                        echo "</div>";
                    }

                    // Display menu items by category
                    displayMenuItems($conn, "makanan");
                    displayMenuItems($conn, "minuman");

                    $conn->close();
                    ?>
                </div>
                <div class="image-popup" id="imagePopup">
                    <img src="" alt="Full Size Image" id="popupImage">
                </div>
            </div>
            <!-- content-wrapper ends -->
        </div>
        <!-- main-panel ends -->
    </div>
    <!-- page-body-wrapper ends -->
    </div>
    <!-- container-scroller -->
    <!-- plugins:js -->
    <script src="../assets/vendors/js/vendor.bundle.base.js"></script>
    <!-- endinject -->
    <!-- Plugin js for this page -->
    <!-- End plugin js for this page -->
    <!-- inject:js -->
    <script src="../assets/js/off-canvas.js"></script>
    <script src="../assets/js/hoverable-collapse.js"></script>
    <script src="../assets/js/misc.js"></script>
    <script src="../assets/js/settings.js"></script>
    <script src="../assets/js/todolist.js"></script>
    <!-- endinject -->
    <!-- Custom js for this page -->
    <!-- End custom js for this page -->

    <script>
        function showImage(imageSrc, imageName) {
            document.getElementById('popupImage').src = imageSrc;
            document.getElementById('popupImage').alt = imageName;
            document.getElementById('imagePopup').style.display = 'flex';
        }

        document.getElementById('imagePopup').addEventListener('click', function() {
            this.style.display = 'none';
        });
    </script>
<script>
    function confirmDelete(id) {
        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: "Menu ini akan dihapus secara permanen!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ya, hapus!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'index.php?delete_id=' + id;
            }
        });
    }

    function searchTable() {
        var input, filter, tables, tr, td, i, j, txtValue;
        input = document.getElementById("searchInput");
        filter = input.value.toLowerCase();
        tables = document.querySelectorAll(".table tbody");

        tables.forEach(function (tbody) {
            tr = tbody.getElementsByTagName("tr");
            for (i = 0; i < tr.length; i++) {
                let found = false;
                td = tr[i].getElementsByTagName("td");
                for (j = 0; j < td.length; j++) {
                    if (td[j]) {
                        txtValue = td[j].textContent || td[j].innerText;
                        if (txtValue.toLowerCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                tr[i].style.display = found ? "" : "none";
            }
        });
    }

    function clearSearch() {
        document.getElementById("searchInput").value = "";
        searchTable(); // reset hasil pencarian
    }

    function sortTable() {
        const sortType = document.getElementById('sortSelect').value;
        const tables = document.querySelectorAll(".table tbody");

        tables.forEach(function (tbody) {
            let rows = Array.from(tbody.querySelectorAll('tr')).filter(tr => tr.style.display !== 'none');
            rows.sort(function (a, b) {
                let valA, valB;

                if (sortType === "likes_desc") {
                    valA = parseInt(a.children[4].innerText.replace(/\D/g, '')) || 0;
                    valB = parseInt(b.children[4].innerText.replace(/\D/g, '')) || 0;
                    return valB - valA;
                } else if (sortType === "harga_asc") {
                    valA = parseInt(a.children[3].innerText.replace(/\D/g, '')) || 0;
                    valB = parseInt(b.children[3].innerText.replace(/\D/g, '')) || 0;
                    return valA - valB;
                } else if (sortType === "harga_desc") {
                    valA = parseInt(a.children[3].innerText.replace(/\D/g, '')) || 0;
                    valB = parseInt(b.children[3].innerText.replace(/\D/g, '')) || 0;
                    return valB - valA;
                } else {
                    return 0; // Tidak ada sorting
                }
            });

            rows.forEach(row => tbody.appendChild(row));
        });
    }
</script>


</body>

</html>