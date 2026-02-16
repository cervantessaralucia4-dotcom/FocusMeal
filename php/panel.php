<?php
session_start();

if (!isset($_SESSION["usuario"])) {
    header("Location: ../login.html");
    exit;
}

$nombre = $_SESSION["usuario"]["nombre"];
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

        <a href="rutinas.php" class="dashboard-card">
            <h3>🏋️ Rutinas</h3>
            <p>Ver o asignar rutinas de ejercicio</p>
        </a>

        <a href="ajustes.php" class="dashboard-card">
            <h3> ⚙ Ajustes</h3>
            <p>Ajustes del perfil</p>
        </a>

    </div>

</div>

</body>
</html>