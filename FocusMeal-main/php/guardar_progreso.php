<?php
session_start();
include("conexion.php");

// Verificar sesión
if (!isset($_SESSION["usuario"])) {
    header("Location: ../login.html");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id_usuario = $_SESSION["usuario"]["id"];
    $peso = $_POST['peso'] ?? null;
    $calorias_consumidas = $_POST['calorias_consumidas'] ?? null;
    $observaciones = $_POST['observaciones'] ?? null;
    $fecha = date('Y-m-d');

    $sql = "INSERT INTO historial_progreso (id_usuario, fecha, peso, calorias_consumidas, observaciones) 
            VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isdis", $id_usuario, $fecha, $peso, $calorias_consumidas, $observaciones);

    if ($stmt->execute()) {
        header("Location: progreso.php?mensaje=guardado");
    } else {
        header("Location: progreso.php?error=1");
    }
} else {
    header("Location: progreso.php");
}
?>
