<?php
session_start();
require "conexion.php";
require "esPremium.php";

if (!isset($_SESSION["usuario"])) {
    header("Location: ../html/login.html");
    exit;
}

$usuario_id = $_SESSION["usuario"]["id"];
$nombre_u   = $_SESSION["usuario"]["nombre"];
$correo_u   = $_SESSION["usuario"]["correo"];

if (esPremium($conn, $usuario_id)) {
    header("Location: planes.php");
    exit;
}

$tipo = $_GET["tipo"] ?? "mensual";
$tipo = in_array($tipo, ["mensual", "anual"]) ? $tipo : "mensual";

// Obtener precio
$plan_p = $conn->query("SELECT * FROM planes_premium WHERE activo = 1 LIMIT 1")->fetch_assoc();
$monto  = $tipo === "anual" ? $plan_p["precio_anual"] : $plan_p["precio_mensual"];
$monto_fmt = number_format($monto, 0, ',', '.');

// Configuración PayU (Sandbox — reemplazar con datos reales en producción)
$payu_merchant_id  = "508029";        // Reemplazar con tu Merchant ID de PayU
$payu_account_id   = "512321";        // Reemplazar con tu Account ID
$payu_api_key      = "4Vj8eK4rloUd272L48hsrarnUA"; // Reemplazar con tu API Key
$payu_url          = "https://sandbox.checkout.payulatam.com/ppp-web-gateway-payu/"; // Cambiar a producción cuando esté listo

$referencia   = "PREMIUM_" . $usuario_id . "_" . time();
$descripcion  = "Focus Premium - Plan " . ucfirst($tipo);
$moneda       = "COP";
$url_respuesta = "https://tudominio.com/php/pago_respuesta.php"; // Cambiar por tu dominio
$url_confirmacion = "https://tudominio.com/php/pago_confirmacion.php";

