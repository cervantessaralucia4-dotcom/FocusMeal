<?php
session_start();
include("conexion.php");

if (!isset($_SESSION["usuario"])) {
    header("Location: ../html/login.html");
    exit;
}

$id_usuario = $_SESSION["usuario"]["id"];
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mi Progreso — FocusMeal</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Unbounded:wght@200;400;600&display=swap" rel="stylesheet">
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

  <?php if ($mensaje): ?>
    <p><strong><?= $tipo_msg === "exito" ? "✅" : "❌" ?> <?= htmlspecialchars($mensaje) ?></strong></p>
  <?php endif; ?>

  <!-- ESTADÍSTICAS RESUMEN -->
  <?php if ($total_registros > 0): ?>
  <div class="stats">
    <div class="stat-box">
      <h2><?= $peso_actual ?> kg</h2>
      <p>Peso actual</p>
    </div>
    <div class="stat-box">
      <h2><?= $diferencia_peso > 0 ? "+" : "" ?><?= $diferencia_peso ?> kg</h2>
      <p>Cambio total desde el inicio</p>
    </div>
    <div class="stat-box">
      <h2><?= $avg_calorias ?> kcal</h2>
      <p>Promedio diario registrado</p>
    </div>
    <div class="stat-box">
      <h2><?= $total_registros ?></h2>
      <p>Días registrados</p>
    </div>
  </div>
  <?php endif; ?>

  <div class="stats">

    <!-- REGISTRAR PROGRESO HOY -->
    <div class="card" style="flex:1; min-width:260px">
      <h3>📝 Registrar de hoy</h3>

      <form method="POST" action="progreso.php">
        <label>Peso actual (kg) *</label>
        <input type="number" name="peso" step="0.1" min="1" max="300"
               value="<?= $peso_actual ?: '' ?>" placeholder="Ej: 65.5" required>

        <label>Calorías consumidas (kcal)</label>
        <input type="number" name="calorias_consumidas" min="0"
               placeholder="0">

        <label>Observaciones (opcional)</label>
        <textarea name="observaciones" rows="2" placeholder="Cómo te sentiste, qué comiste..."></textarea>

        <br>
        <button type="submit" class="btn-primary">Guardar</button>
      </form>
    </div>

    <!-- GRÁFICA DE PESO -->
    <div class="card" style="flex:2; min-width:300px">
      <h3>⚖️ Evolución del peso</h3>
      <?php if ($total_registros > 0): ?>
        <canvas id="graficoPeso" height="160"></canvas>
      <?php else: ?>
        <p>Aún no hay registros. Guarda tu primer peso arriba.</p>
      <?php endif; ?>
    </div>

  </div>

  <!-- GRÁFICA DE CALORÍAS POR DÍA -->
  <?php if (count($cal_por_dia) > 0): ?>
  <div class="card">
    <h3>🔥 Calorías consumidas por día (último mes)</h3>
    <canvas id="graficoCalorias" height="120"></canvas>
  </div>
  <?php endif; ?>

  <!-- HISTORIAL TABLA -->
  <?php if ($total_registros > 0): ?>
  <div class="card">
    <h3>📋 Historial completo</h3>
    <table>
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
            <td><?= date("d/m/Y", strtotime($fila["fecha"])) ?></td>
            <td><?= $fila["peso"] ?></td>
            <td><?= $fila["calorias_consumidas"] ?: "—" ?></td>
            <td><?= htmlspecialchars($fila["observaciones"] ?: "—") ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <br>
  <a href="panel.php">← Volver al panel</a>

</div>

<script>
<?php if ($total_registros > 0): ?>
new Chart(document.getElementById('graficoPeso'), {
    type: 'line',
    data: {
        labels: <?= json_encode($fechas) ?>,
        datasets: [{
            label: 'Peso (kg)',
            data: <?= json_encode($pesos) ?>,
            borderColor: '#1DB954',
            backgroundColor: 'rgba(29,185,84,0.08)',
            borderWidth: 2,
            tension: 0.3,
            pointRadius: 4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { title: { display: true, text: 'kg' } }
        }
    }
});
<?php endif; ?>

<?php if (count($cal_por_dia) > 0): ?>
new Chart(document.getElementById('graficoCalorias'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($fechas_cal) ?>,
        datasets: [{
            label: 'Calorías',
            data: <?= json_encode($cal_por_dia) ?>,
            backgroundColor: 'rgba(10,31,68,0.75)',
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { title: { display: true, text: 'kcal' } }
        }
    }
});
<?php endif; ?>
</script>

</body>
</html>