<?php
session_start();
require "conexion.php";

if (!isset($_SESSION["usuario"])) {
    header("Location: ../html/login.html");
    exit;
}

$usuario_id = $_SESSION["usuario"]["id"];
$mensaje    = "";
$tipo_msg   = "";

// Buscar plan activo
$stmt = $conn->prepare("SELECT id_plan, nombre_plan, calorias_diarias FROM planes WHERE id_usuario = ? AND estado = 'Activo' LIMIT 1");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$plan_activo = $stmt->get_result()->fetch_assoc();

// Guardar comida
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id_plan       = intval($_POST["id_plan"] ?? 0);
    $tipo_comida   = $_POST["tipo_comida"] ?? "";
    $nombre_comida = trim($_POST["nombre_comida"] ?? "");
    $descripcion   = trim($_POST["descripcion"] ?? "");
    $calorias      = intval($_POST["calorias"] ?? 0);
    $proteinas     = floatval($_POST["proteinas"] ?? 0);
    $carbohidratos = floatval($_POST["carbohidratos"] ?? 0);
    $grasas        = floatval($_POST["grasas"] ?? 0);

    if (!$nombre_comida || !$tipo_comida || $id_plan <= 0) {
        $mensaje  = "Por favor completa los campos obligatorios.";
        $tipo_msg = "error";
    } else {
        $ins = $conn->prepare("
            INSERT INTO comidas (id_plan, fecha, tipo_comida, nombre_comida, descripcion, calorias, proteinas, carbohidratos, grasas)
            VALUES (?, CURDATE(), ?, ?, ?, ?, ?, ?, ?)
        ");
        $ins->bind_param("issssiddd", $id_plan, $tipo_comida, $nombre_comida, $descripcion, $calorias, $proteinas, $carbohidratos, $grasas);

        if ($ins->execute()) {
            $mensaje  = "Comida registrada correctamente.";
            $tipo_msg = "exito";
        } else {
            $mensaje  = "Error al guardar: " . $ins->error;
            $tipo_msg = "error";
        }
    }
}

// Comidas de hoy
$comidas_hoy = [];
if ($plan_activo) {
    $q = $conn->prepare("SELECT * FROM comidas WHERE id_plan = ? AND fecha = CURDATE() ORDER BY id_comidas DESC");
    $q->bind_param("i", $plan_activo["id_plan"]);
    $q->execute();
    $res = $q->get_result();
    while ($row = $res->fetch_assoc()) $comidas_hoy[] = $row;
}

// Totales
$total_cal  = array_sum(array_column($comidas_hoy, "calorias"));
$total_prot = array_sum(array_column($comidas_hoy, "proteinas"));
$total_carb = array_sum(array_column($comidas_hoy, "carbohidratos"));
$total_gras = array_sum(array_column($comidas_hoy, "grasas"));
$meta_cal   = $plan_activo["calorias_diarias"] ?? 2000;
$pct_cal    = $meta_cal > 0 ? min(100, round($total_cal / $meta_cal * 100)) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Agregar Comida — FocusMeal</title>
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

  <h1>🍽 Agregar comida</h1>

  <?php if (!$plan_activo): ?>
    <p>⚠️ No tienes un plan activo. <a href="planes.php">Elige un plan</a> primero.</p>
  <?php endif; ?>

  <?php if ($mensaje): ?>
    <p><strong><?= $tipo_msg === "exito" ? "✅" : "❌" ?> <?= htmlspecialchars($mensaje) ?></strong></p>
  <?php endif; ?>

  <div class="stats">

    <!-- COLUMNA IZQUIERDA: foto + formulario -->
    <div class="card" style="flex:1; min-width:300px">

      <!-- FORMULARIO -->
      <h3>✏️ Nueva comida</h3>

      <?php if ($plan_activo): ?>
        <form method="POST" action="agregar_comida.php" id="form-comida">
          <input type="hidden" name="id_plan" value="<?= $plan_activo['id_plan'] ?>">

          <label>Tipo de comida *</label>
          <select name="tipo_comida" id="campo-tipo" required>
            <option value="">Seleccionar...</option>
            <option value="Desayuno">Desayuno</option>
            <option value="Almuerzo">Almuerzo</option>
            <option value="Cena">Cena</option>
            <option value="Snack">Snack</option>
          </select>

          <label>Nombre del alimento *</label>
          <input type="text" name="nombre_comida" id="campo-nombre" placeholder="Ej: Avena con frutas" required>

          <label>Descripción (opcional)</label>
          <textarea name="descripcion" id="campo-descripcion" rows="2" placeholder="Ingredientes, preparación..."></textarea>

          <label>Calorías (kcal)</label>
          <input type="number" name="calorias" id="campo-calorias" min="0" placeholder="0">

          <label>Proteínas (g)</label>
          <input type="number" name="proteinas" id="campo-proteinas" step="0.1" min="0" placeholder="0">

          <label>Carbohidratos (g)</label>
          <input type="number" name="carbohidratos" id="campo-carbohidratos" step="0.1" min="0" placeholder="0">

          <label>Grasas (g)</label>
          <input type="number" name="grasas" id="campo-grasas" step="0.1" min="0" placeholder="0">

          <br>
          <button type="submit" class="btn-primary">Guardar comida</button>
        </form>
      <?php else: ?>
        <p><a href="planes.php">→ Elegir un plan</a></p>
      <?php endif; ?>
    </div>

    <!-- COLUMNA DERECHA: resumen -->
    <div class="card" style="flex:1; min-width:280px">
      <h3>📊 Resumen del día</h3>

      <div class="stat-box">
        <h2><?= $total_cal ?> kcal</h2>
        <p>de <?= $meta_cal ?> kcal meta — <?= $pct_cal ?>%</p>
      </div>

      <br>
      <p><strong>Proteínas:</strong> <?= round($total_prot, 1) ?>g</p>
      <p><strong>Carbohidratos:</strong> <?= round($total_carb, 1) ?>g</p>
      <p><strong>Grasas:</strong> <?= round($total_gras, 1) ?>g</p>

      <br>
      <h3>Comidas registradas hoy</h3>

      <?php if (count($comidas_hoy) === 0): ?>
        <p>Aún no has registrado ninguna comida hoy.</p>
      <?php else: ?>
        <table>
          <tr>
            <th>Tipo</th>
            <th>Nombre</th>
            <th>kcal</th>
          </tr>
          <?php foreach ($comidas_hoy as $c): ?>
            <tr>
              <td><?= htmlspecialchars($c['tipo_comida']) ?></td>
              <td><?= htmlspecialchars($c['nombre_comida']) ?></td>
              <td><?= $c['calorias'] ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>
    </div>

  </div>

  <br>
  <a href="panel.php">← Volver al panel</a>

