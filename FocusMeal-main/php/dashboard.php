<?php
session_start();
include("conexion.php");

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel - FocusMeal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/index.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">Focus Meal</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="logout.php">Cerrar sesión</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <h1>Bienvenida, <?php echo htmlspecialchars($usuario["nombre"]);?> 👋</h1>
    <p class="text-muted">Correo: <?php echo htmlspecialchars($usuario["correo"]); ?></p>
    <hr>

    <h3>¿Qué deseas hacer?</h3>
    <div class="row mt-4">
        <div class="col-md-6 col-lg-4 mb-3">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <h5 class="card-title">🍽 Planes</h5>
                    <p>Ver planes de alimentación</p>
                    <a href="planes.php" class="btn btn-primary">Ir</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4 mb-3">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <h5 class="card-title">📊 Progreso</h5>
                    <p>Registra y ve tu progreso</p>
                    <a href="progreso.php" class="btn btn-primary">Ir</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-lg-4 mb-3">
            <div class="card shadow-sm">
                <div class="card-body text-center">
                    <h5 class="card-title">⚙️ Perfil</h5>
                    <p>Edita tu información</p>
                    <a href="perfil.php" class="btn btn-primary">Ir</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

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