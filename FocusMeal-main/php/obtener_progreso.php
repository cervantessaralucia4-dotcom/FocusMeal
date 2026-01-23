<?php
session_start();
include("conexion.php");
header('Content-Type: application/json');

// Verificar sesión
if (!isset($_SESSION["usuario"])) {
    http_response_code(401);
    echo json_encode(["error" => "No autorizado"]);
    exit;
}

$id_usuario = $_SESSION["usuario"]["id"];

// Obtener últimos 30 días de progreso
$sql = "SELECT fecha, peso, calorias_consumidas FROM historial_progreso 
        WHERE id_usuario = ? AND fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ORDER BY fecha ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$resultado = $stmt->get_result();

$datos = [];
while ($fila = $resultado->fetch_assoc()) {
    $datos[] = $fila;
}

echo json_encode($datos);
?>
