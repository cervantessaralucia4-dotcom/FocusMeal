<?php
session_start();
include("conexion.php");

// proteger página
if (!isset($_SESSION["usuario"])) {
    header("Location: ../login.html");
    exit;
}

$id_usuario = $_SESSION["usuario"]["id"];

// SQL correcto
$sql = "
SELECT 
    fecha,
    peso,
    calorias_consumidas
FROM historial_progreso
WHERE id_usuario = ?
ORDER BY fecha ASC
";

$stmt = $conn->prepare($sql);

// 🔴 PROTECCIÓN EXTRA (para que no vuelva a pasar)
if (!$stmt) {
    die("Error en la consulta SQL: " . $conn->error);
}

$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$resultado = $stmt->get_result();

/* ARRAYS PARA EL GRAFICO */
$fechas = [];
$pesos =[];
$calorias =[];

while ($fila = $resultado->fetch_assoc()) {
    $fechas[] = $fila["fecha"];
    $pesos[] = $fila["peso"];
    $calorias[] = $fila["calorias_consumidas"];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi Progreso</title>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<h1>📊 Mi progreso</h1>

<?php if ($resultado->num_rows > 0): ?>
    <table border="1" cellpadding="8">
        <tr>
            <th>Fecha</th>
            <th>Peso (kg)</th>
            <th>Calorías consumidas</th>
        </tr>

        <?php while ($fila = $resultado->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($fila["fecha"]) ?></td>
                <td><?= htmlspecialchars($fila["peso"]) ?></td>
                <td><?= htmlspecialchars($fila["calorias_consumidas"]) ?></td>
            </tr>
        <?php endwhile; ?>

    </table>

<h2>📈 Progreso gráfico</h2>

<canvas id="graficoProgreso" width="400" height="200"></canvas>

<?php else: ?>
    <p>⚠️ Aún no hay registros de progreso.</p>
<?php endif; ?>

<br>
<a href="panel.php">⬅ Volver al panel</a>

<script>
const ctx = document.getElementById('graficoProgreso').getContext('2d');

new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($fechas) ?>,
        datasets: [
            {
                label: 'Peso (kg)',
                data: <?= json_encode($pesos) ?>,
                borderWidth: 2,
                tension: 0.3
            },
            {
                label: 'Calorías consumidas',
                data: <?= json_encode($calorias) ?>,
                borderWidth: 2,
                tension: 0.3
            }
        ]
    },
    options: {
        responsive: true
    }
});
</script>

</body>
</html>
