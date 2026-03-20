<?php
session_start();
require "conexion.php";
require "esPremium.php";

$logueado   = isset($_SESSION["usuario"]);
$usuario_id = $logueado ? $_SESSION["usuario"]["id"] : null;
$es_premium = $logueado ? esPremium($conn, $usuario_id) : false;

// Planes gratuitos disponibles
$planes_gratis = $conn->query("SELECT * FROM planes_disponibles ORDER BY calorias_diarias ASC");

// Precio premium
$plan_premium = $conn->query("SELECT * FROM planes_premium WHERE activo = 1 LIMIT 1")->fetch_assoc();
$precio_mensual = $plan_premium ? number_format($plan_premium['precio_mensual'], 0, ',', '.') : '19.900';
$precio_anual   = $plan_premium ? number_format($plan_premium['precio_anual'],   0, ',', '.') : '179.900';
$ahorro         = $plan_premium ? number_format(($plan_premium['precio_mensual'] * 12) - $plan_premium['precio_anual'], 0, ',', '.') : '58.900';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Planes — FocusMeal</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Unbounded:wght@200;400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/dashboard.css">
  <style>
    .planes-page { max-width: 1100px; margin: 0 auto; padding: 40px 24px 64px; }
    .planes-hero { text-align: center; margin-bottom: 48px; }
    .planes-hero h1 { font-family: 'Unbounded', sans-serif; font-weight: 400; font-size: 1.8rem; color: var(--navy); margin-bottom: 10px; }
    .planes-hero p  { color: var(--text-light); font-size: 0.95rem; }

    /* Toggle mensual/anual */
    .toggle-wrap { display: flex; align-items: center; justify-content: center; gap: 12px; margin: 24px 0 40px; }
    .toggle-label { font-size: 0.88rem; font-weight: 600; color: var(--text-light); }
    .toggle-label.activo { color: var(--navy); }
    .toggle-switch { position: relative; width: 48px; height: 26px; }
    .toggle-switch input { opacity: 0; width: 0; height: 0; }
    .toggle-track {
      position: absolute; inset: 0;
      background: var(--green); border-radius: 999px; cursor: pointer;
      transition: background 0.2s;
    }
    .toggle-track::before {
      content: ''; position: absolute;
      width: 20px; height: 20px; left: 3px; top: 3px;
      background: #fff; border-radius: 50%;
      transition: transform 0.2s;
    }
    .toggle-switch input:checked + .toggle-track { background: var(--navy); }
    .toggle-switch input:checked + .toggle-track::before { transform: translateX(22px); }
    .badge-ahorro { background: #dcfce7; color: #14532d; font-size: 0.72rem; font-weight: 700; padding: 3px 10px; border-radius: 999px; }

    /* Grid planes */
    .planes-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 28px; margin-bottom: 48px; }
    @media(max-width: 720px) { .planes-grid { grid-template-columns: 1fr; } }

    /* Card gratis */
    .plan-card-gratis {
      background: var(--surface);
      border: 1.5px solid var(--border);
      border-radius: 18px;
      padding: 32px 28px;
    }
    .plan-card-gratis h2 { font-size: 1.2rem; font-weight: 700; color: var(--navy); margin-bottom: 6px; }
    .plan-card-gratis .precio { font-family: 'Unbounded', sans-serif; font-size: 2rem; font-weight: 600; color: var(--green); margin: 12px 0 4px; }
    .plan-card-gratis .precio span { font-size: 0.85rem; font-weight: 400; color: var(--text-light); }
    .features-list { list-style: none; padding: 0; margin: 20px 0 24px; }
    .features-list li { font-size: 0.88rem; color: var(--text-mid); padding: 6px 0; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 8px; }
    .features-list li:last-child { border-bottom: none; }
    .check { color: var(--green); font-weight: 700; font-size: 0.9rem; }
    .cross { color: #d1d5db; font-weight: 700; font-size: 0.9rem; }

    /* Card premium */
    .plan-card-premium {
      background: var(--navy);
      border: 1.5px solid var(--navy);
      border-radius: 18px;
      padding: 32px 28px;
      position: relative;
      overflow: hidden;
    }
    .plan-card-premium::before {
      content: '';
      position: absolute; top: -40px; right: -40px;
      width: 150px; height: 150px;
      background: rgba(22,163,74,0.12);
      border-radius: 50%;
    }
    .badge-premium { display: inline-block; background: var(--green); color: #fff; font-size: 0.7rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; padding: 4px 12px; border-radius: 999px; margin-bottom: 12px; }
    .plan-card-premium h2 { font-size: 1.2rem; font-weight: 700; color: #fff; margin-bottom: 6px; }
    .plan-card-premium p  { font-size: 0.85rem; color: rgba(255,255,255,0.6); margin-bottom: 0; }
    .precio-premium { font-family: 'Unbounded', sans-serif; font-size: 2.2rem; font-weight: 600; color: var(--green); margin: 16px 0 4px; }
    .precio-premium span { font-size: 0.85rem; font-weight: 400; color: rgba(255,255,255,0.5); }
    .precio-anual-txt { font-size: 0.8rem; color: rgba(255,255,255,0.5); margin-bottom: 4px; }
    .features-list-premium { list-style: none; padding: 0; margin: 20px 0 28px; }
    .features-list-premium li { font-size: 0.88rem; color: rgba(255,255,255,0.8); padding: 7px 0; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; gap: 8px; }
    .features-list-premium li:last-child { border-bottom: none; }

    /* Botones */
    .btn-gratis {
      display: block; width: 100%; padding: 12px;
      background: transparent; color: var(--navy);
      border: 2px solid var(--navy); border-radius: 999px;
      font-family: 'Inter', sans-serif; font-size: 0.9rem; font-weight: 600;
      text-align: center; text-decoration: none; cursor: pointer;
      transition: background 0.2s, color 0.2s;
    }
    .btn-gratis:hover { background: var(--navy); color: #fff; }
    .btn-premium-pay {
      display: block; width: 100%; padding: 13px;
      background: var(--green); color: #fff;
      border: none; border-radius: 999px;
      font-family: 'Inter', sans-serif; font-size: 0.95rem; font-weight: 700;
      text-align: center; text-decoration: none; cursor: pointer;
      transition: background 0.2s, transform 0.15s;
      box-shadow: 0 4px 16px rgba(22,163,74,0.35);
    }
    .btn-premium-pay:hover { background: #15803D; transform: translateY(-1px); color: #fff; }
    .btn-premium-pay:disabled, .btn-premium-pay.ya-premium {
      background: rgba(255,255,255,0.15); color: rgba(255,255,255,0.5);
      cursor: not-allowed; box-shadow: none; transform: none;
    }

    /* Planes gratuitos detalle */
    .planes-gratis-titulo { font-family: 'Unbounded', sans-serif; font-weight: 400; font-size: 1.2rem; color: var(--navy); margin-bottom: 20px; }
    .mini-planes { display: flex; flex-wrap: wrap; gap: 16px; }
    .mini-plan {
      flex: 1; min-width: 180px;
      background: var(--surface); border: 1.5px solid var(--border);
      border-radius: 14px; padding: 20px 18px;
      transition: border-color 0.2s, transform 0.2s;
    }
    .mini-plan:hover { border-color: var(--green); transform: translateY(-3px); }
    .mini-plan h4 { font-size: 0.92rem; font-weight: 700; color: var(--navy); margin-bottom: 6px; }
    .mini-plan p  { font-size: 0.8rem; color: var(--text-light); margin-bottom: 10px; }
    .mini-plan .kcal { font-weight: 700; color: var(--green); font-size: 0.9rem; }
    .mini-plan form { margin-top: 10px; }
    .mini-plan button {
      width: 100%; padding: 8px; background: var(--navy); color: #fff;
      border: none; border-radius: 999px; font-family: 'Inter', sans-serif;
      font-size: 0.82rem; font-weight: 600; cursor: pointer;
      transition: background 0.2s;
    }
    .mini-plan button:hover { background: var(--green); }

    /* Comparación */
    .comparacion { margin-top: 56px; }
    .comparacion h2 { font-family: 'Unbounded', sans-serif; font-weight: 400; font-size: 1.2rem; color: var(--navy); margin-bottom: 20px; text-align: center; }
    table.comp { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
    table.comp th { padding: 12px 16px; text-align: left; background: var(--navy); color: #fff; font-weight: 600; }
    table.comp th:first-child { border-radius: 10px 0 0 0; }
    table.comp th:last-child  { border-radius: 0 10px 0 0; text-align: center; background: var(--green); }
    table.comp td { padding: 11px 16px; border-bottom: 1px solid var(--border); color: var(--text-mid); }
    table.comp td:last-child { text-align: center; }
    table.comp tr:last-child td { border-bottom: none; }
    table.comp tr:hover td { background: #f0f4ff; }
  </style>
</head>
<body>

<div class="panel-header">
  <div class="logo-container">
    <img src="../img/logo.png" alt="FocusMeal">
    <span>Focus Meal</span>
  </div>
  <div style="display:flex; gap:12px; align-items:center;">
    <?php if ($logueado): ?>
      <a href="panel.php" style="color:rgba(255,255,255,0.75); font-size:0.88rem; text-decoration:none;">Panel</a>
      <a href="logout.php" class="btn-danger">Cerrar sesión</a>
    <?php else: ?>
      <a href="../html/login.html" style="color:rgba(255,255,255,0.75); font-size:0.88rem; text-decoration:none;">Ingresar</a>
      <a href="../html/registro.html" class="btn-primary" style="padding:8px 18px; font-size:0.85rem;">Registrarse</a>
    <?php endif; ?>
  </div>
</div>

<div class="planes-page">

  <div class="planes-hero">
    <h1>Elige tu plan</h1>
    <p>Empieza gratis o desbloquea todo el potencial de FocusMeal con Premium.</p>
  </div>

  <!-- Toggle mensual / anual -->
  <div class="toggle-wrap">
    <span class="toggle-label activo" id="lbl-mensual">Mensual</span>
    <label class="toggle-switch">
      <input type="checkbox" id="toggle-periodo">
      <span class="toggle-track"></span>
    </label>
    <span class="toggle-label" id="lbl-anual">
      Anual <span class="badge-ahorro">Ahorra $<?= $ahorro ?></span>
    </span>
  </div>

  <!-- Comparación principal: Gratis vs Premium -->
  <div class="planes-grid">

    <!-- GRATIS -->
    <div class="plan-card-gratis">
      <h2>Plan Gratuito</h2>
      <p style="color:var(--text-light); font-size:0.85rem">Todo lo esencial para empezar.</p>
      <div class="precio">$0 <span>/ siempre</span></div>

      <ul class="features-list">
        <li><span class="check">✓</span> Registro de comidas diarias</li>
        <li><span class="check">✓</span> Seguimiento de peso y calorías</li>
        <li><span class="check">✓</span> Acceso a planes predefinidos</li>
        <li><span class="check">✓</span> Panel de progreso con gráficas</li>
        <li><span class="check">✓</span> Ajustes de perfil</li>
        <li><span class="cross">✗</span> <span style="color:#9ca3af">Plan alimenticio personalizado por IA</span></li>
        <li><span class="cross">✗</span> <span style="color:#9ca3af">Chat con nutricionista real</span></li>
        <li><span class="cross">✗</span> <span style="color:#9ca3af">Menú semanal generado</span></li>
      </ul>

      <?php if ($logueado): ?>
        <a href="panel.php" class="btn-gratis">Ir al panel</a>
      <?php else: ?>
        <a href="../html/registro.html" class="btn-gratis">Crear cuenta gratis</a>
      <?php endif; ?>
    </div>

    <!-- PREMIUM -->
    <div class="plan-card-premium">
      <div class="badge-premium">⭐ Premium</div>
      <h2>Focus Premium</h2>
      <p>IA + nutricionista real + todo lo que necesitas para alcanzar tu objetivo.</p>

      <div class="precio-premium" id="precio-display">
        $<?= $precio_mensual ?> <span id="periodo-txt">/ mes</span>
      </div>
      <p class="precio-anual-txt" id="anual-txt" style="display:none">
        Facturado anualmente: $<?= $precio_anual ?>/año
      </p>

      <ul class="features-list-premium">
        <li><span class="check">✓</span> Todo lo del plan gratuito</li>
        <li><span class="check">✓</span> Plan alimenticio personalizado por IA</li>
        <li><span class="check">✓</span> Menú semanal (desayuno, almuerzo, cena)</li>
        <li><span class="check">✓</span> Macros calculados según tu perfil</li>
        <li><span class="check">✓</span> Chat en vivo con nutricionista</li>
        <li><span class="check">✓</span> Soporte prioritario</li>
      </ul>

      <?php if ($es_premium): ?>
        <button class="btn-premium-pay ya-premium" disabled>Ya eres Premium ✓</button>
      <?php elseif ($logueado): ?>
        <a href="pago_premium.php?tipo=mensual" class="btn-premium-pay" id="btn-pagar">
          Obtener Premium
        </a>
      <?php else: ?>
        <a href="../html/registro.html" class="btn-premium-pay">
          Crear cuenta y suscribirse
        </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Planes gratuitos disponibles -->
  <div class="planes-gratis-titulo">Planes nutricionales incluidos (gratis)</div>
  <div class="mini-planes">
    <?php
    if ($planes_gratis && $planes_gratis->num_rows > 0):
      while ($plan = $planes_gratis->fetch_assoc()):
    ?>
      <div class="mini-plan">
        <h4><?= htmlspecialchars($plan['nombre_plan']) ?></h4>
        <p><?= htmlspecialchars($plan['descripcion'] ?? '') ?></p>
        <div class="kcal"><?= $plan['calorias_diarias'] ?> kcal/día</div>
        <div style="font-size:0.75rem; color:var(--text-light); margin-top:2px"><?= htmlspecialchars($plan['tipo_dieta'] ?? '') ?></div>
        <?php if ($logueado): ?>
          <form action="guardar_plan.php" method="POST">
            <input type="hidden" name="id_plan" value="<?= $plan['id_plan'] ?>">
            <button type="submit">Seleccionar</button>
          </form>
        <?php else: ?>
          <a href="../html/login.html" style="display:block; margin-top:10px; text-align:center; font-size:0.82rem; color:var(--green); font-weight:600;">Inicia sesión para elegir</a>
        <?php endif; ?>
      </div>
    <?php endwhile; else: ?>
      <p>No hay planes disponibles.</p>
    <?php endif; ?>
  </div>

  <!-- Tabla comparativa -->
  <div class="comparacion">
    <h2>Comparación detallada</h2>
    <div class="card" style="padding:0; overflow:hidden;">
      <table class="comp">
        <thead>
          <tr>
            <th>Funcionalidad</th>
            <th>Gratuito</th>
            <th>Premium</th>
          </tr>
        </thead>
        <tbody>
          <tr><td>Registro de comidas diarias</td><td>✓</td><td>✓</td></tr>
          <tr><td>Seguimiento de peso</td><td>✓</td><td>✓</td></tr>
          <tr><td>Gráficas de progreso</td><td>✓</td><td>✓</td></tr>
          <tr><td>Planes predefinidos</td><td>3 planes</td><td>Ilimitados</td></tr>
          <tr><td>Plan alimenticio generado por IA</td><td>—</td><td>✓</td></tr>
          <tr><td>Menú semanal (desayuno, almuerzo, cena)</td><td>—</td><td>✓</td></tr>
          <tr><td>Macros calculados según tu perfil</td><td>—</td><td>✓</td></tr>
          <tr><td>Chat con nutricionista real</td><td>—</td><td>✓</td></tr>
          <tr><td>Soporte prioritario</td><td>—</td><td>✓</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($logueado): ?>
    <br><a href="panel.php" style="color:var(--text-light); font-size:0.88rem;">← Volver al panel</a>
  <?php endif; ?>

</div>

<script>
const toggle      = document.getElementById('toggle-periodo');
const precioEl    = document.getElementById('precio-display');
const periodoTxt  = document.getElementById('periodo-txt');
const anualTxt    = document.getElementById('anual-txt');
const lblMensual  = document.getElementById('lbl-mensual');
const lblAnual    = document.getElementById('lbl-anual');
const btnPagar    = document.getElementById('btn-pagar');

const precioMensual = '<?= $precio_mensual ?>';
const precioAnual   = '<?= $precio_anual ?>';

toggle.addEventListener('change', function() {
  if (this.checked) {
    precioEl.innerHTML = '$' + precioAnual + ' <span id="periodo-txt">/ año</span>';
    anualTxt.style.display = 'block';
    anualTxt.textContent   = 'Equivale a $' + Math.round(<?= $plan_premium ? $plan_premium['precio_anual'] : 179900 ?> / 12).toLocaleString('es-CO') + '/mes';
    lblMensual.classList.remove('activo');
    lblAnual.classList.add('activo');
    if (btnPagar) btnPagar.href = 'pago_premium.php?tipo=anual';
  } else {
    precioEl.innerHTML = '$' + precioMensual + ' <span id="periodo-txt">/ mes</span>';
    anualTxt.style.display = 'none';
    lblMensual.classList.add('activo');
    lblAnual.classList.remove('activo');
    if (btnPagar) btnPagar.href = 'pago_premium.php?tipo=mensual';
  }
});
</script>

</body>
</html>