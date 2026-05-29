<?php
session_start();
require "conexion.php";
require __DIR__ . '/esPremium.php';

if (!isset($_SESSION["usuario"])) {
    header("Location: ../html/login.html");
    exit;
}

$usuario_id = $_SESSION["usuario"]["id"];
$es_premium = esPremium($conn, $usuario_id);
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
        $ins->bind_param("isssiddd", $id_plan, $tipo_comida, $nombre_comida, $descripcion, $calorias, $proteinas, $carbohidratos, $grasas);

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
$active_page = 'comida';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Agregar Comida — FocusMeal</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Unbounded:wght@200;400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="../css/styles.css">
  <style>
    /* Estilo de la zona de subida */
    .dropzone-area {
      border: 2px dashed var(--border);
      border-radius: var(--radius-md);
      padding: 32px 20px;
      text-align: center;
      background: var(--background);
      cursor: pointer;
      transition: var(--transition);
      position: relative;
    }
    .dropzone-area:hover {
      border-color: var(--green);
      background: rgba(22,163,74,0.02);
    }
    .dropzone-area input[type="file"] {
      position: absolute;
      top: 0; left: 0; width: 100%; height: 100%;
      opacity: 0;
      cursor: pointer;
    }
    .dropzone-icon {
      font-size: 2.2rem;
      color: var(--text-light);
      margin-bottom: 12px;
    }
  </style>
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
                <h1 class="h3 font-headings mb-1" style="font-family: var(--font-headings); font-weight: 500;">🍽 Agregar Comida</h1>
                <p class="text-muted mb-0" style="font-size: 0.9rem;">Registra tus platos manualmente o usando nuestra IA nutricional por imagen</p>
            </div>
            <?php if ($es_premium): ?>
                <span class="badge bg-success py-2 px-3 rounded-pill" style="background-color: var(--green) !important;"><i class="fa-solid fa-crown me-1"></i> Premium</span>
            <?php endif; ?>
        </div>

        <?php if (!$plan_activo): ?>
            <div class="alert alert-warning border-0 shadow-sm mb-4" role="alert" style="border-radius: var(--radius-md);">
                <i class="fa-solid fa-circle-exclamation me-2"></i> No tienes un plan alimenticio activo. <a href="planes.php" class="alert-link">Elige un plan primero</a> para poder registrar comidas.
            </div>
        <?php endif; ?>

        <?php if ($mensaje): ?>
            <div class="alert <?= $tipo_msg === 'exito' ? 'alert-success' : 'alert-danger' ?> alert-dismissible fade show mb-4 border-0 shadow-sm" role="alert" style="border-radius: var(--radius-md);">
                <strong><?= $tipo_msg === 'exito' ? '✅' : '❌' ?></strong> <?= htmlspecialchars($mensaje) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- COLUMNA IZQUIERDA: Registro de comidas -->
            <div class="col-lg-7">
                <?php if ($plan_activo): ?>
                <!-- ANÁLISIS POR FOTO -->
                <div class="card p-4 border-0 shadow-sm mb-4" style="border-radius: var(--radius-lg); background: var(--surface);">
                    <h3 class="h5 mb-2" style="font-family: var(--font-headings); font-weight: 500; color: var(--navy);"><i class="fa-solid fa-camera me-2 text-success"></i>Analizar plato con IA</h3>
                    <p class="text-muted mb-3" style="font-size:0.86rem;">
                        Sube o toma una foto de tu plato para que la IA estime automáticamente los ingredientes, calorías y macronutrientes.
                    </p>
                    <div id="zona-foto">
                        <div class="dropzone-area">
                            <i class="fa-solid fa-cloud-arrow-up dropzone-icon"></i>
                            <p class="mb-1" style="font-weight: 600; font-size: 0.9rem; color: var(--navy);">Sube tu foto aquí</p>
                            <p class="text-muted small mb-0">Haz clic o arrastra tu archivo (Captura / Galería)</p>
                            <input type="file" id="input-foto" accept="image/*" capture="environment">
                        </div>
                        <img id="preview-foto" src="" alt="Vista previa del plato" class="img-fluid mt-3" style="display:none; border-radius: var(--radius-md); max-height: 220px; object-fit: cover; width: 100%;">
                        <button type="button" id="btn-analizar" class="btn btn-primary w-100 mt-3 py-2 border-0" style="background: var(--green); font-weight: 600; display:none;">
                            <i class="fa-solid fa-wand-magic-sparkles me-1"></i> Analizar con IA
                        </button>
                        <p id="estado-analisis" class="text-center mt-2 small font-weight-bold mb-0"></p>
                    </div>
                </div>

                <!-- FORMULARIO MANUAL -->
                <div class="card p-4 border-0 shadow-sm" style="border-radius: var(--radius-lg); background: var(--surface);">
                    <h3 class="h5 mb-3" style="font-family: var(--font-headings); font-weight: 500; color: var(--navy);"><i class="fa-solid fa-keyboard me-2 text-success"></i>Registro manual</h3>
                    <form method="POST" action="agregar_comida.php" id="form-comida">
                        <input type="hidden" name="id_plan" value="<?= $plan_activo['id_plan'] ?>">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label font-weight-bold" style="font-size: 0.82rem; text-transform: uppercase; color: var(--text-light);">Tipo de comida *</label>
                                <select class="form-select" name="tipo_comida" id="campo-tipo" required>
                                    <option value="">Seleccionar...</option>
                                    <option value="Desayuno">Desayuno</option>
                                    <option value="Almuerzo">Almuerzo</option>
                                    <option value="Cena">Cena</option>
                                    <option value="Snack">Snack</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label font-weight-bold" style="font-size: 0.82rem; text-transform: uppercase; color: var(--text-light);">Nombre del alimento *</label>
                                <input type="text" class="form-control" name="nombre_comida" id="campo-nombre" placeholder="Ej: Avena con plátano" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label font-weight-bold" style="font-size: 0.82rem; text-transform: uppercase; color: var(--text-light);">Descripción / Notas (opcional)</label>
                            <textarea class="form-control" name="descripcion" id="campo-descripcion" rows="2" placeholder="Detalles de preparación o ingredientes..."></textarea>
                        </div>

                        <div class="row">
                            <div class="col-6 col-md-3 mb-3">
                                <label class="form-label font-weight-bold" style="font-size: 0.82rem; text-transform: uppercase; color: var(--text-light);">Calorías (kcal)</label>
                                <input type="number" class="form-control" name="calorias" id="campo-calorias" min="0" placeholder="0">
                            </div>
                            <div class="col-6 col-md-3 mb-3">
                                <label class="form-label font-weight-bold" style="font-size: 0.82rem; text-transform: uppercase; color: var(--text-light);">Proteínas (g)</label>
                                <input type="number" class="form-control" name="proteinas" id="campo-proteinas" step="0.1" min="0" placeholder="0">
                            </div>
                            <div class="col-6 col-md-3 mb-3">
                                <label class="form-label font-weight-bold" style="font-size: 0.82rem; text-transform: uppercase; color: var(--text-light);">Carbohidratos (g)</label>
                                <input type="number" class="form-control" name="carbohidratos" id="campo-carbohidratos" step="0.1" min="0" placeholder="0">
                            </div>
                            <div class="col-6 col-md-3 mb-3">
                                <label class="form-label font-weight-bold" style="font-size: 0.82rem; text-transform: uppercase; color: var(--text-light);">Grasas (g)</label>
                                <input type="number" class="form-control" name="grasas" id="campo-grasas" step="0.1" min="0" placeholder="0">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2.5 mt-2 rounded-pill border-0" style="background: var(--green); font-weight: 600;">Guardar Alimento</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <!-- COLUMNA DERECHA: Resumen de hoy -->
            <div class="col-lg-5">
                <div class="card p-4 border-0 shadow-sm mb-4" style="border-radius: var(--radius-lg); background: var(--surface);">
                    <h3 class="h5 mb-3" style="font-family: var(--font-headings); font-weight: 500; color: var(--navy);"><i class="fa-solid fa-chart-pie me-2 text-success"></i>Consumo diario</h3>
                    
                    <div class="p-3 mb-3 text-center rounded" style="background: var(--background);">
                        <h2 class="h1 mb-1 font-headings" style="color: var(--green); font-weight: 600;"><?= $total_cal ?> <span style="font-size: 1rem; font-family: var(--font-body); color: var(--text-light);">kcal</span></h2>
                        <p class="text-muted small mb-0">Consumidas de <?= $meta_cal ?> kcal de tu meta (<?= $pct_cal ?>%)</p>
                        
                        <div class="progress mt-3" style="height: 10px; border-radius: 999px;">
                            <div class="progress-bar" role="progressbar" style="width: <?= $pct_cal ?>%; background-color: var(--green);" aria-valuenow="<?= $pct_cal ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>

                    <div class="row text-center mt-3 g-2">
                        <div class="col-4">
                            <div class="p-2 border rounded" style="background: rgba(99, 102, 241, 0.04); border-color: rgba(99, 102, 241, 0.1) !important;">
                                <span class="d-block text-muted small">Proteínas</span>
                                <strong style="color: #6366f1;"><?= round($total_prot, 1) ?>g</strong>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 border rounded" style="background: rgba(14, 165, 233, 0.04); border-color: rgba(14, 165, 233, 0.1) !important;">
                                <span class="d-block text-muted small">Carbohidratos</span>
                                <strong style="color: #0ea5e9;"><?= round($total_carb, 1) ?>g</strong>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2 border rounded" style="background: rgba(245, 158, 11, 0.04); border-color: rgba(245, 158, 11, 0.1) !important;">
                                <span class="d-block text-muted small">Grasas</span>
                                <strong style="color: #f59e0b;"><?= round($total_gras, 1) ?>g</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card p-4 border-0 shadow-sm" style="border-radius: var(--radius-lg); background: var(--surface);">
                    <h3 class="h5 mb-3" style="font-family: var(--font-headings); font-weight: 500; color: var(--navy);"><i class="fa-solid fa-clipboard-list me-2 text-success"></i>Registros de hoy</h3>

                    <?php if (count($comidas_hoy) === 0): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fa-solid fa-cookie fa-2x mb-2 opacity-25"></i>
                            <p class="small mb-0">No has registrado alimentos hoy.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle table-sm" style="font-size: 0.88rem;">
                                <thead>
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Alimento</th>
                                        <th class="text-end">kcal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($comidas_hoy as $c): ?>
                                        <tr>
                                            <td style="font-weight: 600; color: var(--text-mid);"><?= htmlspecialchars($c['tipo_comida']) ?></td>
                                            <td><?= htmlspecialchars($c['nombre_comida']) ?></td>
                                            <td class="text-end font-weight-bold text-success"><?= $c['calorias'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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
        btnAnalizar.style.display = "block";
        estadoTxt.textContent = "";
    };
    reader.readAsDataURL(archivo);
});

// Enviar foto a analizar_foto.php y rellenar el formulario
btnAnalizar.addEventListener("click", function () {
    const archivo = inputFoto.files[0];
    if (!archivo) return;

    estadoTxt.className = "text-center mt-2 small text-primary";
    estadoTxt.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Analizando plato con IA nutricional...';
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
            estadoTxt.className = "text-center mt-2 small text-danger";
            estadoTxt.textContent = "❌ " + data.error;
            btnAnalizar.disabled = false;
            return;
        }

        // Rellenar campos del formulario
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
                if (opt.value.toLowerCase() === data.tipo_comida.toLowerCase()) {
                    selectTipo.value = opt.value;
                    break;
                }
            }
        }

        estadoTxt.className = "text-center mt-2 small text-success";
        estadoTxt.textContent = "✅ ¡Campos calculados con éxito! Revisa e ingresa.";
        btnAnalizar.disabled = false;

        // Scroll suave al formulario
        document.getElementById("form-comida").scrollIntoView({ behavior: "smooth" });
    })
    .catch(() => {
        estadoTxt.className = "text-center mt-2 small text-danger";
        estadoTxt.textContent = "❌ Error de conexión con el analizador.";
        btnAnalizar.disabled = false;
    });
});
</script>

</body>
</html>