</div>

<script>
const inputFoto    = document.getElementById("input-foto");
const previewFoto  = document.getElementById("preview-foto");
const btnAnalizar  = document.getElementById("btn-analizar");
const estadoTxt    = document.getElementById("estado-analisis");

// Mostrar preview al seleccionar foto
inputFoto.addEventListener("change", function () {
    const archivo = this.files[0];
    if (!archivo) return;

    const reader = new FileReader();
    reader.onload = function (e) {
        previewFoto.src = e.target.result;
        previewFoto.style.display = "block";
        btnAnalizar.style.display = "inline-block";
        estadoTxt.textContent = "";
    };
    reader.readAsDataURL(archivo);
});

// Enviar foto a analizar_foto.php y rellenar el formulario
btnAnalizar.addEventListener("click", function () {
    const archivo = inputFoto.files[0];
    if (!archivo) return;

    estadoTxt.textContent = "⏳ Analizando imagen...";
    btnAnalizar.disabled = true;

    const formData = new FormData();
    formData.append("foto", archivo);

    fetch("analizar_foto.php", {
        method: "POST",
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.error) {
            estadoTxt.textContent = "❌ " + data.error;
            btnAnalizar.disabled = false;
            return;
        }

        // Rellenar campos del formulario con la respuesta de la IA
        if (data.nombre)        document.getElementById("campo-nombre").value        = data.nombre;
        if (data.descripcion)   document.getElementById("campo-descripcion").value   = data.descripcion;
        if (data.calorias)      document.getElementById("campo-calorias").value      = data.calorias;
        if (data.proteinas)     document.getElementById("campo-proteinas").value     = data.proteinas;
        if (data.carbohidratos) document.getElementById("campo-carbohidratos").value = data.carbohidratos;
        if (data.grasas)        document.getElementById("campo-grasas").value        = data.grasas;

        // Seleccionar tipo de comida si coincide
        const selectTipo = document.getElementById("campo-tipo");
        if (data.tipo_comida) {
            for (let opt of selectTipo.options) {
                if (opt.value === data.tipo_comida) {
                    selectTipo.value = data.tipo_comida;
                    break;
                }
            }
        }

        estadoTxt.textContent = "✅ ¡Campos rellenados! Revisa y guarda.";
        btnAnalizar.disabled = false;

        // Scroll suave al formulario
        document.getElementById("form-comida").scrollIntoView({ behavior: "smooth" });
    })
    .catch(() => {
        estadoTxt.textContent = "❌ Error de conexión. Intenta de nuevo.";
        btnAnalizar.disabled = false;
    });
});
</script>

</body>
</html>