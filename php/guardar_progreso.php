<?php
session_start();
require "conexion.php";

if (!isset($_SESSION["usuario"])) {
    header("Location: ../html/login.html");
    exit;
}

$usuario_id = $_SESSION["usuario"]["id"];
$error = "";
$exito = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $peso              = floatval($_POST["peso"] ?? 0);
    $calorias_consumidas = intval($_POST["calorias_consumidas"] ?? 0);
    $observaciones     = trim($_POST["observaciones"] ?? "");

    if ($peso <= 0) {
        $error = "El peso debe ser mayor a 0.";
    } else {
        // Verificar si ya existe registro de hoy
        $chk = $conn->prepare("SELECT id_hsitoial FROM historial_progreso WHERE id_usuario = ? AND fecha = CURDATE()");
        $chk->bind_param("i", $usuario_id);
        $chk->execute();
        $existe = $chk->get_result()->fetch_assoc();

        if ($existe) {
            // Actualizar el registro de hoy
            $stmt = $conn->prepare("UPDATE historial_progreso SET peso = ?, calorias_consumidas = ?, observaciones = ? WHERE id_usuario = ? AND fecha = CURDATE()");
            $stmt->bind_param("disi", $peso, $calorias_consumidas, $observaciones, $usuario_id);
        } else {
            // Insertar nuevo registro
            $stmt = $conn->prepare("INSERT INTO historial_progreso (id_usuario, fecha, peso, calorias_consumidas, observaciones) VALUES (?, CURDATE(), ?, ?, ?)");
            $stmt->bind_param("idis", $usuario_id, $peso, $calorias_consumidas, $observaciones);
        }

        if ($stmt->execute()) {
            $exito = true;
        } else {
            $error = "Error al guardar: " . $stmt->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Progreso - FocusMeal</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/dashboard.css">
</head>
<body>

<div class="panel-header">
    <div class="logo-container">
        <img src="../img/logo.png" alt="FocusMeal Logo">
        <span>Focus Meal</span>
    </div>
    <a href="logout.php" class="btn-danger">Cerrar sesión</a>
</div>

<div class="panel-container">
    <h1>📝 Registrar progreso de hoy</h1>

    <?php if ($exito): ?>
        <div class="card" style="border-left: 4px solid #1DB954;">
            <p>✅ Progreso guardado correctamente.</p>
            <a href="progreso.php" class="btn-primary">Ver mi progreso</a>
        </div>
    <?php else: ?>

        <?php if ($error): ?>
            <div class="card" style="border-left: 4px solid #dc3545;">
                <p>❌ <?= htmlspecialchars($error) ?></p>
            </div>
        <?php endif; ?>

        <div class="card">
            <form method="POST" action="guardar_progreso.php">
                <div style="margin-bottom:16px">
                    <label><strong>Peso actual (kg)</strong></label><br>
                    <input type="number" name="peso" step="0.1" min="1" max="300" required
                           style="padding:8px;border-radius:6px;border:1px solid #ccc;width:200px">
                </div>
                <div style="margin-bottom:16px">
                    <label><strong>Calorías consumidas hoy (kcal)</strong></label><br>
                    <input type="number" name="calorias_consumidas" min="0" max="10000"
                           style="padding:8px;border-radius:6px;border:1px solid #ccc;width:200px">
                </div>
                <div style="margin-bottom:16px">
                    <label><strong>Observaciones</strong> <span style="font-weight:400">(opcional)</span></label><br>
                    <textarea name="observaciones" rows="3"
                              style="padding:8px;border-radius:6px;border:1px solid #ccc;width:100%;max-width:400px"></textarea>
                </div>
                <button type="submit" class="btn-primary">Guardar</button>
            </form>
        </div>

    <?php endif; ?>

    <br>
    <a href="panel.php">← Volver al panel</a>
</div>
</body>
</html>