<?php
session_start();

if (!isset($_SESSION["usuario"])) {
    header("Location: ../login.html");
    exit();
}

$usuario = $_SESSION["usuario"];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel - FocusMeal</title>

    <!-- Fuente profesional -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

    <!-- CSS del panel -->
    <link rel="stylesheet" href="../css/panel.css">
</head>
<body>

    <!-- HEADER -->
    <div class="panel-header">
        <h1>Focus Meal 🍽️</h1>
        <a href="logout.php" class="btn-primary">Cerrar sesión</a>
    </div>

    <!-- CONTENEDOR PRINCIPAL -->
    <div class="panel-container">

        <!-- BIENVENIDA -->
        <div class="card">
            <h2>Bienvenida, <?php echo htmlspecialchars($usuario["nombre"]); ?> 👋</h2>
            <p><strong>Correo:</strong> <?php echo htmlspecialchars($usuario["correo"]); ?></p>
        </div>

       <!-- ESTADÍSTICAS -->
        <?php
// Aquí luego conectaremos la base de datos
$caloriasHoy = 0; // temporal hasta conectar BD
$metaDiaria = 2200;

if ($caloriasHoy > 0) {
    $restantes = $metaDiaria - $caloriasHoy;
?>

<div class="stats">
    <div class="stat-box">
        <h2><?php echo $caloriasHoy; ?> kcal</h2>
        <p>Calorías consumidas hoy</p>
    </div>

    <div class="stat-box">
        <h2><?php echo $metaDiaria; ?> kcal</h2>
        <p>Meta diaria</p>
    </div>

    <div class="stat-box">
        <h2><?php echo $restantes; ?> kcal</h2>
        <p>Calorías restantes</p>
    </div>
</div>

<?php
} else {
?>

<div class="card" style="text-align:center;">
    <h3>Aún no has registrado comidas hoy 🍽️</h3>
    <p>Comienza agregando tu primera comida para ver tu progreso.</p>
    <br>
    <a href="planes.php" class="btn-primary">Agregar comida</a>
</div>

<?php } ?>
