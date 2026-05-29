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
  <link rel="stylesheet" href="../css/styles.css">
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