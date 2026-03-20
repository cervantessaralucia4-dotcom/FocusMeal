<?php
session_start();
require "conexion.php";

if (!isset($_SESSION["usuario"])) {
    http_response_code(401);
    echo json_encode(["error" => "No autenticado"]);
    exit;
}

$usuario_id = $_SESSION["usuario"]["id"];

$stmt = $conn->prepare("
    SELECT fecha, peso, calorias_consumidas, observaciones
    FROM historial_progreso
    WHERE id_usuario = ?
    ORDER BY fecha ASC
");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$resultado = $stmt->get_result();

$datos = [];
while ($fila = $resultado->fetch_assoc()) {
    $datos[] = $fila;
}

header("Content-Type: application/json");
echo json_encode($datos);