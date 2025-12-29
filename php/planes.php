<?php
session_start();
include("conexion.php");

// Verificar sesión
if (!isset($_SESSION["usuario"])) {
    header("Location: ../login.html");
    exit;
}

$id_usuario = $_SESSION["usuario"]["id"];

// Consultar planes del usuario
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
    <link rel="stylesheet" href="../css/index.css">
</head>
<body>

<h1>🍽 Mis planes de alimentación</h1>

<a href="panel.php">⬅ Volver al panel</a>
<hr>

<?php if ($resultado->num_rows > 0): ?>
    <table border="1" cellpadding="10">
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
<?php else: ?>
    <p>❌ Aún no tienes planes asignados.</p>
<?php endif; ?>

</body>
</html>
