<?php
session_start();
include("conexion.php");

// Verificar sesión
if (!isset($_SESSION["usuario"])) {
    header("Location: ../login.html");
    exit;
}

$id_usuario = $_SESSION["usuario"]["id"];

// Consultar historial de progreso del usuario
$sql = "SELECT * FROM historial_progreso WHERE id_usuario = ? ORDER BY fecha DESC LIMIT 20";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$resultado = $stmt->get_result();

// Obtener peso inicial y actual
$sql_peso = "SELECT MIN(peso) as peso_inicial, MAX(peso) as peso_actual FROM historial_progreso WHERE id_usuario = ?";
$stmt_peso = $conn->prepare($sql_peso);
$stmt_peso->bind_param("i", $id_usuario);
$stmt_peso->execute();
$resultado_peso = $stmt_peso->get_result();
$datos_peso = $resultado_peso->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Progreso - FocusMeal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/index.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="panel.php">Focus Meal</a>
    </div>
</nav>

<div class="container mt-4">
    <h1>📊 Mi Progreso</h1>
    <a href="panel.php" class="btn btn-secondary mb-3">⬅ Volver al panel</a>
    <hr>

    <?php if ($datos_peso['peso_inicial'] != null): ?>
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-center bg-light">
                    <div class="card-body">
                        <h6 class="card-title">Peso Inicial</h6>
                        <h3><?= number_format($datos_peso['peso_inicial'], 2) ?> kg</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center bg-light">
                    <div class="card-body">
                        <h6 class="card-title">Peso Actual</h6>
                        <h3><?= number_format($datos_peso['peso_actual'], 2) ?> kg</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center bg-light">
                    <div class="card-body">
                        <h6 class="card-title">Diferencia</h6>
                        <h3><?= number_format($datos_peso['peso_actual'] - $datos_peso['peso_inicial'], 2) ?> kg</h3>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <h3>📈 Historial</h3>
    
    <?php if ($resultado->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>Fecha</th>
                        <th>Peso (kg)</th>
                        <th>Calorías Consumidas</th>
                        <th>Observaciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($fila = $resultado->fetch_assoc()): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($fila['fecha'])) ?></td>
                            <td><?= number_format($fila['peso'], 2) ?> kg</td>
                            <td><?= $fila['calorias_consumidas'] ?? '—' ?> kcal</td>
                            <td><?= htmlspecialchars($fila['observaciones'] ?? '') ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            ℹ️ Aún no tienes registros de progreso. ¡Comienza a registrar hoy!
        </div>
    <?php endif; ?>

    <div class="mt-4">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalProgreso">
            ➕ Registrar nuevo progreso
        </button>
    </div>
</div>

<!-- Modal para registrar progreso -->
<div class="modal fade" id="modalProgreso" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Registrar Progreso</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="guardar_progreso.php" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Peso (kg)</label>
                        <input type="number" step="0.1" class="form-control" name="peso" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Calorías consumidas</label>
                        <input type="number" class="form-control" name="calorias_consumidas">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Observaciones</label>
                        <textarea class="form-control" name="observaciones" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

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
