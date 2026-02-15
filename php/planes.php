<?php
session_start();
include("conexion.php");

if (!isset($_SESSION["usuario"])) {
    header("Location: ../login.html");
    exit;
}

$id_usuario = $_SESSION["usuario"]["id"];

$sql = "SELECT * FROM planes WHERE id_usuario = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$resultado = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Planes - FocusMeal</title>

    <!-- Fuente -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

    <!-- MISMO CSS DEL PANEL -->
    <link rel="stylesheet" href="../css/panel.css">
</head>
<body>

    <!-- HEADER -->
    <div class="panel-header">
        <h1>Focus Meal 🍽️</h1>
        <a href="panel.php" class="btn-primary">Volver al panel</a>
    </div>

    <div class="panel-container">

        <div class="card">
            <h2>🍽 Mis planes de alimentación</h2>
        </div>

        <?php if ($resultado->num_rows > 0): ?>
            <div class="card">
                <table>
                    <thead>
                        <tr>
                            <th>Plan</th>
                            <th>Calorías diarias</th>
                            <th>Inicio</th>
                            <th>Fin</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($plan = $resultado->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($plan["nombre_plan"]) ?></td>
                                <td><?= htmlspecialchars($plan["calorias_diarias"]) ?> kcal</td>
                                <td><?= htmlspecialchars($plan["fecha_inicio"]) ?></td>
                                <td><?= htmlspecialchars($plan["fecha_fin"]) ?></td>
                                <td><?= htmlspecialchars($plan["estado"]) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="card" style="text-align:center;">
                <h3>❌ Aún no tienes planes asignados</h3>
                <p>Cuando tengas un plan activo aparecerá aquí.</p>
            </div>
        <?php endif; ?>

    </div>

</body>
</html>