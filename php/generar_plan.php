<?php
session_start();
require "conexion.php";
require "esPremium.php";

if (!isset($_SESSION["usuario"])) {
    header("Location: ../html/login.html");
    exit;
}

$usuario_id = $_SESSION["usuario"]["id"];

if (!esPremium($conn, $usuario_id)) {
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mi Plan Alimenticio — FocusMeal</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Unbounded:wght@200;400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/dashboard.css">
  <style>
    .badge-premium { display:inline-block; background:var(--green); color:#fff; font-size:0.7rem; font-weight:700; letter-spacing:1px; text-transform:uppercase; padding:3px 10px; border-radius:999px; margin-left:8px; vertical-align:middle; }

    /* Macros resumen */
    .macros-bar { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:24px; }
    .macro-pill { background:var(--navy); color:#fff; border-radius:10px; padding:14px 18px; flex:1; min-width:120px; text-align:center; }
    .macro-pill strong { display:block; font-size:1.3rem; color:var(--green); }
    .macro-pill span   { font-size:0.75rem; color:rgba(255,255,255,0.6); }

    /* Selector de días */
    .dias-nav { display:flex; gap:8px; overflow-x:auto; padding-bottom:4px; margin-bottom:24px; scrollbar-width:none; }
    .dias-nav::-webkit-scrollbar { display:none; }
    .dia-btn {
      flex-shrink:0; padding:8px 16px;
      background:var(--surface); border:1.5px solid var(--border);
      border-radius:999px; font-family:'Inter',sans-serif; font-size:0.83rem;
      font-weight:600; color:var(--text-mid); cursor:pointer;
      transition:all 0.2s; white-space:nowrap;
    }
    .dia-btn:hover { border-color:var(--green); color:var(--green); }
    .dia-btn.activo { background:var(--navy); border-color:var(--navy); color:#fff; }

    /* Comidas del día */
    .comidas-dia { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
    @media(max-width:600px) { .comidas-dia { grid-template-columns:1fr; } }

    .comida-card { background:var(--surface); border:1.5px solid var(--border); border-radius:14px; padding:20px; transition:border-color 0.2s, transform 0.2s; }
    .comida-card:hover { border-color:var(--green); transform:translateY(-2px); }
    .comida-tipo { font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:var(--text-light); margin-bottom:8px; }
    .comida-nombre { font-weight:700; font-size:0.95rem; color:var(--navy); margin-bottom:6px; }
    .comida-ingredientes { font-size:0.82rem; color:var(--text-light); line-height:1.5; margin-bottom:10px; }
    .comida-kcal { font-size:0.82rem; font-weight:700; color:var(--green); }

    .tipo-Desayuno  { border-top:3px solid #f59e0b; }
    .tipo-Almuerzo  { border-top:3px solid var(--green); }
    .tipo-Cena      { border-top:3px solid #7c3aed; }
    .tipo-Snack     { border-top:3px solid #f97316; }

    /* Sin datos */
    .sin-perfil { background:#fffbeb; border:1px solid #fde68a; border-radius:12px; padding:20px 24px; margin-bottom:24px; }
    .sin-perfil p { color:#92400e; font-size:0.88rem; margin:0; }

    .btn-generar { padding:12px 28px; background:var(--green); color:#fff; border:none; border-radius:999px; font-family:'Inter',sans-serif; font-size:0.92rem; font-weight:700; cursor:pointer; transition:background 0.2s, transform 0.15s; }
    .btn-generar:hover { background:#15803D; transform:translateY(-1px); }
    .btn-generar:disabled { background:#9ca3af; cursor:not-allowed; transform:none; }
  </style>
</head>
<body>

<div class="panel-header">
  <div class="logo-container">
    <img src="../img/logo.png" alt="FocusMeal">
    <span>Focus Meal</span>
  </div>
  <a href="logout.php" class="btn-danger">Cerrar sesión</a>
</div>

<div class="panel-container">

  <h1>🤖 Mi Plan Alimenticio <span class="badge-premium">Premium</span></h1>

  <?php if ($mensaje): ?>
    <p><strong><?= $tipo_msg === "exito" ? "✅" : "❌" ?> <?= htmlspecialchars($mensaje) ?></strong></p>
  <?php endif; ?>

  <?php if (!$perfil_completo): ?>
    <div class="sin-perfil">
      <p>⚠️ Para generar tu plan necesitas completar tu perfil (peso, altura, edad y objetivo).
         <a href="ajustes.php" style="color:#92400e; font-weight:700;">Completar perfil →</a>
      </p>
    </div>
  <?php endif; ?>

  <div class="stats" style="margin-bottom:24px">

    <!-- Info perfil -->
    <div class="card" style="flex:1; min-width:240px">
      <h3>Tu perfil nutricional</h3>
      <p><strong>Peso:</strong> <?= $perfil["peso_actual"] ?: "—" ?> kg</p>
      <p><strong>Altura:</strong> <?= $perfil["altura"] ?: "—" ?> cm</p>
      <p><strong>Edad:</strong> <?= $perfil["edad"] ?: "—" ?> años</p>
      <p><strong>Objetivo:</strong> <?= $perfil["objetivo"] ?: "—" ?></p>
      <p><strong>Tipo de dieta:</strong> <?= $perfil["tipo_dieta"] ?: "General" ?></p>
      <?php if ($ultimo_plan): ?>
        <p style="margin-top:12px; font-size:0.78rem; color:var(--text-light)">
          Último plan: <?= date("d/m/Y H:i", strtotime($ultimo_plan["fecha_gen"])) ?>
        </p>
      <?php endif; ?>
      <br>
      <form method="POST" action="generar_plan.php">
        <button type="submit" class="btn-generar" <?= !$perfil_completo ? "disabled" : "" ?>>
          <?= $ultimo_plan ? "🔄 Regenerar plan" : "✨ Generar mi plan" ?>
        </button>
      </form>
    </div>

    <!-- Macros objetivo -->
    <?php if ($ultimo_plan): ?>
    <div class="card" style="flex:2; min-width:280px">
      <h3>Macros diarios objetivos</h3>
      <div class="macros-bar">
        <div class="macro-pill">
          <strong><?= $ultimo_plan["calorias_obj"] ?></strong>
          <span>kcal/día</span>
        </div>
        <div class="macro-pill">
          <strong><?= $ultimo_plan["proteinas_obj"] ?>g</strong>
          <span>Proteínas</span>
        </div>
        <div class="macro-pill">
          <strong><?= $ultimo_plan["carbos_obj"] ?>g</strong>
          <span>Carbohidratos</span>
        </div>
        <div class="macro-pill">
          <strong><?= $ultimo_plan["grasas_obj"] ?>g</strong>
          <span>Grasas</span>
        </div>
      </div>
      <p style="font-size:0.82rem; color:var(--text-light)">
        Calculados con la fórmula Harris-Benedict según tu perfil y objetivo de <strong><?= $perfil["objetivo"] ?></strong>.
      </p>
    </div>
    <?php endif; ?>

  </div>

  <!-- Menú semanal -->
  <?php if ($plan_json && isset($plan_json["dias"])): ?>

    <div class="card">
      <h3>📅 Menú semanal</h3>

      <!-- Selector de días -->
      <div class="dias-nav" id="dias-nav">
        <?php foreach ($plan_json["dias"] as $i => $dia_data): ?>
          <button class="dia-btn <?= $i === 0 ? 'activo' : '' ?>"
                  onclick="mostrarDia(<?= $i ?>)"
                  id="btn-dia-<?= $i ?>">
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
              "desayuno" => ["label" => "Desayuno", "clase" => "tipo-Desayuno"],
              "almuerzo" => ["label" => "Almuerzo", "clase" => "tipo-Almuerzo"],
              "cena"     => ["label" => "Cena",     "clase" => "tipo-Cena"],
              "snack"    => ["label" => "Snack",    "clase" => "tipo-Snack"],
            ];
            foreach ($comidas as $key => $info):
              $c = $dia_data[$key] ?? null;
              if (!$c) continue;
            ?>
              <div class="comida-card <?= $info['clase'] ?>">
                <div class="comida-tipo"><?= $info["label"] ?></div>
                <div class="comida-nombre"><?= htmlspecialchars($c["nombre"] ?? "") ?></div>
                <div class="comida-ingredientes"><?= htmlspecialchars($c["ingredientes"] ?? "") ?></div>
                <div class="comida-kcal">~<?= $c["calorias"] ?? 0 ?> kcal</div>
              </div>
            <?php endforeach; ?>

          </div>
        </div>
      <?php endforeach; ?>

    </div>

  <?php elseif ($perfil_completo): ?>
    <div class="card" style="text-align:center; padding:48px">
      <div style="font-size:2.5rem; margin-bottom:12px">🤖</div>
      <p>Aún no tienes un plan generado. Presiona el botón para que la IA cree tu menú semanal personalizado.</p>
    </div>
  <?php endif; ?>

  <br>
  <a href="panel.php">← Volver al panel</a>

</div>

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