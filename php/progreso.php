<?php
session_start();
include("conexion.php");
require __DIR__ . '/esPremium.php';

if (!isset($_SESSION["usuario"])) {
    header("Location: ../html/login.html");
    exit;
}

$id_usuario = $_SESSION["usuario"]["id"];
$es_premium = esPremium($conn, $id_usuario);
$mensaje    = "";
$tipo_msg   = "";

// Guardar registro de progreso
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $peso                = floatval($_POST["peso"] ?? 0);
    $calorias_consumidas = intval($_POST["calorias_consumidas"] ?? 0);
    $observaciones       = trim($_POST["observaciones"] ?? "");

    if ($peso <= 0) {
        $mensaje  = "El peso debe ser mayor a 0.";
        $tipo_msg = "error";
    } else {
        $chk = $conn->prepare("SELECT id_hsitoial FROM historial_progreso WHERE id_usuario = ? AND fecha = CURDATE()");
        $chk->bind_param("i", $id_usuario);
        $chk->execute();
        $existe = $chk->get_result()->fetch_assoc();

        if ($existe) {
            $stmt = $conn->prepare("UPDATE historial_progreso SET peso = ?, calorias_consumidas = ?, observaciones = ? WHERE id_usuario = ? AND fecha = CURDATE()");
            $stmt->bind_param("disi", $peso, $calorias_consumidas, $observaciones, $id_usuario);
        } else {
            $stmt = $conn->prepare("INSERT INTO historial_progreso (id_usuario, fecha, peso, calorias_consumidas, observaciones) VALUES (?, CURDATE(), ?, ?, ?)");
            $stmt->bind_param("idis", $id_usuario, $peso, $calorias_consumidas, $observaciones);
        }

        if ($stmt->execute()) {
            $mensaje  = "Progreso guardado correctamente.";
            $tipo_msg = "exito";
        } else {
            $mensaje  = "Error al guardar: " . $stmt->error;
            $tipo_msg = "error";
        }
    }
}

// Historial de progreso
$stmt = $conn->prepare("SELECT fecha, peso, calorias_consumidas, observaciones FROM historial_progreso WHERE id_usuario = ? ORDER BY fecha ASC");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$res = $stmt->get_result();

$filas    = [];
while ($fila = $res->fetch_assoc()) $filas[] = $fila;

$fechas   = array_column($filas, "fecha");
$pesos    = array_column($filas, "peso");
$calorias = array_column($filas, "calorias_consumidas");

// Estadísticas
$total_registros = count($filas);
$peso_inicial    = $total_registros > 0 ? floatval($filas[0]["peso"]) : 0;
$peso_actual     = $total_registros > 0 ? floatval($filas[$total_registros - 1]["peso"]) : 0;
$diferencia_peso = round($peso_actual - $peso_inicial, 1);
$avg_calorias    = $total_registros > 0 ? round(array_sum($calorias) / $total_registros) : 0;

// Calorías reales por día desde tabla comidas (último mes)
$plan_stmt = $conn->prepare("SELECT id_plan FROM planes WHERE id_usuario = ? AND estado = 'Activo' LIMIT 1");
$plan_stmt->bind_param("i", $id_usuario);
$plan_stmt->execute();
$plan = $plan_stmt->get_result()->fetch_assoc();

