<?php
session_start(); // Mulai sesi (opsional, jika diperlukan otentikasi)

include '../koneksi.php';
// Sertakan file konfigurasi global
include 'config.php'; // Sesuaikan path jika config.php ada di direktori yang berbeda

// Periksa apakah pengguna sudah login (opsional, disarankan untuk keamanan)
if (!isset($_SESSION['username'])) {
  // Mungkin tampilkan pesan error atau redirect
  die("Akses ditolak. Mohon login terlebih dahulu.");
}

// Folder tempat gambar QR disimpan: Relatif ke print_qr.php (di /admin/meja/)
$qr_image_folder = "qr_code/";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print QR Code Meja</title>
    <style>
        /* Gaya CSS untuk tampilan cetak */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20mm; /* Padding halaman */
        }
        .qr-container {
            border: 1px solid #ccc;
            padding: 10mm;
            margin-bottom: 20mm; /* Spasi antar QR saat cetak */
            text-align: center;
            display: inline-block; /* Membuat QR per container */
            /* Sesuaikan ukuran jika perlu */
            width: 80mm; /* Lebar container */
            page-break-inside: avoid; /* Hindari pemisah halaman di dalam container */
        }
        .qr-container img {
            max-width: 100%; /* Gambar QR menyesuaikan lebar container */
            height: auto;
            display: block; /* Untuk margin otomatis */
            margin: 0 auto 5mm auto; /* Margin bawah gambar */
        }
        .qr-container p {
            margin: 0;
            font-size: 16px; /* Ukuran font ID meja */
            font-weight: bold;
        }

        /* Media query untuk print - hapus elemen non-cetak, sesuaikan margin, dll */
        @media print {
            body {
                padding: 0; /* Hapus padding di mode print */
            }
            .qr-container {
                border: none; /* Hapus border di mode print */
                margin: 5mm; /* Spasi antar QR saat print */
                /* Setiap QR di halaman baru (opsional, tergantung desain) */
                 page-break-after: auto; /* Bisa 'always' atau 'auto' */
            }
             /* Anda bisa mengatur ukuran per QR lebih tepat di sini jika perlu */
            .qr-container img {
                 max-width: 70mm; /* Contoh ukuran gambar di print */
            }

            /* Sembunyikan elemen yang tidak perlu dicetak */
             .no-print {
                 display: none;
             }
        }
    </style>
</head>
<body>

    <?php
    // Pastikan koneksi terbuka
    if (!isset($conn) || !$conn) {
        include '../koneksi.php';
    }

    // Set header agar browser tahu ini halaman yang siap dicetak
    header('Content-Type: text/html; charset=utf-8');

    if (isset($_GET['id'])) {
        // Print QR per Meja
        $id_meja = $_GET['id'];
        $sql = "SELECT id_meja, image_kode_qr FROM meja WHERE id_meja = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id_meja);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $qr_filename = $row['image_kode_qr'];
            $qr_filepath_php = $qr_image_folder . $qr_filename; // Path gambar fisik di server

            if (file_exists($qr_filepath_php)) {
                echo '<div class="qr-container">';
                // Path gambar untuk browser di halaman print_qr.php (Relatif ke print_qr.php)
                 echo '<img src="' . htmlspecialchars($qr_image_folder . $qr_filename) . '" alt="QR Code Meja ' . htmlspecialchars($row['id_meja']) . '">';
                echo '<p>Meja: ' . htmlspecialchars($row['id_meja']) . '</p>';
                echo '</div>';
            } else {
                echo '<p>File QR Code untuk Meja ' . htmlspecialchars($row['id_meja']) . ' tidak ditemukan di folder ' . htmlspecialchars($qr_image_folder) . '.</p>';
            }
        } else {
            echo '<p>Meja dengan ID ' . htmlspecialchars($id_meja) . ' tidak ditemukan.</p>';
        }

        $stmt->close();

    } elseif (isset($_GET['all']) && $_GET['all'] == 'true') {
        // Print Semua QR Code
        $sql = "SELECT id_meja, image_kode_qr FROM meja ORDER BY id_meja ASC";
        $result = $conn->query($sql);

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $qr_filename = $row['image_kode_qr'];
                $qr_filepath_php = $qr_image_folder . $qr_filename; // Path gambar fisik di server

                if (file_exists($qr_filepath_php)) {
                     echo '<div class="qr-container">';
                    // Path gambar untuk browser
                    echo '<img src="' . htmlspecialchars($qr_image_folder . $qr_filename) . '" alt="QR Code Meja ' . htmlspecialchars($row['id_meja']) . '">';
                    echo '<p>Meja: ' . htmlspecialchars($row['id_meja']) . '</p>';
                    echo '</div>';
                } else {
                     echo '<p>File QR Code untuk Meja ' . htmlspecialchars($row['id_meja']) . ' tidak ditemukan di folder ' . htmlspecialchars($qr_image_folder) . '.</p>';
                }
            }
        } else {
            echo '<p>Tidak ada data meja ditemukan untuk dicetak.</p>';
        }

    } else {
        echo '<p>Parameter print tidak valid. Gunakan ?id= [ID Meja] atau ?all=true</p>';
    }

     // Tutup koneksi jika terbuka
     if (isset($conn) && $conn) {
        $conn->close();
     }
    ?>

    <script>
        // Secara opsional, panggil dialog print secara otomatis setelah halaman dimuat
        window.onload = function() {
            window.print();
             // Optional: tutup tab/jendela setelah cetak jika perlu (perilaku ini mungkin dibatasi oleh browser)
             // setTimeout(function(){ window.close(); }, 1); // Jeda sedikit sebelum mencoba menutup
        };
    </script>

</body>
</html>