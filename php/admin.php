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
        if ($buscar) $sql .= " WHERE u.nombre LIKE '%$buscar%' OR u.correo LIKE '%$buscar%'";
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
  <title>Admin — FocusMeal</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Unbounded:wght@200;400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/dashboard.css">
  <style>
    .admin-layout { display:flex; min-height:calc(100vh - 64px); }

    /* Sidebar */
    .admin-sidebar {
      width: 220px; flex-shrink:0;
      background: var(--navy); padding: 24px 0;
      display: flex; flex-direction: column; gap: 4px;
    }
    .admin-sidebar a {
      display: flex; align-items: center; gap: 10px;
      padding: 11px 22px; color: rgba(255,255,255,0.65);
      text-decoration: none; font-size: 0.88rem; font-weight: 500;
      transition: background 0.15s, color 0.15s; border-left: 3px solid transparent;
    }
    .admin-sidebar a:hover { background: rgba(255,255,255,0.07); color: #fff; }
    .admin-sidebar a.activo { background: rgba(22,163,74,0.15); color: #fff; border-left-color: var(--green); }
    .admin-sidebar .seccion-titulo { padding: 16px 22px 6px; font-size: 0.7rem; font-weight: 700; color: rgba(255,255,255,0.3); text-transform: uppercase; letter-spacing: 1px; }

    /* Contenido */
    .admin-content { flex:1; padding: 32px; overflow-y: auto; }
    .admin-content h1 { font-family:'Unbounded',sans-serif; font-weight:400; font-size:1.4rem; color:var(--navy); margin-bottom:24px; }

    /* Stat cards dashboard */
    .stat-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px,1fr)); gap:16px; margin-bottom:28px; }
    .stat-card { background:var(--surface); border:1.5px solid var(--border); border-radius:14px; padding:20px; }
    .stat-card .num { font-family:'Unbounded',sans-serif; font-size:1.8rem; font-weight:600; color:var(--green); }
    .stat-card .lbl { font-size:0.78rem; color:var(--text-light); margin-top:4px; }
    .stat-card.navy { background:var(--navy); border-color:var(--navy); }
    .stat-card.navy .num { color:#4ade80; }
    .stat-card.navy .lbl { color:rgba(255,255,255,0.5); }

    /* Buscador */
    .buscador { display:flex; gap:10px; margin-bottom:20px; }
    .buscador input { flex:1; padding:9px 14px; border:1.5px solid var(--border); border-radius:8px; font-family:'Inter',sans-serif; font-size:0.88rem; }
    .buscador button { padding:9px 18px; background:var(--navy); color:#fff; border:none; border-radius:8px; font-family:'Inter',sans-serif; font-size:0.88rem; font-weight:600; cursor:pointer; }

    /* Badge */
    .badge { display:inline-block; font-size:0.72rem; font-weight:700; padding:2px 9px; border-radius:999px; }
    .badge.verde   { background:#dcfce7; color:#14532d; }
    .badge.gris    { background:#f3f4f6; color:#374151; }
    .badge.rojo    { background:#fee2e2; color:#991b1b; }
    .badge.ambar   { background:#fef3c7; color:#92400e; }
    .badge.navy    { background:var(--navy); color:#fff; }

    /* Tabla admin */
    table.admin-table { width:100%; border-collapse:collapse; font-size:0.85rem; }
    table.admin-table th { background:var(--navy); color:#fff; padding:10px 14px; text-align:left; font-weight:600; font-size:0.8rem; }
    table.admin-table th:first-child { border-radius:8px 0 0 0; }
    table.admin-table th:last-child  { border-radius:0 8px 0 0; }
    table.admin-table td { padding:10px 14px; border-bottom:1px solid var(--border); color:var(--text-mid); vertical-align:middle; }
    table.admin-table tr:last-child td { border-bottom:none; }
    table.admin-table tr:hover td { background:#f8faff; }

    /* Botones tabla */
    .btn-tbl { padding:5px 12px; border:none; border-radius:6px; font-family:'Inter',sans-serif; font-size:0.78rem; font-weight:600; cursor:pointer; }
    .btn-tbl.red   { background:#fee2e2; color:#991b1b; }
    .btn-tbl.green { background:#dcfce7; color:#14532d; }
    .btn-tbl.navy  { background:var(--navy); color:#fff; }

    /* PQRS expand */
    .pqrs-mensaje { font-size:0.82rem; color:var(--text-light); max-width:320px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .respuesta-inline { margin-top:8px; }
    .respuesta-inline textarea { width:100%; padding:8px 12px; border:1.5px solid var(--border); border-radius:8px; font-family:'Inter',sans-serif; font-size:0.85rem; resize:vertical; min-height:70px; }

    /* Form plan */
    .form-plan { background:var(--bg); border:1.5px solid var(--border); border-radius:12px; padding:20px; margin-bottom:24px; }
    .form-plan .row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
    .form-plan label { font-size:0.78rem; font-weight:600; color:var(--text-light); text-transform:uppercase; letter-spacing:0.4px; display:block; margin-bottom:4px; }
    .form-plan input, .form-plan select, .form-plan textarea { width:100%; padding:9px 12px; border:1.5px solid var(--border); border-radius:8px; font-family:'Inter',sans-serif; font-size:0.88rem; margin-bottom:12px; }

    .ingresos-box { background:var(--navy); border-radius:14px; padding:24px; color:#fff; margin-bottom:24px; display:flex; gap:32px; flex-wrap:wrap; }
    .ingreso-item strong { display:block; font-size:1.6rem; color:#4ade80; font-family:'Unbounded',sans-serif; font-weight:600; }
    .ingreso-item span   { font-size:0.8rem; color:rgba(255,255,255,0.5); }

    @media(max-width:768px) {
      .admin-sidebar { display:none; }
      .admin-content { padding: 20px 16px; }
    }
  </style>
</head>
<body>

<div class="panel-header">
  <div class="logo-container">
    <img src="../img/logo.png" alt="FocusMeal">
    <span>Focus Meal</span>
  </div>
  <?php if ($logueado): ?>
    <div style="display:flex; align-items:center; gap:12px;">
      <span style="color:rgba(255,255,255,0.6); font-size:0.82rem;">Panel Admin</span>
      <form method="POST" style="margin:0">
        <button name="logout_admin" class="btn-danger">Cerrar sesión</button>
      </form>
    </div>
  <?php endif; ?>
</div>

<?php if (!$logueado): ?>

  <!-- LOGIN ADMIN -->
  <div style="max-width:400px; margin:80px auto; padding:0 24px;">
    <div class="card">
      <h2 style="margin-bottom:6px">🔐 Panel Administración</h2>
      <p style="color:var(--text-light); font-size:0.88rem; margin-bottom:20px">Acceso exclusivo para administradores.</p>
      <?php if (isset($error_login)): ?>
        <p><strong>❌ <?= htmlspecialchars($error_login) ?></strong></p>
      <?php endif; ?>
      <form method="POST">
        <label>Usuario</label>
        <input type="text" name="usuario" required placeholder="admin">
        <label>Contraseña</label>
        <input type="password" name="password" required>
        <br>
        <button type="submit" name="login_admin" class="btn-primary" style="width:100%">Ingresar</button>
      </form>
    </div>
  </div>

<?php else: ?>

<div class="admin-layout">

  <!-- SIDEBAR -->
  <div class="admin-sidebar">
    <div class="seccion-titulo">General</div>
    <a href="admin.php?seccion=dashboard" class="<?= $seccion==='dashboard' ? 'activo' : '' ?>">📊 Dashboard</a>
    <div class="seccion-titulo">Gestión</div>
    <a href="admin.php?seccion=usuarios"       class="<?= $seccion==='usuarios'       ? 'activo' : '' ?>">👥 Usuarios</a>
    <a href="admin.php?seccion=suscripciones"  class="<?= $seccion==='suscripciones'  ? 'activo' : '' ?>">⭐ Suscripciones</a>
    <a href="admin.php?seccion=planes"         class="<?= $seccion==='planes'         ? 'activo' : '' ?>">🥗 Planes</a>
    <div class="seccion-titulo">Atención</div>
    <a href="admin.php?seccion=pqrs"  class="<?= $seccion==='pqrs'  ? 'activo' : '' ?>">
      📩 PQRS
      <?php if(isset($pqrs_pendientes) && $pqrs_pendientes > 0): ?>
        <span class="badge rojo" style="margin-left:auto"><?= $pqrs_pendientes ?></span>
      <?php endif; ?>
    </a>
    <a href="admin.php?seccion=chats" class="<?= $seccion==='chats' ? 'activo' : '' ?>">💬 Chats</a>
  </div>

  <!-- CONTENIDO -->
  <div class="admin-content">

    <?php if ($seccion === 'dashboard'): ?>
      <h1>Dashboard</h1>

      <div class="stat-grid">
        <div class="stat-card"><div class="num"><?= $total_usuarios ?></div><div class="lbl">Usuarios totales</div></div>
        <div class="stat-card navy"><div class="num"><?= $usuarios_premium ?></div><div class="lbl">Usuarios premium</div></div>
        <div class="stat-card"><div class="num"><?= $subs_activas ?></div><div class="lbl">Suscripciones activas</div></div>
        <div class="stat-card"><div class="num"><?= $nuevos_hoy ?></div><div class="lbl">Registros hoy</div></div>
        <div class="stat-card navy"><div class="num"><?= $pqrs_pendientes ?></div><div class="lbl">PQRS pendientes</div></div>
      </div>

      <div class="ingresos-box">
        <div class="ingreso-item">
          <strong>$<?= number_format($ingresos_mensual, 0, ',', '.') ?></strong>
          <span>Ingresos mensuales (subs mensuales)</span>
        </div>
        <div class="ingreso-item">
          <strong>$<?= number_format($ingresos_anual, 0, ',', '.') ?></strong>
          <span>Ingresos (subs anuales acumuladas)</span>
        </div>
        <div class="ingreso-item">
          <strong>$<?= number_format($ingresos_mensual + ($ingresos_anual / 12), 0, ',', '.') ?></strong>
          <span>Ingreso mensual estimado total</span>
        </div>
      </div>

      <div class="card">
        <h3>Accesos rápidos</h3>
        <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:8px">
          <a href="admin.php?seccion=usuarios" class="btn-primary">Ver usuarios</a>
          <a href="admin.php?seccion=pqrs&filtro=pendientes" class="btn-primary" style="background:var(--navy); border-color:var(--navy)">Ver PQRS pendientes</a>
          <a href="panel_nutricionista.php" class="btn-primary" style="background:var(--navy); border-color:var(--navy)" target="_blank">Panel nutricionista</a>
        </div>
      </div>

    <?php elseif ($seccion === 'usuarios'): ?>
      <h1>Usuarios (<?= count($usuarios) ?>)</h1>

      <form method="GET" action="admin.php" class="buscador">
        <input type="hidden" name="seccion" value="usuarios">
        <input type="text" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" placeholder="Buscar por nombre o correo...">
        <button type="submit">Buscar</button>
      </form>

      <div class="card" style="padding:0; overflow:hidden">
        <table class="admin-table">
          <thead><tr>
            <th>Nombre</th><th>Correo</th><th>Plan</th><th>Objetivo</th><th>Registro</th><th>Estado</th><th>Acción</th>
          </tr></thead>
          <tbody>
          <?php foreach ($usuarios as $u): ?>
            <tr>
              <td><strong><?= htmlspecialchars($u['nombre']) ?></strong></td>
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
              <td>
                <form method="POST" style="margin:0">
                  <input type="hidden" name="id_usuario" value="<?= $u['id_usuario'] ?>">
                  <input type="hidden" name="nuevo_estado" value="<?= ($u['activo'] ?? 1) ? 0 : 1 ?>">
                  <button type="submit" name="toggle_usuario" class="btn-tbl <?= ($u['activo'] ?? 1) ? 'red' : 'green' ?>">
                    <?= ($u['activo'] ?? 1) ? 'Desactivar' : 'Activar' ?>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    <?php elseif ($seccion === 'suscripciones'): ?>
      <h1>Suscripciones</h1>
      <div class="card" style="padding:0; overflow:hidden">
        <table class="admin-table">
          <thead><tr>
            <th>Usuario</th><th>Plan</th><th>Tipo</th><th>Estado</th><th>Inicio</th><th>Vence</th><th>Referencia</th>
          </tr></thead>
          <tbody>
          <?php foreach ($subs as $s): ?>
            <tr>
              <td>
                <strong><?= htmlspecialchars($s['nombre']) ?></strong><br>
                <small style="color:var(--text-light)"><?= htmlspecialchars($s['correo']) ?></small>
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
              <td style="font-size:0.75rem; color:var(--text-light)"><?= htmlspecialchars($s['referencia_payu'] ?: '—') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    <?php elseif ($seccion === 'planes'): ?>
      <h1>Planes disponibles</h1>

      <!-- Crear nuevo plan -->
      <div class="form-plan">
        <h3 style="margin-bottom:14px">+ Agregar plan</h3>
        <form method="POST">
          <div class="row">
            <div>
              <label>Nombre del plan</label>
              <input type="text" name="nombre_plan" required placeholder="Ej: Déficit calórico leve">
            </div>
            <div>
              <label>Calorías diarias</label>
              <input type="number" name="calorias_diarias" required placeholder="1800">
            </div>
          </div>
          <label>Descripción</label>
          <textarea name="descripcion" rows="2" placeholder="Breve descripción del plan..."></textarea>
          <label>Tipo de dieta</label>
          <select name="tipo_dieta">
            <option value="General">General</option>
            <option value="Vegetariana">Vegetariana</option>
            <option value="Keto">Keto</option>
            <option value="Baja en carbohidratos">Baja en carbohidratos</option>
            <option value="Alta en proteinas">Alta en proteínas</option>
          </select>
          <button type="submit" name="crear_plan" class="btn-primary">Guardar plan</button>
        </form>
      </div>

      <div class="card" style="padding:0; overflow:hidden">
        <table class="admin-table">
          <thead><tr><th>Nombre</th><th>Descripción</th><th>kcal/día</th><th>Tipo dieta</th><th>Acción</th></tr></thead>
          <tbody>
          <?php foreach ($planes as $p): ?>
            <tr>
              <td><strong><?= htmlspecialchars($p['nombre_plan']) ?></strong></td>
              <td style="font-size:0.82rem; color:var(--text-light)"><?= htmlspecialchars($p['descripcion'] ?? '—') ?></td>
              <td><?= $p['calorias_diarias'] ?></td>
              <td><span class="badge gris"><?= htmlspecialchars($p['tipo_dieta'] ?? '—') ?></span></td>
              <td>
                <form method="POST" onsubmit="return confirm('¿Eliminar este plan?')">
                  <input type="hidden" name="id_plan" value="<?= $p['id_plan'] ?>">
                  <button type="submit" name="eliminar_plan" class="btn-tbl red">Eliminar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    <?php elseif ($seccion === 'pqrs'): ?>
      <h1>PQRS</h1>

      <div style="display:flex; gap:8px; margin-bottom:16px">
        <a href="admin.php?seccion=pqrs" class="badge <?= ($_GET['filtro'] ?? '') !== 'pendientes' ? 'navy' : 'gris' ?>" style="padding:6px 14px; text-decoration:none">Todas</a>
        <a href="admin.php?seccion=pqrs&filtro=pendientes" class="badge <?= ($_GET['filtro'] ?? '') === 'pendientes' ? 'navy' : 'gris' ?>" style="padding:6px 14px; text-decoration:none">Solo pendientes</a>
      </div>

      <div style="display:flex; flex-direction:column; gap:14px">
      <?php foreach ($pqrs_list as $pq): ?>
        <div class="card" style="padding:20px">
          <div style="display:flex; align-items:flex-start; gap:12px; flex-wrap:wrap">
            <span class="badge <?= $pq['estado'] === 'Pendiente' ? 'ambar' : 'verde' ?>"><?= $pq['estado'] ?></span>
            <span class="badge gris"><?= $pq['tipo'] ?></span>
            <strong style="font-size:0.9rem"><?= htmlspecialchars($pq['asunto']) ?></strong>
            <span style="margin-left:auto; font-size:0.78rem; color:var(--text-light)"><?= date("d/m/Y H:i", strtotime($pq['fecha'])) ?></span>
          </div>
          <p style="font-size:0.85rem; margin:10px 0 4px"><strong><?= htmlspecialchars($pq['nombre']) ?></strong> — <?= htmlspecialchars($pq['correo']) ?></p>
          <p style="font-size:0.85rem; color:var(--text-mid); margin-bottom:10px"><?= nl2br(htmlspecialchars($pq['mensaje'])) ?></p>

          <?php if ($pq['respuesta']): ?>
            <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:12px 14px; font-size:0.85rem; color:#14532d">
              <strong>Respuesta enviada:</strong><br><?= nl2br(htmlspecialchars($pq['respuesta'])) ?>
            </div>
          <?php else: ?>
            <form method="POST" class="respuesta-inline">
              <input type="hidden" name="id_pqrs" value="<?= $pq['id_pqrs'] ?>">
              <textarea name="respuesta_pqrs" placeholder="Escribe la respuesta..." required></textarea>
              <button type="submit" name="responder_pqrs" class="btn-primary" style="margin-top:8px">Responder</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if (empty($pqrs_list)): ?>
        <div class="card" style="text-align:center; color:var(--text-light)">No hay solicitudes.</div>
      <?php endif; ?>
      </div>

    <?php elseif ($seccion === 'chats'): ?>
      <h1>Chats con nutricionista</h1>
      <div class="card" style="padding:0; overflow:hidden">
        <table class="admin-table">
          <thead><tr><th>Usuario</th><th>Correo</th><th>Mensajes</th><th>Sin leer</th><th>Último mensaje</th><th>Acción</th></tr></thead>
          <tbody>
          <?php foreach ($chats_res as $ch): ?>
            <tr>
              <td><strong><?= htmlspecialchars($ch['nombre']) ?></strong></td>
              <td><?= htmlspecialchars($ch['correo']) ?></td>
              <td><?= $ch['total_mensajes'] ?></td>
              <td><?= $ch['sin_leer'] > 0 ? '<span class="badge rojo">'.$ch['sin_leer'].'</span>' : '—' ?></td>
              <td><?= date("d/m H:i", strtotime($ch['ultimo_mensaje'])) ?></td>
              <td>
                <a href="panel_nutricionista.php?usuario=<?= $ch['id_usuario'] ?>" target="_blank" class="btn-tbl navy">Ver chat</a>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($chats_res)): ?>
            <tr><td colspan="6" style="text-align:center; color:var(--text-light)">No hay chats aún.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>

    <?php endif; ?>

  </div>
</div>

<?php endif; ?>
</body>
</html>