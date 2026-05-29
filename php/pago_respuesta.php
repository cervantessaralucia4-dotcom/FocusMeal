<?php
/**
 * PayU redirige al usuario aquí después del pago (GET).
 * Mostramos el resultado al usuario.
 */
session_start();

$estado     = $_GET["transactionState"] ?? "";
$referencia = $_GET["referenceCode"]    ?? "";
$monto      = $_GET["TX_VALUE"]         ?? "";

$aprobado = $estado == "4";
$pendiente = $estado == "7";
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Resultado del pago — FocusMeal</title>
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
      align-items: center;
      justify-content: center;
      margin: 0;
      padding: 20px;
    }
    .status-card {
      background: rgba(255, 255, 255, 0.03);
      border: 1px solid rgba(255, 255, 255, 0.08);
      backdrop-filter: blur(10px);
      border-radius: var(--radius-lg);
      padding: 48px 40px;
      text-align: center;
      max-width: 480px;
      width: 100%;
      box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    }
    .status-icon {
      font-size: 3.5rem;
      margin-bottom: 24px;
    }
    .status-icon.success { color: var(--green); }
    .status-icon.warning { color: #f59e0b; }
    .status-icon.danger  { color: #ef4444; }
  </style>
</head>
<body>
  <div class="status-card">
    <?php if ($aprobado): ?>
      <div class="status-icon success"><i class="fa-solid fa-circle-check"></i></div>
      <h2 class="font-headings h4 mb-3">¡Bienvenido a Premium!</h2>
      <p class="text-muted small mb-4" style="color: rgba(255,255,255,0.6) !important; line-height: 1.6;">Tu pago fue aprobado exitosamente. Ya tienes acceso ilimitado al plan alimenticio personalizado por IA y al chat en vivo con tu nutricionista.</p>
      <a href="panel.php" class="btn btn-primary px-4 py-2.5 rounded-pill border-0" style="background: var(--green); font-weight: 600;">Ir a mi Panel de Control</a>
    <?php elseif ($pendiente): ?>
      <div class="status-icon warning"><i class="fa-solid fa-circle-notch fa-spin"></i></div>
      <h2 class="font-headings h4 mb-3">Pago en proceso</h2>
      <p class="text-muted small mb-4" style="color: rgba(255,255,255,0.6) !important; line-height: 1.6;">Tu transacción está siendo verificada. Te habilitaremos los servicios en cuanto recibamos la confirmación. Ref: <strong><?= htmlspecialchars($referencia) ?></strong></p>
      <a href="panel.php" class="btn btn-primary px-4 py-2.5 rounded-pill border-0" style="background: var(--green); font-weight: 600;">Volver al Panel</a>
    <?php else: ?>
      <div class="status-icon danger"><i class="fa-solid fa-circle-xmark"></i></div>
      <h2 class="font-headings h4 mb-3">Pago no completado</h2>
      <p class="text-muted small mb-4" style="color: rgba(255,255,255,0.6) !important; line-height: 1.6;">No pudimos procesar tu solicitud de pago. Por favor, verifica el medio de pago o intenta nuevamente en unos minutos.</p>
      <a href="planes.php" class="btn btn-primary px-4 py-2.5 rounded-pill border-0" style="background: var(--green); font-weight: 600;">Volver a Planes</a>
    <?php endif; ?>
  </div>
</body>
</html>