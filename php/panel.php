<?php
session_start();
require __DIR__ . '/conexion.php';

if (!isset($_SESSION["usuario"])) {
    header("Location: ../html/login.html");
    exit();
}

$usuario_id = $_SESSION["usuario"]["id"];

$sql = "SELECT nombre, objetivo, tipo_dieta, peso_actual FROM usuarios WHERE id_usuario = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Error en la consulta: " . $conn->error);
}
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();

$nombre     = $usuario["nombre"];
$objetivo   = $usuario["objetivo"];
$tipo_dieta = $usuario["tipo_dieta"];

$sql2 = "SELECT nombre_plan, calorias_diarias FROM planes WHERE id_usuario = ? AND estado = 'Activo' LIMIT 1";
$stmt2 = $conn->prepare($sql2);
$stmt2->bind_param("i", $usuario_id);
$stmt2->execute();
$plan_activo = $stmt2->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel - FocusMeal</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/dashboard.css">
</head>
<body>

<div class="panel-header">
    <div class="logo-container">
        <img src="../img/logo.png" alt="FocusMeal Logo">
        <span>Focus Meal</span>
    </div>
    <a href="logout.php" class="btn-danger">Cerrar sesión</a>
</div>

<div class="panel-container">

    <div class="card bienvenida">
        <h2>👋 Bienvenido, <?= htmlspecialchars($nombre) ?></h2>
        <p>Gestiona tu alimentación y progreso desde aquí.</p>

        <?php if ($plan_activo): ?>
            <div class="info-plan">
                <p><strong>Plan activo:</strong> <?= htmlspecialchars($plan_activo["nombre_plan"]) ?></p>
                <p><strong>Calorías objetivo:</strong> <?= htmlspecialchars($plan_activo["calorias_diarias"]) ?> kcal/día</p>
            </div>
        <?php else: ?>
            <div class="info-plan">
                <p>No tienes un plan activo aún.</p>
                <a href="planes.php" class="btn-primary">Elegir un plan</a>
            </div>
        <?php endif; ?>

        <?php if ($objetivo): ?>
            <p style="margin-top:10px">
                <strong>Objetivo:</strong> <?= htmlspecialchars($objetivo) ?> &nbsp;|&nbsp;
                <strong>Dieta:</strong> <?= htmlspecialchars($tipo_dieta ?: 'General') ?>
            </p>
        <?php endif; ?>
    </div>

    <div class="dashboard-grid">
        <a href="progreso.php" class="dashboard-card">
            <h3>📊 Mi Progreso</h3>
            <p>Ver evolución de peso y calorías</p>
        </a>
        <a href="agregar_comida.php" class="dashboard-card">
            <h3>🍽 Agregar Comida</h3>
            <p>Registrar alimentos consumidos</p>
        </a>
        <a href="planes.php" class="dashboard-card">
            <h3>🥗 Mis Planes</h3>
            <p>Ver plan de alimentación activo</p>
        </a>
        <a href="ajustes.php" class="dashboard-card">
            <h3>⚙ Ajustes</h3>
            <p>Editar tu perfil</p>
        </a>
    </div>

</div>
</body>
</html>
