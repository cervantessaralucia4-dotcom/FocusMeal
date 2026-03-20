<?php
session_start();
include("conexion.php");

if (!isset($_SESSION["usuario"])) {
    header("Location: ../html/login.html");
    exit;
}

$id_usuario = $_SESSION["usuario"]["id"];

$sql = "
SELECT fecha, peso, calorias_consumidas
FROM historial_progreso
WHERE id_usuario = ?
ORDER BY fecha ASC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("Error en la consulta SQL: " . $conn->error);
}

$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$resultado = $stmt->get_result();

// Guardamos todas las filas primero — así no agotamos el cursor
$filas = [];
while ($fila = $resultado->fetch_assoc()) {
    $filas[] = $fila;
}

$fechas   = array_column($filas, "fecha");
$pesos    = array_column($filas, "peso");
$calorias = array_column($filas, "calorias_consumidas");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi Progreso - FocusMeal</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

    <h1>📊 Mi Progreso</h1>

    <?php if (count($filas) > 0): ?>

        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Peso (kg)</th>
                        <th>Calorías consumidas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filas as $fila): ?>
                    <tr>
                        <td><?= htmlspecialchars($fila["fecha"]) ?></td>
                        <td><?= htmlspecialchars($fila["peso"]) ?></td>
                        <td><?= htmlspecialchars($fila["calorias_consumidas"]) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2>📈 Evolución gráfica</h2>
            <canvas id="graficoProgreso" width="400" height="180"></canvas>
        </div>

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
                        borderColor: '#1DB954',
                        backgroundColor: 'rgba(29,185,84,0.1)',
                        borderWidth: 2,
                        tension: 0.3,
                        yAxisID: 'yPeso'
                    },
                    {
                        label: 'Calorías consumidas',
                        data: <?= json_encode($calorias) ?>,
                        borderColor: '#0a1f44',
                        backgroundColor: 'rgba(10,31,68,0.08)',
                        borderWidth: 2,
                        tension: 0.3,
                        yAxisID: 'yCal'
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    yPeso: { type: 'linear', position: 'left',  title: { display: true, text: 'kg' } },
                    yCal:  { type: 'linear', position: 'right', title: { display: true, text: 'kcal' }, grid: { drawOnChartArea: false } }
                }
            }
        });
        </script>

    <?php else: ?>
        <div class="card">
            <p>⚠️ Aún no hay registros de progreso.</p>
            <a href="guardar_progreso.php" class="btn-primary">Registrar progreso de hoy</a>
        </div>
    <?php endif; ?>

    <br>
    <a href="panel.php">← Volver al panel</a>

</div>
</body>
</html>