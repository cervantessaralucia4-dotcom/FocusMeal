<?php
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../html/index.html");
    exit;
}

require "conexion.php";

$nombre  = trim($_POST["nombre"]  ?? "");
$correo  = trim($_POST["correo"]  ?? "");
$tipo    = trim($_POST["tipo"]    ?? "");
$asunto  = trim($_POST["asunto"]  ?? "");
$mensaje = trim($_POST["mensaje"] ?? "");

$errores = [];
if (strlen($nombre) < 2)                                             $errores[] = "Nombre inválido.";
if (!filter_var($correo, FILTER_VALIDATE_EMAIL))                     $errores[] = "Correo inválido.";
if (!in_array($tipo, ["Pregunta","Queja","Reclamo","Sugerencia"]))   $errores[] = "Tipo inválido.";
if (strlen($asunto) < 5)                                             $errores[] = "Asunto muy corto.";
if (strlen($mensaje) < 20)                                           $errores[] = "Mensaje muy corto.";

if (!empty($errores)) {
    $lista = implode('\n', $errores);
    echo "<script>alert('Por favor corrige:\\n\\n$lista'); window.history.back();</script>";
    exit;
}

$ins = $conn->prepare("INSERT INTO pqrs (nombre, correo, tipo, asunto, mensaje) VALUES (?, ?, ?, ?, ?)");
$ins->bind_param("sssss", $nombre, $correo, $tipo, $asunto, $mensaje);
$ins->execute();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Solicitud enviada — Focus Meal</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Unbounded:wght@200;400&display=swap" rel="stylesheet">
  <style>
    body { font-family:'Inter',sans-serif; background:#f8fafb; display:flex; align-items:center; justify-content:center; min-height:100vh; margin:0; }
    .box { background:#fff; border:1px solid #e8edf2; border-radius:18px; padding:48px 40px; text-align:center; max-width:480px; width:90%; }
    .icon { font-size:3rem; margin-bottom:16px; }
    h2 { font-family:'Unbounded',sans-serif; font-weight:400; font-size:1.4rem; color:#071121; margin-bottom:10px; }
    p  { color:#5a6a7a; font-size:0.92rem; line-height:1.7; margin-bottom:24px; }
    a  { display:inline-block; padding:12px 28px; background:#16A34A; color:#fff; border-radius:999px; text-decoration:none; font-weight:600; }
    .detalle { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; padding:14px 18px; margin-bottom:24px; text-align:left; font-size:0.85rem; color:#14532d; }
  </style>
</head>
<body>
  <div class="box">
    <div class="icon">✅</div>
    <h2>¡Solicitud enviada!</h2>
    <p>Hemos recibido tu <strong><?= htmlspecialchars($tipo) ?></strong>. Te responderemos en máximo 48 horas hábiles al correo indicado.</p>
    <div class="detalle">
      <strong>Resumen:</strong><br>
      Tipo: <?= htmlspecialchars($tipo) ?><br>
      Asunto: <?= htmlspecialchars($asunto) ?><br>
      Correo: <?= htmlspecialchars($correo) ?>
    </div>
    <a href="../html/index.html">Volver al inicio</a>
  </div>
</body>
</html>