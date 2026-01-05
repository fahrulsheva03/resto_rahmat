<?php
session_start();
session_destroy();
header("Location: ../admin/auth/login.php");
exit();
?>