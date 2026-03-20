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
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Unbounded:wght@200;400&display=swap" rel="stylesheet">
  <style>
    body { font-family:'Inter',sans-serif; background:#f8fafb; display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; }
    .box { background:#fff; border:1px solid #e8edf2; border-radius:18px; padding:48px 40px; text-align:center; max-width:480px; width:90%; }
    .icon { font-size:3rem; margin-bottom:16px; }
    h2 { font-family:'Unbounded',sans-serif; font-weight:400; font-size:1.4rem; color:#071121; margin-bottom:10px; }
    p  { color:#5a6a7a; font-size:0.92rem; line-height:1.7; margin-bottom:24px; }
    a  { display:inline-block; padding:12px 28px; background:#16A34A; color:#fff; border-radius:999px; text-decoration:none; font-weight:600; font-size:0.9rem; }
  </style>
</head>
<body>
  <div class="box">
    <?php if ($aprobado): ?>
      <div class="icon">🎉</div>
      <h2>¡Bienvenido a Premium!</h2>
      <p>Tu pago fue aprobado. Ya tienes acceso al plan alimenticio personalizado por IA y al chat con nutricionista.</p>
      <a href="panel.php">Ir a mi panel</a>
    <?php elseif ($pendiente): ?>
      <div class="icon">⏳</div>
      <h2>Pago en proceso</h2>
      <p>Tu pago está siendo procesado. Te notificaremos cuando se confirme. Ref: <strong><?= htmlspecialchars($referencia) ?></strong></p>
      <a href="panel.php">Volver al panel</a>
    <?php else: ?>
      <div class="icon">❌</div>
      <h2>Pago no completado</h2>
      <p>El pago no pudo procesarse. Puedes intentarlo de nuevo o contactarnos si el problema persiste.</p>
      <a href="planes.php">Volver a planes</a>
    <?php endif; ?>
  </div>
</body>
</html>