// Firma PayU
$firma = md5($payu_api_key . "~" . $payu_merchant_id . "~" . $referencia . "~" . number_format($monto, 2, '.', '') . "~" . $moneda);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Suscripción Premium — FocusMeal</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Unbounded:wght@200;400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/dashboard.css">
  <style>
    .pago-wrap { max-width: 560px; margin: 48px auto; padding: 0 24px 64px; }
    .pago-card { background: #fff; border: 1.5px solid var(--border); border-radius: 18px; padding: 36px; }
    .pago-header { text-align: center; margin-bottom: 28px; }
    .pago-header h1 { font-family: 'Unbounded', sans-serif; font-weight: 400; font-size: 1.4rem; color: var(--navy); margin-bottom: 6px; }
    .pago-header p  { color: var(--text-light); font-size: 0.88rem; }
    .resumen-pago { background: var(--navy); border-radius: 12px; padding: 20px 24px; margin-bottom: 24px; color: #fff; }
    .resumen-pago .plan-nombre { font-size: 0.8rem; color: rgba(255,255,255,0.6); text-transform: uppercase; letter-spacing: 0.5px; }
    .resumen-pago .plan-precio { font-family: 'Unbounded', sans-serif; font-size: 1.8rem; font-weight: 600; color: var(--green); margin: 6px 0 2px; }
    .resumen-pago .plan-tipo   { font-size: 0.82rem; color: rgba(255,255,255,0.5); }
    .garantia { display: flex; align-items: center; gap: 8px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 12px 16px; margin-bottom: 20px; font-size: 0.82rem; color: #14532d; }
    .btn-pagar-payu { display: block; width: 100%; padding: 14px; background: var(--green); color: #fff; border: none; border-radius: 999px; font-family: 'Inter', sans-serif; font-size: 1rem; font-weight: 700; cursor: pointer; transition: background 0.2s; text-align: center; }
    .btn-pagar-payu:hover { background: #15803D; }
    .seguro-txt { text-align: center; font-size: 0.78rem; color: var(--text-light); margin-top: 14px; }
    .toggle-tipo { display: flex; border: 1.5px solid var(--border); border-radius: 999px; overflow: hidden; margin-bottom: 24px; }
    .toggle-tipo a { flex: 1; text-align: center; padding: 9px; font-size: 0.85rem; font-weight: 600; text-decoration: none; color: var(--text-light); transition: background 0.2s, color 0.2s; }
    .toggle-tipo a.activo { background: var(--navy); color: #fff; }
  </style>
</head>
<body>

<div class="panel-header">
  <div class="logo-container">
    <img src="../img/logo.png" alt="FocusMeal">
    <span>Focus Meal</span>
  </div>
  <a href="planes.php" class="btn-danger">← Volver a planes</a>
</div>

<div class="pago-wrap">
  <div class="pago-card">

    <div class="pago-header">
      <div style="font-size:2rem; margin-bottom:8px">⭐</div>
      <h1>Activar Premium</h1>
      <p>Estás a un paso de desbloquear el plan personalizado por IA y el chat con nutricionista.</p>
    </div>

    <!-- Toggle mensual/anual -->
    <div class="toggle-tipo">
      <a href="pago_premium.php?tipo=mensual" class="<?= $tipo === 'mensual' ? 'activo' : '' ?>">Mensual</a>
      <a href="pago_premium.php?tipo=anual"   class="<?= $tipo === 'anual'   ? 'activo' : '' ?>">Anual — Ahorra 25%</a>
    </div>

    <!-- Resumen -->
    <div class="resumen-pago">
      <div class="plan-nombre">Focus Premium</div>
      <div class="plan-precio">$<?= $monto_fmt ?> COP</div>
      <div class="plan-tipo">
        <?= $tipo === 'anual' ? 'Facturado una vez al año' : 'Facturado mensualmente' ?>
      </div>
    </div>

    <!-- Qué incluye -->
    <ul style="list-style:none; padding:0; margin-bottom:20px;">
      <?php
      $beneficios = [
        "Plan alimenticio personalizado por IA",
        "Menú semanal: desayuno, almuerzo y cena",
        "Macros calculados según tu perfil",
        "Chat en vivo con nutricionista",
        "Soporte prioritario"
      ];
      foreach ($beneficios as $b):
      ?>
        <li style="font-size:0.87rem; color:var(--text-mid); padding:6px 0; border-bottom:1px solid var(--border); display:flex; align-items:center; gap:8px;">
          <span style="color:var(--green); font-weight:700;">✓</span> <?= $b ?>
        </li>
      <?php endforeach; ?>
    </ul>

    <div class="garantia">
      🔒 Pago seguro procesado por PayU. Acepta PSE, tarjetas de crédito y débito.
    </div>

    <!-- Formulario PayU -->
    <form method="POST" action="<?= $payu_url ?>">
      <input type="hidden" name="merchantId"      value="<?= $payu_merchant_id ?>">
      <input type="hidden" name="accountId"       value="<?= $payu_account_id ?>">
      <input type="hidden" name="description"     value="<?= htmlspecialchars($descripcion) ?>">
      <input type="hidden" name="referenceCode"   value="<?= $referencia ?>">
      <input type="hidden" name="amount"          value="<?= number_format($monto, 2, '.', '') ?>">
      <input type="hidden" name="tax"             value="0">
      <input type="hidden" name="taxReturnBase"   value="0">
      <input type="hidden" name="currency"        value="<?= $moneda ?>">
      <input type="hidden" name="signature"       value="<?= $firma ?>">
      <input type="hidden" name="test"            value="1"> <!-- Cambiar a 0 en producción -->
      <input type="hidden" name="buyerEmail"      value="<?= htmlspecialchars($correo_u) ?>">
      <input type="hidden" name="buyerFullName"   value="<?= htmlspecialchars($nombre_u) ?>">
      <input type="hidden" name="responseUrl"     value="<?= $url_respuesta ?>">
      <input type="hidden" name="confirmationUrl" value="<?= $url_confirmacion ?>">
      <!-- Datos extra para activar la suscripción al confirmar -->
      <input type="hidden" name="extra1"          value="<?= $usuario_id ?>">
      <input type="hidden" name="extra2"          value="<?= $tipo ?>">

      <button type="submit" class="btn-pagar-payu">
        Pagar con PayU — $<?= $monto_fmt ?> COP
      </button>
    </form>

    <p class="seguro-txt">🔐 Tus datos están protegidos. Puedes cancelar cuando quieras.</p>

  </div>
</div>

</body>
</html>