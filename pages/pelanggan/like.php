<?php
$host = "localhost";
$user = "root";
$pass = "";
$db = "restoran";

$con = mysqli_connect($host, $user, $pass, $db);
if (!$con) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $idMenu = isset($_POST["id_menu"]) ? mysqli_real_escape_string($con, $_POST["id_menu"]) : 0;
    $isLiked = isset($_POST["isLiked"]) ? filter_var($_POST["isLiked"], FILTER_VALIDATE_BOOLEAN) : false;

    if ($idMenu == 0) {
        echo "Error: ID Menu tidak valid.";
        exit;
    }

    // Update the likes count based on the isLiked status
    $sql = "UPDATE menu SET likes = CASE WHEN '$isLiked' = 1 THEN likes + 1 ELSE likes - 1 END WHERE id_menu = '$idMenu'";

    if (mysqli_query($con, $sql)) {
           $sql_select = "SELECT likes FROM menu WHERE id_menu = '$idMenu'";
        $result = mysqli_query($con, $sql_select);
        $row = mysqli_fetch_assoc($result);
        $likes = $row['likes'];

        echo $likes; // Kirim jumlah suka yang baru ke JavaScript
    } else {
        echo "Error: " . $sql . "<br>" . mysqli_error($con);
    }
} else {
    echo "Permintaan tidak valid.";
}
?>