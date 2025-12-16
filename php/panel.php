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
    <style>
        body {
            font-family: Arial;
            background: #f2f2f2;
            padding: 40px;
        }
        .panel {
            background: white;
            padding: 25px;
            border-radius: 10px;
            width: 400px;
            margin: auto;
            text-align: center;
            box-shadow: 0 0 10px #ddd;
        }
        a {
            display: block;
            margin: 10px;
            padding: 10px;
            background: #4CAF50;
            color: white;
            border-radius: 6px;
            text-decoration: none;
        }
    </style>
</head>
<body>

<div class="panel">
    <h2>Bienvenido, <?= $usuario["nombre"] ?> 👋</h2>

    <a href="#">Ver plan de comidas</a>
    <a href="#">Editar perfil</a>
    <a href="#">Registrar progreso</a>
    <a href="logout.php">Cerrar sesión</a>
</div>

</body>
</html>
