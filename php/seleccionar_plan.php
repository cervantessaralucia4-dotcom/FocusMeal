<?php
session_start();
include("conexion.php");

if (!isset($_SESSION['usuario_id'])) {
    header("Location: index.html");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$plan = $_POST['plan'];

$sql = "UPDATE usuarios SET plan = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $plan, $usuario_id);
$stmt->execute();

header("Location: dashboard.php");
exit();
?>