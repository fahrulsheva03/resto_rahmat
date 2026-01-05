<?php
include 'koneksi.php';

$id_menu = $_GET['id'];
$sql = "SELECT * FROM menu WHERE id_menu = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_menu);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo "Menu item not found.";
    exit();
}

$row = $result->fetch_assoc();

// Handle form submission
$success = false;
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nama_menu = $_POST["nama_menu"];
    $kategori = $_POST["kategori"];
    $harga = $_POST["harga"];
    $likes = $_POST["likes"];

    if ($_FILES["gambar"]["name"] != "") {
        $old_image = $row["gambar"];
        $old_image_path = "../../images/" . $old_image;
        if (file_exists($old_image_path)) {
            unlink($old_image_path);
        }

        $gambar = $_FILES["gambar"]["name"];
        $tmp_name = $_FILES["gambar"]["tmp_name"];
        $upload_path = "../../images/" . $gambar;
        move_uploaded_file($tmp_name, $upload_path);
    } else {
        $gambar = $row["gambar"];
    }

    $sql = "UPDATE menu SET nama_menu = ?, kategori = ?, harga = ?, gambar = ?, likes = ? WHERE id_menu = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssdsii", $nama_menu, $kategori, $harga, $gambar, $likes, $id_menu);

    if ($stmt->execute()) {
        $success = true;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Edit Menu - resto rahmat</title>
    <link rel="stylesheet" href="../assets/vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="../assets/vendors/flag-icon-css/css/flag-icon.min.css">
    <link rel="stylesheet" href="../assets/vendors/css/vendor.bundle.base.css">
    <link rel="stylesheet" href="../assets/css/demo_2/style.css" />
    <link rel="shortcut icon" href="../assets/images/favicon.png" />
    <style>
        .content-wrapper { background: #f8f9fa; padding: 2rem; }
        .page-header { margin-bottom: 2rem; padding-bottom: 0.5rem; border-bottom: 1px solid #dee2e6; }
        .form-group { margin-bottom: 1.5rem; }
        label { font-weight: bold; }
        .btn-primary { background-color: #007bff; border-color: #007bff; }
        .btn-primary:hover { background-color: #0069d9; border-color: #0062cc; }
    </style>
</head>
<body>
<div class="container-scroller">
    <?php include '../partials/_navbar.php'; ?>
    <div class="container-fluid page-body-wrapper">
        <div class="main-panel">
            <div class="content-wrapper">
                <div class="row justify-content-center">
                    <div class="grid-margin stretch-card col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="form-group">
                                        <label for="nama_menu">Nama Menu:</label>
                                        <input type="text" class="form-control" id="nama_menu" name="nama_menu" value="<?= $row['nama_menu']; ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="kategori">Kategori:</label>
                                        <select class="form-control" id="kategori" name="kategori" required>
                                            <option value="Makanan" <?= ($row['kategori'] == 'Makanan') ? 'selected' : ''; ?>>Makanan</option>
                                            <option value="Minuman" <?= ($row['kategori'] == 'Minuman') ? 'selected' : ''; ?>>Minuman</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="harga">Harga:</label>
                                        <input type="number" step="0.01" class="form-control" id="harga" name="harga" value="<?= $row['harga']; ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="gambar">Gambar:</label>
                                        <input type="file" class="form-control" id="gambar" name="gambar" accept="image/*">
                                        <small class="form-text text-muted">Upload gambar baru untuk mengganti.</small>
                                    </div>
                                    <div class="form-group">
                                        <label for="likes">Likes:</label>
                                        <input type="number" class="form-control" id="likes" name="likes" value="<?= $row['likes']; ?>">
                                    </div>
                                    <button type="submit" class="btn btn-primary">Update Menu Item</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- plugins -->
    <script src="../assets/vendors/js/vendor.bundle.base.js"></script>
    <script src="../assets/js/off-canvas.js"></script>
    <script src="../assets/js/hoverable-collapse.js"></script>
    <script src="../assets/js/misc.js"></script>
    <script src="../assets/js/settings.js"></script>
    <script src="../assets/js/todolist.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <?php if ($success): ?>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            Swal.fire({
                title: 'Berhasil!',
                text: 'Menu berhasil diubah!',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'index.php';
                }
            });
        });
    </script>
    <?php endif; ?>
</div>
</body>
</html>
