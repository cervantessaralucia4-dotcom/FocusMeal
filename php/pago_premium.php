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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Unbounded:wght@200;400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="../css/styles.css">
  <style>
    body {
      background-color: var(--navy);
      color: #fff;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 40px 20px;
    }
    .checkout-card {
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid rgba(255, 255, 255, 0.08);
      backdrop-filter: blur(10px);
      border-radius: var(--radius-lg);
      max-width: 520px;
      width: 100%;
      padding: 40px;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    }
    .logo-header {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      margin-bottom: 24px;
      text-decoration: none;
    }
    .logo-header span {
      font-family: var(--font-headings);
      font-weight: 500;
      color: #fff;
      font-size: 1.25rem;
    }
    .toggle-tipo {
      display: flex;
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 999px;
      overflow: hidden;
      margin-bottom: 24px;
      background: rgba(255, 255, 255, 0.02);
      padding: 4px;
    }
    .toggle-tipo a {
      flex: 1;
      text-align: center;
      padding: 10px;
      font-size: 0.85rem;
      font-weight: 600;
      text-decoration: none;
      color: rgba(255, 255, 255, 0.6);
      border-radius: 999px;
      transition: var(--transition);
    }
    .toggle-tipo a.activo {
      background: var(--green);
      color: #fff;
    }
    .toggle-tipo a:hover:not(.activo) {
      color: #fff;
      background: rgba(255, 255, 255, 0.05);
    }
    .resumen-pago {
      background: rgba(22, 163, 74, 0.1);
      border: 1px solid rgba(22, 163, 74, 0.2);
      border-radius: var(--radius-md);
      padding: 20px 24px;
      margin-bottom: 24px;
      text-align: center;
    }
    .resumen-pago .plan-nombre {
      font-size: 0.8rem;
      color: rgba(255, 255, 255, 0.5);
      text-transform: uppercase;
      letter-spacing: 1px;
      font-weight: 600;
    }
    .resumen-pago .plan-precio {
      font-family: var(--font-headings);
      font-size: 2rem;
      font-weight: 600;
      color: var(--green);
      margin: 8px 0 4px;
    }
    .resumen-pago .plan-tipo {
      font-size: 0.8rem;
      color: rgba(255, 255, 255, 0.4);
    }
    .benefit-item {
      font-size: 0.88rem;
      color: rgba(255, 255, 255, 0.7);
      padding: 8px 0;
      border-bottom: 1px solid rgba(255, 255, 255, 0.06);
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .btn-pagar-payu {
      display: block;
      width: 100%;
      padding: 14px;
      background: var(--green);
      color: #fff;
      border: none;
      border-radius: 999px;
      font-family: 'Inter', sans-serif;
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
      transition: var(--transition);
      text-align: center;
      margin-top: 24px;
      box-shadow: 0 4px 15px rgba(22, 163, 74, 0.3);
    }
    .btn-pagar-payu:hover {
      background: #15803D;
      transform: translateY(-2px);
    }
    .garantia {
      background: rgba(255, 255, 255, 0.02);
      border: 1px solid rgba(255, 255, 255, 0.05);
      border-radius: var(--radius-md);
      padding: 12px 16px;
      font-size: 0.8rem;
      color: rgba(255, 255, 255, 0.5);
      display: flex;
      align-items: center;
      gap: 10px;
      margin-top: 20px;
    }
  </style>
</head>
<body>

<div class="checkout-card">
    <a href="planes.php" class="logo-header">
        <img src="../img/logo.png" alt="FocusMeal Logo" style="height: 36px;">
        <span>Focus Meal</span>
    </a>

    <div class="text-center mb-4">
        <h1 class="h4 font-headings fw-normal mb-1">Activar Premium</h1>
        <p class="text-muted small mb-0" style="color: rgba(255,255,255,0.4) !important;">Desbloquea el plan nutricional con IA y asesoría por chat.</p>
    </div>

    <!-- Toggle mensual/anual -->
    <div class="toggle-tipo">
        <a href="pago_premium.php?tipo=mensual" class="<?= $tipo === 'mensual' ? 'activo' : '' ?>">Mensual</a>
        <a href="pago_premium.php?tipo=anual"   class="<?= $tipo === 'anual'   ? 'activo' : '' ?>">Anual — Ahorra 25%</a>
    </div>

    <!-- Resumen -->
    <div class="resumen-pago">
        <div class="plan-nombre">Plan Focus Premium</div>
        <div class="plan-precio">$<?= $monto_fmt ?> COP</div>
        <div class="plan-tipo">
            <?= $tipo === 'anual' ? 'Suscripción anual' : 'Suscripción mensual' ?>
        </div>
    </div>

    <!-- Qué incluye -->
    <div class="mb-4">
        <?php
        $beneficios = [
          "Plan alimenticio personalizado por IA",
          "Menú semanal estructurado por comidas",
          "Metas calóricas y macros a medida",
          "Chat privado interactivo con Nutricionista",
          "Soporte técnico prioritario"
        ];
        foreach ($beneficios as $b):
        ?>
            <div class="benefit-item">
                <i class="fa-solid fa-circle-check text-success"></i> <span><?= $b ?></span>
            </div>
        <?php endforeach; ?>
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
        <input type="hidden" name="test"            value="1">
        <input type="hidden" name="buyerEmail"      value="<?= htmlspecialchars($correo_u) ?>">
        <input type="hidden" name="buyerFullName"   value="<?= htmlspecialchars($nombre_u) ?>">
        <input type="hidden" name="responseUrl"     value="<?= $url_respuesta ?>">
        <input type="hidden" name="confirmationUrl" value="<?= $url_confirmacion ?>">
        <input type="hidden" name="extra1"          value="<?= $usuario_id ?>">
        <input type="hidden" name="extra2"          value="<?= $tipo ?>">

        <button type="submit" class="btn-pagar-payu">
            Proceder al Pago — $<?= $monto_fmt ?> COP
        </button>
    </form>

    <div class="garantia">
        <i class="fa-solid fa-shield-halved text-success"></i>
        <span>Procesamiento encriptado de pago seguro via PayU. Acepta PSE, crédito y débito.</span>
    </div>

    <div class="text-center mt-3">
        <a href="planes.php" class="text-muted small text-decoration-none hover-white"><i class="fa-solid fa-arrow-left me-1"></i> Cancelar y volver</a>
    </div>
</div>

</body>
</html>