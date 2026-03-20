<?php
session_start();
require "conexion.php";

if (!isset($_SESSION["usuario"])) {
    header("Location: ../html/login.html");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Planes - FocusMeal</title>
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
    <h1>🥗 Planes disponibles</h1>

    <div class="planes-conatiner">
    <?php
    $sql = "SELECT * FROM planes_disponibles ORDER BY calorias_diarias ASC";
    $resultado = $conn->query($sql);

    if (!$resultado) {
        echo "<p>Error en la consulta: " . $conn->error . "</p>";
    } elseif ($resultado->num_rows === 0) {
        echo "<p>No hay planes disponibles aún.</p>";
    } else {
        while ($plan = $resultado->fetch_assoc()) {
            ?>
            <div class="plan-card">
                <h3><?= htmlspecialchars($plan['nombre_plan']) ?></h3>
                <p><?= htmlspecialchars($plan['descripcion'] ?? '') ?></p>
                <p><strong><?= htmlspecialchars($plan['calorias_diarias']) ?> kcal/día</strong></p>
                <form action="guardar_plan.php" method="POST">
                    <input type="hidden" name="id_plan" value="<?= $plan['id_plan'] ?>">
                    <button type="submit" class="btn-plan">Seleccionar</button>
                </form>
            </div>
            <?php
        }
    }
    ?>
    </div>

    <br>
    <a href="panel.php">← Volver al panel</a>
</div>
</body>
</html>