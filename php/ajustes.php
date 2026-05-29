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
$mensaje    = "";
$tipo_msg   = "";

// Cargar datos actuales
$stmt = $conn->prepare("SELECT nombre, correo, edad, genero, peso_actual, altura, objetivo, tipo_dieta FROM usuarios WHERE id_usuario = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();

// Guardar cambios de perfil
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["accion"])) {

    if ($_POST["accion"] === "perfil") {
        $nombre        = trim($_POST["nombre"] ?? "");
        $correo        = trim($_POST["correo"] ?? "");
        $edad          = intval($_POST["edad"] ?? 0);
        $genero        = $_POST["genero"] ?? "";
        $peso_actual   = floatval($_POST["peso_actual"] ?? 0);
        $altura        = floatval($_POST["altura"] ?? 0);
        $objetivo      = $_POST["objetivo"] ?? "";
        $tipo_dieta    = $_POST["tipo_dieta"] ?? "";

        if (!$nombre || !$correo) {
            $mensaje  = "El nombre y correo son obligatorios.";
            $tipo_msg = "error";
        } else {
            // Verificar que el correo no lo use otro usuario
            $chk = $conn->prepare("SELECT id_usuario FROM usuarios WHERE correo = ? AND id_usuario != ?");
            $chk->bind_param("si", $correo, $usuario_id);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $mensaje  = "Ese correo ya está registrado por otro usuario.";
                $tipo_msg = "error";
            } else {
                $upd = $conn->prepare("
                    UPDATE usuarios
                    SET nombre = ?, correo = ?, edad = ?, genero = ?, peso_actual = ?, altura = ?, objetivo = ?, tipo_dieta = ?
                    WHERE id_usuario = ?
                ");
                $upd->bind_param("ssiiddssi", $nombre, $correo, $edad, $genero, $peso_actual, $altura, $objetivo, $tipo_dieta, $usuario_id);

                if ($upd->execute()) {
                    // Actualizar nombre en sesión
                    $_SESSION["usuario"]["nombre"] = $nombre;
                    $_SESSION["usuario"]["correo"] = $correo;
                    $mensaje  = "Perfil actualizado correctamente.";
                    $tipo_msg = "exito";
                    // Recargar datos
                    $usuario["nombre"]      = $nombre;
                    $usuario["correo"]      = $correo;
                    $usuario["edad"]        = $edad;
                    $usuario["genero"]      = $genero;
                    $usuario["peso_actual"] = $peso_actual;
                    $usuario["altura"]      = $altura;
                    $usuario["objetivo"]    = $objetivo;
                    $usuario["tipo_dieta"]  = $tipo_dieta;
                } else {
                    $mensaje  = "Error al actualizar: " . $upd->error;
                    $tipo_msg = "error";
                }
            }
        }
    }

    if ($_POST["accion"] === "contrasena") {
        $actual    = $_POST["contrasena_actual"] ?? "";
        $nueva     = $_POST["contrasena_nueva"] ?? "";
        $confirmar = $_POST["contrasena_confirmar"] ?? "";

        // Obtener contraseña actual de la BD
        $q = $conn->prepare("SELECT contraseña FROM usuarios WHERE id_usuario = ?");
        $q->bind_param("i", $usuario_id);
        $q->execute();
        $fila = $q->get_result()->fetch_assoc();

        if (!password_verify($actual, $fila["contraseña"])) {
            $mensaje  = "La contraseña actual es incorrecta.";
            $tipo_msg = "error";
        } elseif (strlen($nueva) < 8) {
            $mensaje  = "La nueva contraseña debe tener al menos 8 caracteres.";
            $tipo_msg = "error";
        } elseif ($nueva !== $confirmar) {
            $mensaje  = "Las contraseñas nuevas no coinciden.";
            $tipo_msg = "error";
        } else {
            $hash = password_hash($nueva, PASSWORD_DEFAULT);
            $upd  = $conn->prepare("UPDATE usuarios SET contraseña = ? WHERE id_usuario = ?");
            $upd->bind_param("si", $hash, $usuario_id);
            if ($upd->execute()) {
                $mensaje  = "Contraseña actualizada correctamente.";
                $tipo_msg = "exito";
            } else {
                $mensaje  = "Error al cambiar la contraseña.";
                $tipo_msg = "error";
            }
        }
    }
}
$active_page = 'ajustes';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ajustes — FocusMeal</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Unbounded:wght@200;400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="../css/styles.css">
  <style>
    .form-control, .form-select {
        background-color: var(--background);
        border: 1px solid var(--border);
        color: var(--text-dark);
        border-radius: var(--radius-sm);
        padding: 10px 14px;
        font-size: 0.9rem;
    }
    .form-control:focus, .form-select:focus {
        background-color: var(--surface);
        border-color: var(--green);
        box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1);
        color: var(--text-dark);
    }
    .form-label {
        font-weight: 600;
        font-size: 0.85rem;
        color: var(--text-mid);
        margin-bottom: 6px;
    }
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
                <h1 class="h3 font-headings mb-1" style="font-family: var(--font-headings); font-weight: 500;">⚙ Ajustes</h1>
                <p class="text-muted mb-0" style="font-size: 0.9rem;">Gestiona la configuración y parámetros de tu cuenta</p>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div class="alert <?= $tipo_msg === 'exito' ? 'alert-success' : 'alert-danger' ?> alert-dismissible fade show mb-4 border-0 shadow-sm" role="alert" style="border-radius: var(--radius-md);">
                <strong><?= $tipo_msg === 'exito' ? '✅' : '❌' ?></strong> <?= htmlspecialchars($mensaje) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- DATOS DEL PERFIL -->
            <div class="col-lg-8">
                <div class="card p-4 border-0 shadow-sm" style="border-radius: var(--radius-lg); background: var(--surface);">
                    <h3 class="h5 mb-4" style="font-family: var(--font-headings); font-weight: 500; color: var(--navy);"><i class="fa-solid fa-user-gear me-2 text-success"></i>Datos Personales y Metas</h3>
                    
                    <form method="POST" action="ajustes.php">
                        <input type="hidden" name="accion" value="perfil">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nombre completo</label>
                                <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($usuario['nombre']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Correo electrónico</label>
                                <input type="email" name="correo" class="form-control" value="<?= htmlspecialchars($usuario['correo']) ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Edad</label>
                                <input type="number" name="edad" class="form-control" min="1" max="120" value="<?= $usuario['edad'] ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Género</label>
                                <select name="genero" class="form-select">
                                    <option value="">Seleccionar</option>
                                    <option value="Masculino"  <?= $usuario['genero'] === 'Masculino'  ? 'selected' : '' ?>>Masculino</option>
                                    <option value="Femenino"   <?= $usuario['genero'] === 'Femenino'   ? 'selected' : '' ?>>Femenino</option>
                                    <option value="Otro"       <?= $usuario['genero'] === 'Otro'       ? 'selected' : '' ?>>Otro</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Peso actual (kg)</label>
                                <input type="number" name="peso_actual" class="form-control" step="0.1" min="1" value="<?= $usuario['peso_actual'] ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Altura (cm)</label>
                                <input type="number" name="altura" class="form-control" step="0.1" min="1" value="<?= $usuario['altura'] ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Objetivo</label>
                                <select name="objetivo" class="form-select">
                                    <option value="">Seleccionar</option>
                                    <option value="Bajar de peso"  <?= $usuario['objetivo'] === 'Bajar de peso'  ? 'selected' : '' ?>>Bajar de peso</option>
                                    <option value="Mantener peso"  <?= $usuario['objetivo'] === 'Mantener peso'  ? 'selected' : '' ?>>Mantener peso</option>
                                    <option value="Aumentar masa"  <?= $usuario['objetivo'] === 'Aumentar masa'  ? 'selected' : '' ?>>Aumentar masa</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tipo de dieta</label>
                                <select name="tipo_dieta" class="form-select">
                                    <option value="">Seleccionar</option>
                                    <option value="General"               <?= $usuario['tipo_dieta'] === 'General'               ? 'selected' : '' ?>>General</option>
                                    <option value="Vegetariana"            <?= $usuario['tipo_dieta'] === 'Vegetariana'            ? 'selected' : '' ?>>Vegetariana</option>
                                    <option value="Keto"                   <?= $usuario['tipo_dieta'] === 'Keto'                   ? 'selected' : '' ?>>Keto</option>
                                    <option value="Baja en carbohidratos"  <?= $usuario['tipo_dieta'] === 'Baja en carbohidratos'  ? 'selected' : '' ?>>Baja en carbohidratos</option>
                                    <option value="Alta en proteínas"      <?= $usuario['tipo_dieta'] === 'Alta en proteínas'      ? 'selected' : '' ?>>Alta en proteínas</option>
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary px-4 py-2.5 rounded-pill mt-4 border-0" style="background: var(--green); font-weight: 600;">Guardar Cambios</button>
                    </form>
                </div>
            </div>

            <!-- CAMBIO DE CONTRASEÑA -->
            <div class="col-lg-4">
                <div class="card p-4 border-0 shadow-sm mb-4" style="border-radius: var(--radius-lg); background: var(--surface);">
                    <h3 class="h5 mb-4" style="font-family: var(--font-headings); font-weight: 500; color: var(--navy);"><i class="fa-solid fa-key me-2 text-success"></i>Seguridad</h3>
                    
                    <form method="POST" action="ajustes.php">
                        <input type="hidden" name="accion" value="contrasena">

                        <div class="mb-3">
                            <label class="form-label">Contraseña actual</label>
                            <input type="password" name="contrasena_actual" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nueva contraseña</label>
                            <input type="password" name="contrasena_nueva" class="form-control" minlength="8" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Confirmar contraseña</label>
                            <input type="password" name="contrasena_confirmar" class="form-control" required>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2.5 rounded-pill border-0" style="background: var(--green); font-weight: 600;">Cambiar Contraseña</button>
                    </form>
                </div>

                <div class="card p-4 border-0 shadow-sm" style="border-radius: var(--radius-lg); background: var(--surface);">
                    <h3 class="h6 mb-2" style="font-family: var(--font-headings); font-weight: 500; color: var(--navy);">Información de la Cuenta</h3>
                    <p class="small text-muted mb-0">
                        <strong>Miembro desde:</strong> 
                        <?php
                          $q2 = $conn->prepare("SELECT fecha_registro FROM usuarios WHERE id_usuario = ?");
                          $q2->bind_param("i", $usuario_id);
                          $q2->execute();
                          $fecha = $q2->get_result()->fetch_assoc()["fecha_registro"];
                          echo date("d/m/Y", strtotime($fecha));
                        ?>
                    </p>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>