$cal_por_dia      = [];
$fechas_cal       = [];
if ($plan) {
    $q = $conn->prepare("
        SELECT fecha, SUM(calorias) as total_cal
        FROM comidas
        WHERE id_plan = ? AND fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY fecha
        ORDER BY fecha ASC
    ");
    $q->bind_param("i", $plan["id_plan"]);
    $q->execute();
    $r = $q->get_result();
    while ($row = $r->fetch_assoc()) {
        $fechas_cal[]  = $row["fecha"];
        $cal_por_dia[] = intval($row["total_cal"]);
    }
}
$active_page = 'progreso';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mi Progreso — FocusMeal</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Unbounded:wght@200;400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="../css/styles.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="panel-layout">
    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-brand">
                <img src="../img/logo.png" alt="FocusMeal Logo" style="height: 32px;">
                <span>Focus Meal</span>
            </div>
        </div>
        <ul class="sidebar-menu">
            <li class="<?= ($active_page == 'dashboard') ? 'active' : '' ?>">
                <a href="panel.php"><i class="fa-solid fa-house"></i> Panel de Control</a>
            </li>
            <li class="<?= ($active_page == 'progreso') ? 'active' : '' ?>">
                <a href="progreso.php"><i class="fa-solid fa-chart-line"></i> Mi Progreso</a>
            </li>
            <li class="<?= ($active_page == 'comida') ? 'active' : '' ?>">
                <a href="agregar_comida.php"><i class="fa-solid fa-utensils"></i> Agregar Comida</a>
            </li>
            <li class="<?= ($active_page == 'planes') ? 'active' : '' ?>">
                <a href="planes.php"><i class="fa-solid fa-calendar-days"></i> Mis Planes</a>
            </li>
            <?php if ($es_premium): ?>
                <li class="<?= ($active_page == 'plan_ia') ? 'active' : '' ?>">
                    <a href="generar_plan.php"><i class="fa-solid fa-robot"></i> Mi Plan IA</a>
                </li>
                <li class="<?= ($active_page == 'nutricionista') ? 'active' : '' ?>">
                    <a href="chat_nutricionista.php"><i class="fa-solid fa-comments"></i> Nutricionista</a>
                </li>
            <?php else: ?>
                <li class="<?= ($active_page == 'premium') ? 'active' : '' ?>">
                    <a href="planes.php" class="text-warning"><i class="fa-solid fa-star"></i> Activar Premium</a>
                </li>
            <?php endif; ?>
            <li class="<?= ($active_page == 'ajustes') ? 'active' : '' ?>">
                <a href="ajustes.php"><i class="fa-solid fa-gear"></i> Ajustes</a>
            </li>
        </ul>
        <div class="sidebar-footer">
            <a href="logout.php" class="btn-danger w-100 text-center d-block py-2 rounded-pill text-decoration-none" style="font-size: 0.85rem;"><i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión</a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 font-headings mb-1" style="font-family: var(--font-headings); font-weight: 500;">📊 Mi Progreso</h1>
                <p class="text-muted mb-0" style="font-size: 0.9rem;">Visualiza y registra tu progreso físico y alimentario</p>
            </div>
            <?php if ($es_premium): ?>
                <span class="badge bg-success py-2 px-3 rounded-pill" style="background-color: var(--green) !important;"><i class="fa-solid fa-crown me-1"></i> Premium</span>
            <?php endif; ?>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert <?= $tipo_msg === 'exito' ? 'alert-success' : 'alert-danger' ?> alert-dismissible fade show mb-4 border-0 shadow-sm" role="alert" style="border-radius: var(--radius-md);">
                <strong><?= $tipo_msg === 'exito' ? '✅' : '❌' ?></strong> <?= htmlspecialchars($mensaje) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- ESTADÍSTICAS RESUMEN -->
        <?php if ($total_registros > 0): ?>
        <div class="stats mb-4">
            <div class="stat-box shadow-sm">
                <div class="icon-wrap" style="background: rgba(99, 102, 241, 0.1); color: #6366f1;"><i class="fa-solid fa-weight-scale"></i></div>
                <div>
                    <h2><?= $peso_actual ?> kg</h2>
                    <p>Peso actual</p>
                </div>
            </div>
            <div class="stat-box shadow-sm">
                <div class="icon-wrap" style="background: <?= $diferencia_peso > 0 ? 'rgba(239, 68, 68, 0.1)' : 'rgba(22, 163, 74, 0.1)' ?>; color: <?= $diferencia_peso > 0 ? '#ef4444' : 'var(--green)' ?>;"><i class="fa-solid <?= $diferencia_peso > 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' ?>"></i></div>
                <div>
                    <h2><?= $diferencia_peso > 0 ? "+" : "" ?><?= $diferencia_peso ?> kg</h2>
                    <p>Cambio total</p>
                </div>
            </div>
            <div class="stat-box shadow-sm">
                <div class="icon-wrap" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;"><i class="fa-solid fa-fire"></i></div>
                <div>
                    <h2><?= $avg_calorias ?> kcal</h2>
                    <p>Promedio diario</p>
                </div>
            </div>
            <div class="stat-box shadow-sm">
                <div class="icon-wrap" style="background: rgba(14, 165, 233, 0.1); color: #0ea5e9;"><i class="fa-solid fa-calendar-check"></i></div>
                <div>
                    <h2><?= $total_registros ?></h2>
                    <p>Días registrados</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- FORM Y GRÁFICO ROW -->
        <div class="row g-4 mb-4">
            <!-- REGISTRAR PROGRESO HOY -->
            <div class="col-lg-4">
                <div class="card p-4 border-0 shadow-sm h-100" style="border-radius: var(--radius-lg); background: var(--surface);">
                    <h3 class="h5 mb-3" style="font-family: var(--font-headings); font-weight: 500; color: var(--navy);"><i class="fa-regular fa-pen-to-square me-2 text-success"></i>Registrar hoy</h3>
                    <form method="POST" action="progreso.php">
                        <div class="mb-3">
                            <label class="form-label font-weight-bold" style="font-size: 0.82rem; text-transform: uppercase; color: var(--text-light);">Peso actual (kg) *</label>
                            <input type="number" class="form-control" name="peso" step="0.1" min="1" max="300" value="<?= $peso_actual ?: '' ?>" placeholder="Ej: 65.5" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label font-weight-bold" style="font-size: 0.82rem; text-transform: uppercase; color: var(--text-light);">Calorías estimadas (kcal)</label>
                            <input type="number" class="form-control" name="calorias_consumidas" min="0" placeholder="Ej: 2100">
                        </div>
                        <div class="mb-3">
                            <label class="form-label font-weight-bold" style="font-size: 0.82rem; text-transform: uppercase; color: var(--text-light);">Observaciones (opcional)</label>
                            <textarea class="form-control" name="observaciones" rows="3" placeholder="¿Cómo te has sentido hoy?"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2.5 rounded-pill border-0" style="background: var(--green); font-weight: 600;">Guardar Registro</button>
                    </form>
                </div>
            </div>

            <!-- GRÁFICA DE PESO -->
            <div class="col-lg-8">
                <div class="card p-4 border-0 shadow-sm h-100" style="border-radius: var(--radius-lg); background: var(--surface);">
                    <h3 class="h5 mb-3" style="font-family: var(--font-headings); font-weight: 500; color: var(--navy);"><i class="fa-solid fa-weight-scale me-2 text-success"></i>Evolución del peso</h3>
                    <?php if ($total_registros > 0): ?>
                        <div style="position: relative; height: 300px;">
                            <canvas id="graficoPeso"></canvas>
                        </div>
                    <?php else: ?>
                        <div class="d-flex flex-column align-items-center justify-content-center h-100 py-5 text-center text-muted">
                            <i class="fa-solid fa-chart-line fa-3x mb-3 opacity-25"></i>
                            <p class="mb-0">Aún no hay registros de peso. ¡Completa tu primer registro a la izquierda!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- GRÁFICA DE CALORÍAS POR DÍA -->
        <?php if (count($cal_por_dia) > 0): ?>
        <div class="card p-4 border-0 shadow-sm mb-4" style="border-radius: var(--radius-lg); background: var(--surface);">
            <h3 class="h5 mb-3" style="font-family: var(--font-headings); font-weight: 500; color: var(--navy);"><i class="fa-solid fa-fire-flame-curved me-2 text-success"></i>Calorías consumidas por día (último mes)</h3>
            <div style="position: relative; height: 260px;">
                <canvas id="graficoCalorias"></canvas>
            </div>
        </div>
        <?php endif; ?>

        <!-- HISTORIAL TABLA -->
        <?php if ($total_registros > 0): ?>
        <div class="card p-4 border-0 shadow-sm" style="border-radius: var(--radius-lg); background: var(--surface);">
            <h3 class="h5 mb-3" style="font-family: var(--font-headings); font-weight: 500; color: var(--navy);"><i class="fa-solid fa-list-check me-2 text-success"></i>Historial completo</h3>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Peso (kg)</th>
                            <th>Calorías</th>
                            <th>Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_reverse($filas) as $fila): ?>
                            <tr>
                                <td style="font-weight: 600; color: var(--navy);"><?= date("d/m/Y", strtotime($fila["fecha"])) ?></td>
                                <td><span class="badge bg-light text-dark py-2 px-3 rounded-pill" style="font-size: 0.85rem;"><i class="fa-solid fa-weight-scale me-1 text-muted"></i> <?= $fila["peso"] ?> kg</span></td>
                                <td><?= $fila["calorias_consumidas"] ? '<span class="badge bg-success-light text-success py-2 px-3 rounded-pill" style="background-color: var(--green-light); color: var(--green-dark) !important; font-size: 0.85rem;"><i class="fa-solid fa-fire me-1"></i> ' . $fila["calorias_consumidas"] . ' kcal</span>' : '<span class="text-muted">—</span>' ?></td>
                                <td class="text-muted"><?= htmlspecialchars($fila["observaciones"] ?: "—") ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
