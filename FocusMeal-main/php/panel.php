<?php
session_start();

// Si no hay sesión activa, no puede entrar al panel
if (!isset($_SESSION["usuario"])) {
    header("Location: ../login.html");
    exit;
}

$usuario = $_SESSION["usuario"];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel - FocusMeal</title>
    <link rel="stylesheet" href="../css/index.css">
</head>
<body>

<h1>Bienvenido, <?php echo htmlspecialchars($usuario["nombre"]);?> 👋</h1>

<p>Correo: <?php echo htmlspecialchars($usuario["correo"]); ?></p>

<hr>

<h3>¿Que deseas hacer?</h3>
<ul>
    <li><a href="planes.php">🍽 Ver planes de alimentación</a></li>
    <li><a href="progreso.php">📊 Ver progreso</a></li>
    <li><a href="perfil.php">⚙️ Editar perfil</a></li>
    <li><a href="logout.php">🚪 Cerrar sesión</a></li>
</ul>

</body>
</html>
