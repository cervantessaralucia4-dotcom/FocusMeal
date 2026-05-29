<?php
session_start();
require "conexion.php";

// ── Credenciales admin ──────────────────────────────────
$ADMIN_USER = "admin";
$ADMIN_PASS = "focusmeal_admin2025"; // Cambia esto

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["login_admin"])) {
    if ($_POST["usuario"] === $ADMIN_USER && $_POST["password"] === $ADMIN_PASS) {
        $_SESSION["admin"] = true;
    } else {
        $error_login = "Credenciales incorrectas.";
    }
}
if (isset($_POST["logout_admin"])) unset($_SESSION["admin"]);

$logueado = isset($_SESSION["admin"]);

if ($logueado) {

    // Acciones POST
    if ($_SERVER["REQUEST_METHOD"] === "POST") {

        // Activar / desactivar usuario
        if (isset($_POST["toggle_usuario"])) {
            $uid   = intval($_POST["id_usuario"]);
            $nuevo = intval($_POST["nuevo_estado"]);
            $conn->query("UPDATE usuarios SET activo = $nuevo WHERE id_usuario = $uid");
        }

        // Responder PQRS
        if (isset($_POST["responder_pqrs"])) {
            $id_pqrs   = intval($_POST["id_pqrs"]);
            $respuesta = trim($_POST["respuesta_pqrs"]);
            if ($respuesta) {
                $upd = $conn->prepare("UPDATE pqrs SET respuesta = ?, estado = 'Respondida' WHERE id_pqrs = ?");
                $upd->bind_param("si", $respuesta, $id_pqrs);
                $upd->execute();
            }
        }

        // Crear plan disponible
        if (isset($_POST["crear_plan"])) {
            $np  = trim($_POST["nombre_plan"]);
            $desc = trim($_POST["descripcion"]);
            $cal  = intval($_POST["calorias_diarias"]);
            $td   = trim($_POST["tipo_dieta"]);
            $ins  = $conn->prepare("INSERT INTO planes_disponibles (nombre_plan, descripcion, calorias_diarias, tipo_dieta) VALUES (?,?,?,?)");
            $ins->bind_param("ssis", $np, $desc, $cal, $td);
            $ins->execute();
        }

        // Eliminar plan disponible
        if (isset($_POST["eliminar_plan"])) {
            $pid = intval($_POST["id_plan"]);
            $conn->query("DELETE FROM planes_disponibles WHERE id_plan = $pid");
        }

        header("Location: admin.php?seccion=" . ($_GET["seccion"] ?? "dashboard"));
        exit;
    }

    $seccion = $_GET["seccion"] ?? "dashboard";

    // ── Datos para dashboard ────────────────────────────
    if ($seccion === "dashboard") {
        $total_usuarios    = $conn->query("SELECT COUNT(*) FROM usuarios")->fetch_row()[0];
        $usuarios_premium  = $conn->query("SELECT COUNT(*) FROM usuarios WHERE es_premium = 1")->fetch_row()[0];
        $subs_activas      = $conn->query("SELECT COUNT(*) FROM suscripciones WHERE estado = 'activa' AND fecha_vencimiento >= CURDATE()")->fetch_row()[0];
        $ingresos_mensual  = $conn->query("SELECT COALESCE(SUM(pp.precio_mensual),0) FROM suscripciones s JOIN planes_premium pp ON pp.id_plan_premium = s.id_plan_premium WHERE s.estado='activa' AND s.tipo='mensual'")->fetch_row()[0];
        $ingresos_anual    = $conn->query("SELECT COALESCE(SUM(pp.precio_anual),0) FROM suscripciones s JOIN planes_premium pp ON pp.id_plan_premium = s.id_plan_premium WHERE s.estado='activa' AND s.tipo='anual'")->fetch_row()[0];
        $pqrs_pendientes   = $conn->query("SELECT COUNT(*) FROM pqrs WHERE estado = 'Pendiente'")->fetch_row()[0];
        $nuevos_hoy        = $conn->query("SELECT COUNT(*) FROM usuarios WHERE DATE(fecha_registro) = CURDATE()")->fetch_row()[0];
    }

    // ── Usuarios ────────────────────────────────────────
    if ($seccion === "usuarios") {
        $buscar = trim($_GET["q"] ?? "");
        $sql = "SELECT u.*, CASE WHEN s.id_suscripcion IS NOT NULL THEN 'Premium' ELSE 'Gratis' END as tipo_plan
                FROM usuarios u
                LEFT JOIN suscripciones s ON s.id_usuario = u.id_usuario AND s.estado = 'activa' AND s.fecha_vencimiento >= CURDATE()";
        if ($buscar) $sql .= " WHERE (u.nombre LIKE '%$buscar%' OR u.correo LIKE '%$buscar%')";
        $sql .= " ORDER BY u.fecha_registro DESC LIMIT 100";
        $usuarios = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    // ── Suscripciones ───────────────────────────────────
    if ($seccion === "suscripciones") {
        $subs = $conn->query("
            SELECT s.*, u.nombre, u.correo, pp.nombre as plan_nombre
            FROM suscripciones s
            JOIN usuarios u ON u.id_usuario = s.id_usuario
            JOIN planes_premium pp ON pp.id_plan_premium = s.id_plan_premium
            ORDER BY s.fecha_pago DESC LIMIT 100
        ")->fetch_all(MYSQLI_ASSOC);
    }

    // ── PQRS ────────────────────────────────────────────
    if ($seccion === "pqrs") {
        $filtro = $_GET["filtro"] ?? "todas";
        $sql = "SELECT * FROM pqrs";
        if ($filtro === "pendientes") $sql .= " WHERE estado = 'Pendiente'";
        $sql .= " ORDER BY fecha DESC LIMIT 100";
        $pqrs_list = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    // ── Planes ──────────────────────────────────────────
    if ($seccion === "planes") {
        $planes = $conn->query("SELECT * FROM planes_disponibles ORDER BY calorias_diarias ASC")->fetch_all(MYSQLI_ASSOC);
    }

    // ── Chats ───────────────────────────────────────────
    if ($seccion === "chats") {
        $chats_res = $conn->query("
            SELECT u.id_usuario, u.nombre, u.correo,
                   COUNT(c.id_mensaje) as total_mensajes,
                   MAX(c.fecha_envio) as ultimo_mensaje,
                   COUNT(CASE WHEN c.enviado_por='usuario' AND c.leido=0 THEN 1 END) as sin_leer
            FROM chats c JOIN usuarios u ON u.id_usuario = c.id_usuario
            GROUP BY u.id_usuario ORDER BY sin_leer DESC, ultimo_mensaje DESC
        ")->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Administración — FocusMeal</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Unbounded:wght@200;400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="../css/styles.css">
  <style>
    body {
        background-color: var(--bg);
    }
    .form-control, .form-select {
        background-color: var(--bg);
        border: 1px solid var(--border);
        color: var(--text);
        border-radius: var(--radius-sm);
        padding: 10px 14px;
        font-size: 0.9rem;
    }
    .form-control:focus, .form-select:focus {
        background-color: #fff;
        border-color: var(--green);
        box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1);
        color: var(--text);
    }
    .form-label {
        font-weight: 600;
        font-size: 0.85rem;
        color: var(--text-mid);
        margin-bottom: 6px;
    }
    /* Special Admin Overrides */
    .admin-login-body {
        background-color: var(--navy);
        min-height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
        color: #fff;
    }
    .admin-login-card {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.08);
        backdrop-filter: blur(10px);
        border-radius: var(--radius-lg);
        width: 100%;
        max-width: 420px;
        padding: 40px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    }
    .admin-login-card .form-control {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: #fff;
    }
    .admin-login-card .form-control:focus {
        background: rgba(255, 255, 255, 0.05);
        border-color: var(--green);
        color: #fff;
    }
  </style>
</head>
<body class="<?= !$logueado ? 'admin-login-body' : '' ?>">

<?php if (!$logueado): ?>

  <!-- LOGIN ADMIN -->
  <div class="admin-login-card text-center">
    <div class="mb-4">
        <img src="../img/logo.png" alt="FocusMeal Logo" style="height: 48px;" class="mb-2">
        <h1 class="h4 font-headings fw-normal text-white mb-1">Panel Admin</h1>
        <p class="text-muted small mb-0" style="color: rgba(255,255,255,0.4) !important;">Acceso exclusivo para administradores.</p>
    </div>
    
    <?php if (isset($error_login)): ?>
        <div class="alert alert-danger border-0 small py-2 mb-3" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border-radius: var(--radius-sm);">
            <i class="fa-solid fa-circle-xmark me-1"></i> <?= htmlspecialchars($error_login) ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="text-start">
        <input type="hidden" name="login_admin" value="1">
        
        <div class="mb-3">
            <label class="form-label text-white-50">Usuario</label>
            <input type="text" name="usuario" class="form-control" placeholder="Nombre de usuario" required>
        </div>

        <div class="mb-4">
            <label class="form-label text-white-50">Contraseña</label>
            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2.5 rounded-pill border-0" style="background: var(--green); font-weight: 600;">Ingresar</button>
    </form>
  </div>

<?php else: ?>

<div class="panel-layout">
  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <img src="../img/logo.png" alt="FocusMeal Logo" style="height: 32px;">
            <span>FM Admin</span>
        </div>
    </div>
    <ul class="sidebar-menu">
        <li class="seccion-titulo">General</li>
        <li class="<?= ($seccion === 'dashboard') ? 'active' : '' ?>">
            <a href="admin.php?seccion=dashboard"><i class="fa-solid fa-chart-simple"></i> Dashboard</a>
        </li>
        <li class="seccion-titulo">Gestión</li>
        <li class="<?= ($seccion === 'usuarios') ? 'active' : '' ?>">
            <a href="admin.php?seccion=usuarios"><i class="fa-solid fa-users"></i> Usuarios</a>
        </li>
        <li class="<?= ($seccion === 'suscripciones') ? 'active' : '' ?>">
            <a href="admin.php?seccion=suscripciones"><i class="fa-solid fa-gem"></i> Suscripciones</a>
        </li>
        <li class="<?= ($seccion === 'planes') ? 'active' : '' ?>">
            <a href="admin.php?seccion=planes"><i class="fa-solid fa-heart-pulse"></i> Planes</a>
        </li>
        <li class="seccion-titulo">Atención</li>
        <li class="<?= ($seccion === 'pqrs') ? 'active' : '' ?>">
            <a href="admin.php?seccion=pqrs" class="d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-envelope-open-text"></i> PQRS</span>
                <?php if(isset($pqrs_pendientes) && $pqrs_pendientes > 0): ?>
                    <span class="badge rounded-pill bg-danger" style="font-size: 0.65rem; padding: 3px 6px;"><?= $pqrs_pendientes ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="<?= ($seccion === 'chats') ? 'active' : '' ?>">
            <a href="admin.php?seccion=chats"><i class="fa-solid fa-comments"></i> Chats Clientes</a>
        </li>
    </ul>
    <div class="sidebar-footer">
        <form method="POST" class="m-0">
            <button type="submit" name="logout_admin" class="btn btn-danger w-100 py-2 rounded-pill text-decoration-none" style="font-size: 0.85rem;"><i class="fa-solid fa-right-from-bracket"></i> Salir del Panel</button>
        </form>
    </div>
  </aside>

  <!-- CONTENIDO -->
  <main class="main-content">

    <?php if ($seccion === 'dashboard'): ?>
      <div class="mb-4">
          <h1 class="h3 font-headings mb-1" style="font-family: var(--font-headings); font-weight: 500;">📊 Resumen General</h1>
          <p class="text-muted mb-0" style="font-size: 0.9rem;">Visión de rendimiento y KPIs clave del sistema</p>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-lg">
            <div class="card p-3 border-0 shadow-sm text-center" style="border-radius: var(--radius-md); background: var(--surface);">
                <div class="h2 mb-1" style="color: var(--green); font-family: var(--font-headings); font-weight: 600;"><?= $total_usuarios ?></div>
                <div class="text-muted small text-uppercase fw-semibold" style="font-size: 0.72rem;">Usuarios Totales</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg">
            <div class="card p-3 border-0 shadow-sm text-center" style="border-radius: var(--radius-md); background: var(--navy); color: #fff;">
                <div class="h2 mb-1 text-success" style="font-family: var(--font-headings); font-weight: 600;"><?= $usuarios_premium ?></div>
                <div class="text-white-50 small text-uppercase fw-semibold" style="font-size: 0.72rem;">Clientes Premium</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg">
            <div class="card p-3 border-0 shadow-sm text-center" style="border-radius: var(--radius-md); background: var(--surface);">
                <div class="h2 mb-1" style="color: var(--green); font-family: var(--font-headings); font-weight: 600;"><?= $subs_activas ?></div>
                <div class="text-muted small text-uppercase fw-semibold" style="font-size: 0.72rem;">Suscripciones Activas</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg">
            <div class="card p-3 border-0 shadow-sm text-center" style="border-radius: var(--radius-md); background: var(--surface);">
                <div class="h2 mb-1" style="color: var(--green); font-family: var(--font-headings); font-weight: 600;"><?= $nuevos_hoy ?></div>
                <div class="text-muted small text-uppercase fw-semibold" style="font-size: 0.72rem;">Registros Hoy</div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg">
            <div class="card p-3 border-0 shadow-sm text-center" style="border-radius: var(--radius-md); background: var(--surface);">
                <div class="h2 mb-1 <?= $pqrs_pendientes > 0 ? 'text-danger' : 'text-success' ?>" style="font-family: var(--font-headings); font-weight: 600;"><?= $pqrs_pendientes ?></div>
                <div class="text-muted small text-uppercase fw-semibold" style="font-size: 0.72rem;">PQRS Pendientes</div>
            </div>
        </div>
      </div>

      <div class="card p-4 border-0 shadow-sm mb-4" style="border-radius: var(--radius-lg); background: var(--navy); color: #fff;">
          <h3 class="h6 mb-3 text-white-50 text-uppercase" style="letter-spacing: 1px;"><i class="fa-solid fa-sack-dollar text-success me-2"></i>Facturación Estimada</h3>
          <div class="row g-3">
              <div class="col-md-4">
                  <div class="small text-white-50">Mensual Directo (Planes Mensuales)</div>
                  <div class="h3 font-headings mt-1 text-success fw-bold">$<?= number_format($ingresos_mensual, 0, ',', '.') ?> <span style="font-size:0.9rem; color:rgba(255,255,255,0.4)">COP</span></div>
              </div>
              <div class="col-md-4">
                  <div class="small text-white-50">Anual Acumulado (Planes Anuales)</div>
                  <div class="h3 font-headings mt-1 text-success fw-bold">$<?= number_format($ingresos_anual, 0, ',', '.') ?> <span style="font-size:0.9rem; color:rgba(255,255,255,0.4)">COP</span></div>
              </div>
              <div class="col-md-4">
                  <div class="small text-white-50">Ingreso Mensualizado Estimado</div>
                  <div class="h3 font-headings mt-1 text-success fw-bold">$<?= number_format($ingresos_mensual + ($ingresos_anual / 12), 0, ',', '.') ?> <span style="font-size:0.9rem; color:rgba(255,255,255,0.4)">COP</span></div>
              </div>
          </div>
      </div>

      <div class="card p-4 border-0 shadow-sm" style="border-radius: var(--radius-lg); background: var(--surface);">
          <h3 class="h5 mb-3" style="font-family: var(--font-headings); font-weight: 500; color: var(--navy);"><i class="fa-solid fa-bolt text-success me-2"></i>Acciones Rápidas</h3>
          <div class="d-flex gap-2 flex-wrap">
              <a href="admin.php?seccion=usuarios" class="btn btn-primary px-4 py-2 border-0 rounded-pill" style="font-weight: 600; background: var(--green);">Gestionar Clientes</a>
              <a href="admin.php?seccion=pqrs&filtro=pendientes" class="btn btn-secondary px-4 py-2 rounded-pill border-1" style="font-weight: 600;">Resolver Consultas</a>
              <a href="panel_nutricionista.php" class="btn btn-secondary px-4 py-2 rounded-pill border-1" style="font-weight: 600;" target="_blank">Entrar como Nutricionista <i class="fa-solid fa-arrow-up-right-from-square ms-1 small"></i></a>
          </div>
      </div>

    <?php elseif ($seccion === 'usuarios'): ?>
      <div class="mb-4">
          <h1 class="h3 font-headings mb-1" style="font-family: var(--font-headings); font-weight: 500;">👥 Clientes Registrados</h1>
          <p class="text-muted mb-0" style="font-size: 0.9rem;">Lista y control de cuentas de usuarios de FocusMeal</p>
      </div>

      <form method="GET" action="admin.php" class="row g-2 mb-4">
        <input type="hidden" name="seccion" value="usuarios">
        <div class="col-md-9 col-lg-10">
            <input type="text" name="q" class="form-control mb-0" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" placeholder="Buscar por nombre o correo electrónico...">
        </div>
        <div class="col-md-3 col-lg-2">
            <button type="submit" class="btn btn-primary w-100 py-2.5 rounded-pill border-0" style="background: var(--green); font-weight: 600;"><i class="fa-solid fa-magnifying-glass me-1"></i> Buscar</button>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead>
            <tr>
              <th>Cliente</th>
              <th>Contacto</th>
              <th>Plan</th>
              <th>Objetivo</th>
              <th>Fecha Registro</th>
              <th>Estado</th>
              <th class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($usuarios as $u): ?>
            <tr>
              <td>
                <div class="fw-bold text-dark"><?= htmlspecialchars($u['nombre']) ?></div>
              </td>
              <td><?= htmlspecialchars($u['correo']) ?></td>
              <td>
                <span class="badge <?= $u['tipo_plan'] === 'Premium' ? 'navy' : 'gris' ?>">
                  <?= $u['tipo_plan'] ?>
                </span>
              </td>
              <td><?= htmlspecialchars($u['objetivo'] ?: '—') ?></td>
              <td><?= date("d/m/Y", strtotime($u['fecha_registro'])) ?></td>
              <td>
                <span class="badge <?= ($u['activo'] ?? 1) ? 'verde' : 'rojo' ?>">
                  <?= ($u['activo'] ?? 1) ? 'Activo' : 'Inactivo' ?>
                </span>
              </td>
              <td class="text-end">
                <form method="POST" class="m-0 d-inline-block">
                  <input type="hidden" name="id_usuario" value="<?= $u['id_usuario'] ?>">
                  <input type="hidden" name="nuevo_estado" value="<?= ($u['activo'] ?? 1) ? 0 : 1 ?>">
                  <button type="submit" name="toggle_usuario" class="btn btn-sm px-3 rounded-pill <?= ($u['activo'] ?? 1) ? 'btn-outline-danger' : 'btn-outline-success' ?>" style="font-weight: 600; font-size: 0.78rem;">
                    <?= ($u['activo'] ?? 1) ? '<i class="fa-solid fa-user-slash me-1"></i> Desactivar' : '<i class="fa-solid fa-user-check me-1"></i> Activar' ?>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    <?php elseif ($seccion === 'suscripciones'): ?>
      <div class="mb-4">
          <h1 class="h3 font-headings mb-1" style="font-family: var(--font-headings); font-weight: 500;">⭐ Registro de Suscripciones</h1>
          <p class="text-muted mb-0" style="font-size: 0.9rem;">Historial y estados de transacciones de suscripción</p>
      </div>

      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead>
            <tr>
              <th>Usuario / Correo</th>
              <th>Plan Premium</th>
              <th>Facturación</th>
              <th>Estado</th>
              <th>Inicio</th>
              <th>Vencimiento</th>
              <th>Referencia PayU</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($subs as $s): ?>
            <tr>
              <td>
                <div class="fw-bold text-dark"><?= htmlspecialchars($s['nombre']) ?></div>
                <div class="text-muted small"><?= htmlspecialchars($s['correo']) ?></div>
              </td>
              <td><?= htmlspecialchars($s['plan_nombre']) ?></td>
              <td><span class="badge <?= $s['tipo'] === 'anual' ? 'navy' : 'gris' ?>"><?= ucfirst($s['tipo']) ?></span></td>
              <td>
                <span class="badge <?= $s['estado'] === 'activa' ? 'verde' : ($s['estado'] === 'vencida' ? 'rojo' : 'ambar') ?>">
                  <?= ucfirst($s['estado']) ?>
                </span>
              </td>
              <td><?= date("d/m/Y", strtotime($s['fecha_inicio'])) ?></td>
              <td><?= date("d/m/Y", strtotime($s['fecha_vencimiento'])) ?></td>
              <td class="font-monospace small text-muted"><?= htmlspecialchars($s['referencia_payu'] ?: '—') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    <?php elseif ($seccion === 'planes'): ?>
      <div class="mb-4">
          <h1 class="h3 font-headings mb-1" style="font-family: var(--font-headings); font-weight: 500;">🥗 Planes Nutricionales Disponibles</h1>
          <p class="text-muted mb-0" style="font-size: 0.9rem;">Define y administra los planes estándar recomendados en la app</p>
      </div>

      <!-- Crear nuevo plan -->
      <div class="card p-4 border-0 shadow-sm mb-4" style="border-radius: var(--radius-lg); background: var(--surface);">
        <h3 class="h6 mb-3" style="font-family: var(--font-headings); font-weight: 600;"><i class="fa-solid fa-circle-plus text-success me-2"></i>Agregar Nuevo Plan</h3>
        <form method="POST">
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label">Nombre del plan</label>
              <input type="text" name="nombre_plan" class="form-control mb-0" required placeholder="Ej: Déficit calórico leve">
            </div>
            <div class="col-md-6">
              <label class="form-label">Calorías diarias</label>
              <input type="number" name="calorias_diarias" class="form-control mb-0" required placeholder="1800">
            </div>
            <div class="col-md-6">
              <label class="form-label">Tipo de dieta</label>
              <select name="tipo_dieta" class="form-select mb-0">
                <option value="General">General</option>
                <option value="Vegetariana">Vegetariana</option>
                <option value="Keto">Keto</option>
                <option value="Baja en carbohidratos">Baja en carbohidratos</option>
                <option value="Alta en proteinas">Alta en proteínas</option>
              </select>
            </div>
            <div class="col-md-6 d-flex align-items-end">
                <button type="submit" name="crear_plan" class="btn btn-primary w-100 py-2.5 rounded-pill border-0" style="background: var(--green); font-weight: 600;">Guardar Plan en Catálogo</button>
            </div>
          </div>
          <div class="mb-0">
            <label class="form-label">Descripción</label>
            <textarea name="descripcion" class="form-control mb-0" rows="2" placeholder="Describe brevemente el plan y sus objetivos..."></textarea>
          </div>
        </form>
      </div>

      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead>
            <tr>
              <th>Nombre del Plan</th>
              <th>Descripción</th>
              <th>kcal/día</th>
              <th>Tipo Dieta</th>
              <th class="text-end">Acción</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($planes as $p): ?>
            <tr>
              <td><div class="fw-bold text-dark"><?= htmlspecialchars($p['nombre_plan']) ?></div></td>
              <td class="small text-muted"><?= htmlspecialchars($p['descripcion'] ?? '—') ?></td>
              <td class="fw-semibold text-success"><?= $p['calorias_diarias'] ?> kcal</td>
              <td><span class="badge gris"><?= htmlspecialchars($p['tipo_dieta'] ?? '—') ?></span></td>
              <td class="text-end">
                <form method="POST" class="m-0 d-inline-block" onsubmit="return confirm('¿Eliminar este plan?')">
                  <input type="hidden" name="id_plan" value="<?= $p['id_plan'] ?>">
                  <button type="submit" name="eliminar_plan" class="btn btn-sm btn-outline-danger px-3 rounded-pill" style="font-weight: 600; font-size: 0.78rem;">
                    <i class="fa-solid fa-trash-can"></i> Eliminar
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    <?php elseif ($seccion === 'pqrs'): ?>
      <div class="mb-4">
          <h1 class="h3 font-headings mb-1" style="font-family: var(--font-headings); font-weight: 500;">📩 Consultas y PQRS</h1>
          <p class="text-muted mb-0" style="font-size: 0.9rem;">Gestiona las solicitudes de soporte de los usuarios</p>
      </div>

      <div class="d-flex gap-2 mb-4">
        <a href="admin.php?seccion=pqrs" class="badge <?= ($_GET['filtro'] ?? '') !== 'pendientes' ? 'navy' : 'gris' ?> px-4 py-2.5 text-decoration-none" style="font-size: 0.8rem;">Todas</a>
        <a href="admin.php?seccion=pqrs&filtro=pendientes" class="badge <?= ($_GET['filtro'] ?? '') === 'pendientes' ? 'navy' : 'gris' ?> px-4 py-2.5 text-decoration-none" style="font-size: 0.8rem;">Solo Pendientes</a>
      </div>

      <div class="row g-3">
      <?php foreach ($pqrs_list as $pq): ?>
        <div class="col-12">
            <div class="card p-4 border-0 shadow-sm" style="border-radius: var(--radius-lg); background: var(--surface);">
              <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                <div class="d-flex gap-2 align-items-center">
                    <span class="badge <?= $pq['estado'] === 'Pendiente' ? 'ambar' : 'verde' ?>"><?= $pq['estado'] ?></span>
                    <span class="badge gris"><?= $pq['tipo'] ?></span>
                    <h3 class="h6 mb-0 font-headings" style="font-family: var(--font-headings); font-weight: 600; color: var(--navy);"><?= htmlspecialchars($pq['asunto']) ?></h3>
                </div>
                <span class="small text-muted"><?= date("d/m/Y H:i", strtotime($pq['fecha'])) ?></span>
              </div>
              
              <div class="bg-light p-3 rounded mb-3" style="font-size: 0.88rem;">
                <div class="fw-bold text-dark mb-1"><?= htmlspecialchars($pq['nombre']) ?> <span class="text-muted fw-normal" style="font-size: 0.8rem;">(<?= htmlspecialchars($pq['correo']) ?>)</span></div>
                <div class="text-muted mt-1" style="white-space: pre-wrap;"><?= htmlspecialchars($pq['mensaje']) ?></div>
              </div>

              <?php if ($pq['respuesta']): ?>
                <div class="p-3 border-0" style="border-radius: var(--radius-sm); background: rgba(22, 163, 74, 0.05); color: var(--green-dark); border-left: 4px solid var(--green) !important; font-size: 0.88rem;">
                  <strong class="d-block mb-1"><i class="fa-solid fa-circle-check"></i> Respuesta enviada:</strong>
                  <div style="white-space: pre-wrap;"><?= htmlspecialchars($pq['respuesta']) ?></div>
                </div>
              <?php else: ?>
                <form method="POST" class="mt-2">
                  <input type="hidden" name="id_pqrs" value="<?= $pq['id_pqrs'] ?>">
                  <div class="mb-2">
                      <textarea name="respuesta_pqrs" class="form-control mb-0" placeholder="Escribe la respuesta para enviar al cliente..." required></textarea>
                  </div>
                  <button type="submit" name="responder_pqrs" class="btn btn-primary px-4 py-2 rounded-pill border-0" style="background: var(--green); font-weight: 600;"><i class="fa-solid fa-paper-plane me-1"></i> Enviar Respuesta</button>
                </form>
              <?php endif; ?>
            </div>
        </div>
      <?php endforeach; ?>
      <?php if (empty($pqrs_list)): ?>
        <div class="col-12">
            <div class="card p-5 border-0 shadow-sm text-center text-muted" style="border-radius: var(--radius-lg); background: var(--surface);">
                <i class="fa-solid fa-folder-open mb-2 h2 text-muted"></i>
                <p class="mb-0">No se encontraron solicitudes.</p>
            </div>
        </div>
      <?php endif; ?>
      </div>

    <?php elseif ($seccion === 'chats'): ?>
      <div class="mb-4">
          <h1 class="h3 font-headings mb-1" style="font-family: var(--font-headings); font-weight: 500;">💬 Chats Activos con Nutricionista</h1>
          <p class="text-muted mb-0" style="font-size: 0.9rem;">Bandeja de entrada y asesoramiento nutricional personalizado</p>
      </div>

      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead>
            <tr>
              <th>Cliente</th>
              <th>Correo</th>
              <th>Total Mensajes</th>
              <th>Sin Leer</th>
              <th>Última Actividad</th>
              <th class="text-end">Acción</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($chats_res as $ch): ?>
            <tr>
              <td><div class="fw-bold text-dark"><?= htmlspecialchars($ch['nombre']) ?></div></td>
              <td><?= htmlspecialchars($ch['correo']) ?></td>
              <td><?= $ch['total_mensajes'] ?></td>
              <td>
                <?php if ($ch['sin_leer'] > 0): ?>
                    <span class="badge bg-danger rounded-pill"><?= $ch['sin_leer'] ?></span>
                <?php else: ?>
                    <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td><?= date("d/m H:i", strtotime($ch['ultimo_mensaje'])) ?></td>
              <td class="text-end">
                <a href="panel_nutricionista.php?usuario=<?= $ch['id_usuario'] ?>" target="_blank" class="btn btn-sm btn-outline-primary px-3 rounded-pill" style="font-weight: 600; font-size: 0.78rem;">
                  <i class="fa-solid fa-message me-1"></i> Abrir Chat
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($chats_res)): ?>
            <tr>
              <td colspan="6" class="text-center text-muted py-4">No hay chats registrados.</td>
            </tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>

    <?php endif; ?>

  </main>
</div>

<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>