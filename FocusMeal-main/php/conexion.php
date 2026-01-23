<?php
$host = "localhost";
$user = "root";
$pass = "";
$db = "focusmeal";
$conn = mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    die("Conexión fallida: " . mysqli_connect_error());
}
?>
