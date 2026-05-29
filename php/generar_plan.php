<?php
session_start();
require "conexion.php";
require "esPremium.php";

if (!isset($_SESSION["usuario"])) {
    header("Location: ../html/login.html");
    exit;
}

$usuario_id = $_SESSION["usuario"]["id"];
$es_premium = esPremium($conn, $usuario_id);

if (!$es_premium) {
    header("Location: planes.php");
    exit;
}

$mensaje   = "";
$tipo_msg  = "";
$plan_json = null;

// Cargar perfil del usuario
$stmt = $conn->prepare("SELECT nombre, edad, genero, peso_actual, altura, objetivo, tipo_dieta FROM usuarios WHERE id_usuario = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$perfil = $stmt->get_result()->fetch_assoc();

// Verificar que tenga datos suficientes
$perfil_completo = $perfil["peso_actual"] && $perfil["altura"] && $perfil["edad"] && $perfil["objetivo"];

// Cargar último plan generado
$stmt2 = $conn->prepare("SELECT * FROM plan_generado WHERE id_usuario = ? ORDER BY fecha_gen DESC LIMIT 1");
$stmt2->bind_param("i", $usuario_id);
$stmt2->execute();
$ultimo_plan = $stmt2->get_result()->fetch_assoc();

// Generar plan si viene POST
if ($_SERVER["REQUEST_METHOD"] === "POST" && $perfil_completo) {

    $peso    = floatval($perfil["peso_actual"]);
    $altura  = floatval($perfil["altura"]);  // en cm
    $edad    = intval($perfil["edad"]);
    $genero  = $perfil["genero"];
    $obj     = $perfil["objetivo"];
    $dieta   = $perfil["tipo_dieta"] ?: "General";

    // Calcular TMB con Harris-Benedict
    if ($genero === "Femenino") {
        $tmb = 655 + (9.563 * $peso) + (1.850 * $altura) - (4.676 * $edad);
    } else {
        $tmb = 66 + (13.756 * $peso) + (5.003 * $altura) - (6.755 * $edad);
    }

    // Factor actividad moderada
    $tdee = round($tmb * 1.55);

    // Ajuste según objetivo
    if ($obj === "Bajar de peso")   $calorias_obj = $tdee - 400;
    elseif ($obj === "Aumentar masa") $calorias_obj = $tdee + 300;
    else $calorias_obj = $tdee;

    // Macros
    $proteinas_obj = round($peso * 1.8);           // g
    $grasas_obj    = round($calorias_obj * 0.25 / 9); // g
    $carbos_obj    = round(($calorias_obj - ($proteinas_obj * 4) - ($grasas_obj * 9)) / 4); // g

    // Prompt para Gemini
    $prompt = "Eres un nutricionista experto. Genera un plan alimenticio semanal personalizado en español.

Datos del usuario:
- Peso: {$peso} kg
- Altura: {$altura} cm
- Edad: {$edad} años
- Género: {$genero}
- Objetivo: {$obj}
- Tipo de dieta: {$dieta}
- Calorías objetivo: {$calorias_obj} kcal/día
- Proteínas objetivo: {$proteinas_obj} g/día
- Carbohidratos objetivo: {$carbos_obj} g/día
- Grasas objetivo: {$grasas_obj} g/día

Genera un menú para 7 días (Lunes a Domingo). Cada día debe tener: Desayuno, Almuerzo, Cena y Snack.
Para cada comida incluye: nombre del plato, ingredientes principales y calorías aproximadas.
Adapta todo al tipo de dieta indicada.

Responde ÚNICAMENTE con un JSON válido con este formato exacto, sin texto adicional:
{
  \"calorias_diarias\": número,
  \"proteinas_g\": número,
  \"carbos_g\": número,
  \"grasas_g\": número,
  \"dias\": [
    {
      \"dia\": \"Lunes\",
      \"desayuno\": { \"nombre\": \"\", \"ingredientes\": \"\", \"calorias\": número },
      \"almuerzo\": { \"nombre\": \"\", \"ingredientes\": \"\", \"calorias\": número },
      \"cena\":     { \"nombre\": \"\", \"ingredientes\": \"\", \"calorias\": número },
      \"snack\":    { \"nombre\": \"\", \"ingredientes\": \"\", \"calorias\": número }
    }
  ]
}";

    // Llamar a Gemini
    $api_key = "AIzaSyADpumXxTNj3fkiGynjsyQXpEJ9UIBzKJg"; // Reemplaza con tu key de aistudio.google.com
    $modelo  = "gemini-2.0-flash";
    $url     = "https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent?key={$api_key}";

    $body = json_encode([
        "contents" => [[
            "parts" => [["text" => $prompt]]
        ]],
        "generationConfig" => [
            "temperature"     => 0.3,
            "maxOutputTokens" => 3000
        ]
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => ["Content-Type: application/json"]
    ]);
    $respuesta = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        $err = json_decode($respuesta, true);
        $mensaje  = "Error al contactar la IA: " . ($err["error"]["message"] ?? "Código $http_code");
        $tipo_msg = "error";
    } else {
        $data  = json_decode($respuesta, true);
        $texto = trim($data["candidates"][0]["content"]["parts"][0]["text"] ?? "");
        $texto = preg_replace('/```json|```/i', '', $texto);
        preg_match('/\{.*\}/s', $texto, $matches);

        if (empty($matches)) {
            $mensaje  = "La IA no devolvió un plan válido. Intenta de nuevo.";
            $tipo_msg = "error";
        } else {
            $plan_data = json_decode($matches[0], true);
            if (!$plan_data) {
                $mensaje  = "Error interpretando la respuesta de la IA.";
                $tipo_msg = "error";
            } else {
                // Guardar en BD
                $contenido_json = json_encode($plan_data, JSON_UNESCAPED_UNICODE);
                $ins = $conn->prepare("
                    INSERT INTO plan_generado (id_usuario, contenido, calorias_obj, proteinas_obj, carbos_obj, grasas_obj)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $ins->bind_param("isiiii", $usuario_id, $contenido_json, $calorias_obj, $proteinas_obj, $carbos_obj, $grasas_obj);
                $ins->execute();

                $plan_json = $plan_data;
                $ultimo_plan = [
                    "contenido"    => $contenido_json,
                    "calorias_obj" => $calorias_obj,
                    "proteinas_obj"=> $proteinas_obj,
                    "carbos_obj"   => $carbos_obj,
                    "grasas_obj"   => $grasas_obj,
                    "fecha_gen"    => date("Y-m-d H:i:s")
                ];
                $mensaje  = "Plan generado correctamente.";
                $tipo_msg = "exito";
            }
        }
    }
}

