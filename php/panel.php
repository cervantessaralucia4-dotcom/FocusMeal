<?php
session_start();
require __DIR__ . '/conexion.php';

if (!isset($_SESSION["usuario"])) {
    header("Location: ../html/login.html");
    exit();
}

$usuario_id = $_SESSION["usuario"]["id"];

// Datos del usuario
$stmt = $conn->prepare("SELECT nombre, objetivo, tipo_dieta, peso_actual FROM usuarios WHERE id_usuario = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();

// Plan activo
$stmt2 = $conn->prepare("SELECT id_plan, nombre_plan, calorias_diarias FROM planes WHERE id_usuario = ? AND estado = 'Activo' LIMIT 1");
$stmt2->bind_param("i", $usuario_id);
$stmt2->execute();
$plan_activo = $stmt2->get_result()->fetch_assoc();

// Calorías consumidas hoy desde tabla comidas
$cal_hoy = 0;
if ($plan_activo) {
    $stmt3 = $conn->prepare("SELECT SUM(calorias) as total FROM comidas WHERE id_plan = ? AND fecha = CURDATE()");
    $stmt3->bind_param("i", $plan_activo["id_plan"]);
    $stmt3->execute();
    $cal_hoy = intval($stmt3->get_result()->fetch_assoc()["total"]);
}

// Último peso registrado en historial
$stmt4 = $conn->prepare("SELECT peso, fecha FROM historial_progreso WHERE id_usuario = ? ORDER BY fecha DESC LIMIT 1");
$stmt4->bind_param("i", $usuario_id);
$stmt4->execute();
$ultimo_peso = $stmt4->get_result()->fetch_assoc();

// Comidas registradas hoy
$num_comidas_hoy = 0;
if ($plan_activo) {
    $stmt5 = $conn->prepare("SELECT COUNT(*) as total FROM comidas WHERE id_plan = ? AND fecha = CURDATE()");
    $stmt5->bind_param("i", $plan_activo["id_plan"]);
    $stmt5->execute();
    $num_comidas_hoy = intval($stmt5->get_result()->fetch_assoc()["total"]);
}

// Porcentaje de calorías del día
$meta_cal = $plan_activo["calorias_diarias"] ?? 0;
$pct_cal  = $meta_cal > 0 ? min(100, round($cal_hoy / $meta_cal * 100)) : 0;

$nombre     = $usuario["nombre"];
$objetivo   = $usuario["objetivo"];
$tipo_dieta = $usuario["tipo_dieta"];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel — FocusMeal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Unbounded:wght@200;400;600&display=swap" rel="stylesheet">
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

    <!-- BIENVENIDA + PLAN -->
    <div class="card">
        <h2>👋 Bienvenido, <?= htmlspecialchars($nombre) ?></h2>
        <p><?= date("l, d \d\e F \d\e Y") ?></p>

        <?php if ($objetivo): ?>
            <p><strong>Objetivo:</strong> <?= htmlspecialchars($objetivo) ?>
            &nbsp;|&nbsp;
            <strong>Dieta:</strong> <?= htmlspecialchars($tipo_dieta ?: "General") ?></p>
        <?php endif; ?>

        <?php if ($plan_activo): ?>
            <p><strong>Plan activo:</strong> <?= htmlspecialchars($plan_activo["nombre_plan"]) ?>
            — <?= $meta_cal ?> kcal/día</p>
        <?php else: ?>
            <p>No tienes un plan activo. <a href="planes.php">Elegir un plan</a></p>
        <?php endif; ?>
    </div>

    <!-- RESUMEN DEL DÍA -->
    <div class="stats">
        <div class="stat-box">
            <h2><?= $cal_hoy ?> kcal</h2>
            <p>Consumidas hoy<?= $meta_cal ? " de $meta_cal" : "" ?></p>
        </div>
        <div class="stat-box">
            <h2><?= $pct_cal ?>%</h2>
            <p>De tu meta calórica</p>
        </div>
        <div class="stat-box">
            <h2><?= $num_comidas_hoy ?></h2>
            <p>Comidas registradas hoy</p>
        </div>
        <div class="stat-box">
            <h2><?= $ultimo_peso ? $ultimo_peso["peso"] . " kg" : "—" ?></h2>
            <p>Último peso registrado<?= $ultimo_peso ? " · " . date("d/m", strtotime($ultimo_peso["fecha"])) : "" ?></p>
        </div>
    </div>

    <!-- ACCESOS RÁPIDOS -->
    <div class="dashboard-grid">
        <a href="progreso.php" class="dashboard-card">
            <h3>📊 Mi Progreso</h3>
            <p>Evolución de peso y calorías</p>
        </a>
        <a href="agregar_comida.php" class="dashboard-card">
            <h3>🍽 Agregar Comida</h3>
            <p>Registrar alimentos del día</p>
        </a>
        <a href="planes.php" class="dashboard-card">
            <h3>🥗 Mis Planes</h3>
            <p>Ver o cambiar tu plan activo</p>
        </a>
        <a href="ajustes.php" class="dashboard-card">
            <h3>⚙ Ajustes</h3>
            <p>Editar tu perfil</p>
        </a>
    </div>

</div>
</body>
</html>