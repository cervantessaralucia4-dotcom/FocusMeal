<?php
session_start();
require "conexion.php";

if (!isset($_SESSION["usuario"])) {
    header("Location: ../html/login.html");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: planes.php");
    exit;
}

$usuario_id = $_SESSION["usuario"]["id"];
$id_plan    = intval($_POST["id_plan"] ?? 0);

if ($id_plan <= 0) {
    die("Plan inválido.");
}

// Buscar el plan en planes_disponibles
$stmt = $conn->prepare("SELECT * FROM planes_disponibles WHERE id_plan = ?");
if (!$stmt) {
    die("Error en consulta: " . $conn->error);
}
$stmt->bind_param("i", $id_plan);
$stmt->execute();
$plan = $stmt->get_result()->fetch_assoc();

if (!$plan) {
    die("No se encontró el plan seleccionado.");
}

// Desactivar planes anteriores del usuario
$conn->prepare("UPDATE planes SET estado = 'Finalizado' WHERE id_usuario = ? AND estado = 'Activo'")
     ->execute() || null;
$upd = $conn->prepare("UPDATE planes SET estado = 'Finalizado' WHERE id_usuario = ?");
$upd->bind_param("i", $usuario_id);
$upd->execute();

// Insertar el nuevo plan activo
$stmt2 = $conn->prepare("
    INSERT INTO planes (id_usuario, nombre_plan, calorias_diarias, fecha_inicio, fecha_fin, estado)
    VALUES (?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'Activo')
");
if (!$stmt2) {
    die("Error al preparar inserción: " . $conn->error);
}
$stmt2->bind_param("isi", $usuario_id, $plan["nombre_plan"], $plan["calorias_diarias"]);

if ($stmt2->execute()) {
    header("Location: panel.php");
    exit;
} else {
    die("Error al guardar el plan: " . $stmt2->error);
}