// Cargar plan para mostrar
if (!$plan_json && $ultimo_plan) {
    $plan_json = json_decode($ultimo_plan["contenido"], true);
}

$dias_semana = ["Lunes","Martes","Miércoles","Jueves","Viernes","Sábado","Domingo"];
$active_page = 'plan_ia';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mi Plan Alimenticio IA — FocusMeal</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Unbounded:wght@200;400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="../css/styles.css">
  <style>
    /* Macros resumen */
    .macros-bar { display:flex; gap:12px; flex-wrap:wrap; }
    .macro-pill { background:var(--background); border: 1px solid var(--border); border-radius:10px; padding:14px 18px; flex:1; min-width:110px; text-align:center; transition: var(--transition); }
    .macro-pill:hover { border-color: var(--green); transform: translateY(-2px); }
    .macro-pill strong { display:block; font-size:1.3rem; color:var(--green); font-family: var(--font-headings); }
    .macro-pill span   { font-size:0.75rem; color:var(--text-light); font-weight: 500; }

    /* Selector de días */
    .dias-nav { display:flex; gap:8px; overflow-x:auto; padding-bottom:6px; margin-bottom:24px; scrollbar-width:none; }
    .dias-nav::-webkit-scrollbar { display:none; }
    .dia-btn {
      flex-shrink:0; padding:10px 22px;
      background:var(--surface); border:1px solid var(--border);
      border-radius:999px; font-family:'Inter',sans-serif; font-size:0.85rem;
      font-weight:600; color:var(--text-mid); cursor:pointer;
      transition:all 0.2s; white-space:nowrap;
    }
    .dia-btn:hover { border-color:var(--green); color:var(--green); }
    .dia-btn.activo { background:var(--navy); border-color:var(--navy); color:#fff; }

    /* Comidas del día */
    .comidas-dia { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
    @media(max-width:768px) { .comidas-dia { grid-template-columns:1fr; } }

    .comida-card { background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:24px; transition: var(--transition); position: relative; }
    .comida-card:hover { border-color:var(--green); transform:translateY(-2px); box-shadow: var(--shadow-sm); }
    .comida-tipo { font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--text-light); margin-bottom:8px; }
    .comida-nombre { font-weight:700; font-size:1rem; color:var(--navy); margin-bottom:6px; font-family: var(--font-headings); }
    .comida-ingredientes { font-size:0.85rem; color:var(--text-mid); line-height:1.6; margin-bottom:12px; }
    .comida-kcal { font-size:0.85rem; font-weight:700; color:var(--green); }

    .tipo-Desayuno  { border-left:4px solid #f59e0b; }
    .tipo-Almuerzo  { border-left:4px solid var(--green); }
    .tipo-Cena      { border-left:4px solid #7c3aed; }
    .tipo-Snack     { border-left:4px solid #f97316; }
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
                <h1 class="h3 font-headings mb-1" style="font-family: var(--font-headings); font-weight: 500;">🤖 Mi Plan IA</h1>
                <p class="text-muted mb-0" style="font-size: 0.9rem;">Menú semanal adaptado con Inteligencia Artificial</p>
            </div>
            <span class="badge bg-success py-2 px-3 rounded-pill" style="background-color: var(--green) !important;"><i class="fa-solid fa-crown me-1"></i> Premium</span>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert <?= $tipo_msg === 'exito' ? 'alert-success' : 'alert-danger' ?> alert-dismissible fade show mb-4 border-0 shadow-sm" role="alert" style="border-radius: var(--radius-md);">
                <strong><?= $tipo_msg === 'exito' ? '✅' : '❌' ?></strong> <?= htmlspecialchars($mensaje) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!$perfil_completo): ?>
            <div class="alert alert-warning border-0 shadow-sm mb-4" role="alert" style="border-radius: var(--radius-md);">
                <i class="fa-solid fa-triangle-exclamation me-2"></i> Para generar tu plan necesitas completar tus datos físicos y metas.
                <a href="ajustes.php" class="alert-link ms-1">Completar mi perfil ahora →</a>
            </div>
        <?php endif; ?>

        <div class="row g-4 mb-4">
            <!-- Info perfil -->
            <div class="col-lg-4">
                <div class="card p-4 border-0 shadow-sm h-100" style="border-radius: var(--radius-lg); background: var(--surface);">
                    <h3 class="h5 mb-3" style="font-family: var(--font-headings); font-weight: 500; color: var(--navy);"><i class="fa-solid fa-user-doctor me-2 text-success"></i>Perfil Nutricional</h3>
                    
                    <ul class="list-group list-group-flush mb-4" style="font-size: 0.9rem;">
                        <li class="list-group-item d-flex justify-content-between px-0 py-2.5 bg-transparent" style="border-color: var(--border);">
                            <span class="text-muted">Peso actual:</span>
                            <span class="fw-bold" style="color: var(--navy);"><?= $perfil["peso_actual"] ?: "—" ?> kg</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0 py-2.5 bg-transparent" style="border-color: var(--border);">
                            <span class="text-muted">Altura:</span>
                            <span class="fw-bold" style="color: var(--navy);"><?= $perfil["altura"] ?: "—" ?> cm</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0 py-2.5 bg-transparent" style="border-color: var(--border);">
                            <span class="text-muted">Edad:</span>
                            <span class="fw-bold" style="color: var(--navy);"><?= $perfil["edad"] ?: "—" ?> años</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0 py-2.5 bg-transparent" style="border-color: var(--border);">
                            <span class="text-muted">Objetivo:</span>
                            <span class="fw-bold text-success"><?= $perfil["objetivo"] ?: "—" ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0 py-2.5 bg-transparent" style="border-color: var(--border);">
                            <span class="text-muted">Dieta:</span>
                            <span class="fw-bold" style="color: var(--navy);"><?= $perfil["tipo_dieta"] ?: "General" ?></span>
                        </li>
                    </ul>

                    <?php if ($ultimo_plan): ?>
                        <p class="text-muted small mb-3">
                            <i class="fa-regular fa-clock me-1"></i> Generado el: <?= date("d/m/Y H:i", strtotime($ultimo_plan["fecha_gen"])) ?>
                        </p>
                    <?php endif; ?>

                    <form method="POST" action="generar_plan.php">
                        <button type="submit" class="btn btn-primary w-100 py-2.5 rounded-pill border-0" style="background: var(--green); font-weight: 600;" <?= !$perfil_completo ? "disabled" : "" ?>>
                            <i class="fa-solid fa-wand-magic-sparkles me-1"></i> <?= $ultimo_plan ? "Regenerar Plan" : "Crear Plan con IA" ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Macros objetivo -->
            <div class="col-lg-8">
                <?php if ($ultimo_plan): ?>
                <div class="card p-4 border-0 shadow-sm h-100" style="border-radius: var(--radius-lg); background: var(--surface);">
                    <h3 class="h5 mb-3" style="font-family: var(--font-headings); font-weight: 500; color: var(--navy);"><i class="fa-solid fa-chart-simple me-2 text-success"></i>Metas Diarias de Macronutrientes</h3>
                    
                    <div class="macros-bar mb-4">
                        <div class="macro-pill shadow-sm">
                            <strong><?= $ultimo_plan["calorias_obj"] ?></strong>
                            <span>Kcal Diarias</span>
                        </div>
                        <div class="macro-pill shadow-sm">
                            <strong><?= $ultimo_plan["proteinas_obj"] ?>g</strong>
                            <span>Proteínas</span>
                        </div>
                        <div class="macro-pill shadow-sm">
                            <strong><?= $ultimo_plan["carbos_obj"] ?>g</strong>
                            <span>Carbos</span>
                        </div>
                        <div class="macro-pill shadow-sm">
                            <strong><?= $ultimo_plan["grasas_obj"] ?>g</strong>
                            <span>Grasas</span>
                        </div>
                    </div>
                    <p class="text-muted small mb-0" style="line-height: 1.6;">
                        <i class="fa-solid fa-calculator me-1 text-success"></i> Calculado automáticamente con la fórmula Harris-Benedict para conseguir tu objetivo de <strong><?= htmlspecialchars($perfil["objetivo"]) ?></strong> de forma balanceada y saludable.
                    </p>
                </div>
                <?php else: ?>
                <div class="card p-4 border-0 shadow-sm h-100 d-flex flex-column align-items-center justify-content-center text-center py-5" style="border-radius: var(--radius-lg); background: var(--surface);">
                    <i class="fa-solid fa-robot fa-3x mb-3 text-muted opacity-25"></i>
                    <h4 style="font-family: var(--font-headings); font-weight: 500; color: var(--navy);">Calculadora IA lista</h4>
                    <p class="text-muted small max-width-350">Presiona generar en tu perfil nutricional para estructurar tus macros y comidas.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Menú semanal -->
        <?php if ($plan_json && isset($plan_json["dias"])): ?>
        <div class="card p-4 border-0 shadow-sm" style="border-radius: var(--radius-lg); background: var(--surface);">
            <h3 class="h5 mb-3" style="font-family: var(--font-headings); font-weight: 500; color: var(--navy);"><i class="fa-solid fa-calendar-days me-2 text-success"></i>Plan Semanal Sugerido</h3>

            <!-- Selector de días -->
            <div class="dias-nav mb-4" id="dias-nav">
                <?php foreach ($plan_json["dias"] as $i => $dia_data): ?>
                    <button class="dia-btn <?= $i === 0 ? 'activo' : '' ?>" onclick="mostrarDia(<?= $i ?>)" id="btn-dia-<?= $i ?>">
                        <?= htmlspecialchars($dia_data["dia"]) ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- Contenido por día -->
            <?php foreach ($plan_json["dias"] as $i => $dia_data): ?>
                <div id="dia-<?= $i ?>" style="display:<?= $i === 0 ? 'block' : 'none' ?>">
                    <div class="comidas-dia">
                        <?php
                        $comidas = [
                          "desayuno" => ["label" => "Desayuno", "clase" => "tipo-Desayuno", "icon" => "fa-solid fa-mug-saucer text-warning"],
                          "almuerzo" => ["label" => "Almuerzo", "clase" => "tipo-Almuerzo", "icon" => "fa-solid fa-utensils text-success"],
                          "cena"     => ["label" => "Cena",     "clase" => "tipo-Cena", "icon" => "fa-solid fa-moon text-primary"],
                          "snack"    => ["label" => "Snack",    "clase" => "tipo-Snack", "icon" => "fa-solid fa-apple-whole text-danger"],
                        ];
                        foreach ($comidas as $key => $info):
                          $c = $dia_data[$key] ?? null;
                          if (!$c) continue;
                        ?>
                          <div class="comida-card <?= $info['clase'] ?>">
                            <div class="comida-tipo"><i class="<?= $info['icon'] ?> me-1"></i> <?= $info["label"] ?></div>
                            <div class="comida-nombre"><?= htmlspecialchars($c["nombre"] ?? "") ?></div>
                            <div class="comida-ingredientes"><?= htmlspecialchars($c["ingredientes"] ?? "") ?></div>
                            <div class="comida-kcal">Estimación: ~<?= $c["calorias"] ?? 0 ?> kcal</div>
                          </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php elseif ($perfil_completo): ?>
            <div class="card p-5 border-0 shadow-sm text-center" style="border-radius: var(--radius-lg); background: var(--surface);">
                <i class="fa-solid fa-robot fa-3x mb-3 text-success"></i>
                <h4 style="font-family: var(--font-headings); font-weight: 500; color: var(--navy);">Genera tu menú ahora</h4>
                <p class="text-muted small">Crea un menú balanceado estructurado por días y horas de forma instantánea.</p>
            </div>
        <?php endif; ?>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function mostrarDia(idx) {
  document.querySelectorAll('[id^="dia-"]').forEach(d => d.style.display = 'none');
  document.querySelectorAll('.dia-btn').forEach(b => b.classList.remove('activo'));
  document.getElementById('dia-' + idx).style.display = 'block';
  document.getElementById('btn-dia-' + idx).classList.add('activo');
}
</script>

</body>
</html>