<?php 
session_start();

//SI NO HAY SESION, REDIRIGE AL LOGIN
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.html");
    exit();
}

$nombre = $_SESSION['usuario_nombre'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Principal - Focus Meal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/index.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top shadow-sm">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="#">
            <img src="img/logo.png" alt="Logo" class="logo-navbar">
            <span class="ms-2 fw-bold">Focus Meal</span>
        </a>
        <div class="ms-auto">
            <a href="php/logout.php" class="btn btn-light btn-sm">Cerrar sesión</a>
        </div>
    </div>
</nav>

<main class="container mt-5 pt-5">

    <h1 class="text-center mb-4">Hola, <?php echo $nombre; ?> 👋</h1>
    <p class="text-center text-muted">Bienvenido a tu panel principal</p>

    <div class="row g-4 mt-4">

        <!-- Mi plan -->
        <div class="col-md-4">
            <div class="card shadow-sm text-center p-3">
                <h4>📋 Mi Plan Alimenticio</h4>
                <p class="text-muted">Revisa tu plan personalizado</p>
                <a href="plan.html" class="btn btn-primary">Ver plan</a>
            </div>
        </div>

        <!-- Perfil -->
        <div class="col-md-4">
            <div class="card shadow-sm text-center p-3">
                <h4>👤 Mi Perfil</h4>
                <p class="text-muted">Ver y editar tu información</p>
                <a href="perfil.php" class="btn btn-primary">Abrir perfil</a>
            </div>
        </div>

        <!-- Recomendaciones -->
        <div class="col-md-4">
            <div class="card shadow-sm text-center p-3">
                <h4>🍎 Recomendaciones</h4>
                <p class="text-muted">Comidas y hábitos sugeridos</p>
                <a href="#" class="btn btn-primary">Ver recomendaciones</a>
            </div>
        </div>

    </div>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</nav>
    
</body>
</html>