<?php if ($total_registros > 0): ?>
new Chart(document.getElementById('graficoPeso'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_map(function($f){ return date("d/m", strtotime($f)); }, $fechas)) ?>,
        datasets: [{
            label: 'Peso (kg)',
            data: <?= json_encode($pesos) ?>,
            borderColor: '#16A34A',
            backgroundColor: 'rgba(22, 163, 74, 0.05)',
            borderWidth: 3,
            tension: 0.3,
            pointRadius: 4,
            pointBackgroundColor: '#16A34A',
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { 
            legend: { display: false } 
        },
        scales: {
            x: {
                grid: { display: false }
            },
            y: { 
                grid: { color: 'rgba(0, 0, 0, 0.04)' }
            }
        }
    }
});
<?php endif; ?>

<?php if (count($cal_por_dia) > 0): ?>
new Chart(document.getElementById('graficoCalorias'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(function($f){ return date("d/m", strtotime($f)); }, $fechas_cal)) ?>,
        datasets: [{
            label: 'Calorías',
            data: <?= json_encode($cal_por_dia) ?>,
            backgroundColor: '#16A34A',
            borderRadius: 6,
            hoverBackgroundColor: '#15803D'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { 
            legend: { display: false } 
        },
        scales: {
            x: {
                grid: { display: false }
            },
            y: { 
                grid: { color: 'rgba(0, 0, 0, 0.04)' }
            }
        }
    }
});
<?php endif; ?>
</script>

</body>
</html>