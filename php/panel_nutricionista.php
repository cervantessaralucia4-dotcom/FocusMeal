<?php
session_start();
require "conexion.php";

// Autenticación simple para nutricionista
// En producción usar una tabla de nutricionistas con password_hash
$NUTRI_USER = "nutricionista";
$NUTRI_PASS = "focusmeal2025"; // Cambiar por una contraseña segura

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["login_nutri"])) {
    if ($_POST["usuario"] === $NUTRI_USER && $_POST["password"] === $NUTRI_PASS) {
        $_SESSION["nutricionista"] = true;
    } else {
        $error_login = "Credenciales incorrectas.";
    }
}

if (isset($_POST["logout_nutri"])) {
    unset($_SESSION["nutricionista"]);
}

$logueada = isset($_SESSION["nutricionista"]);

// Enviar respuesta
if ($logueada && $_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["respuesta"])) {
    $id_usuario = intval($_POST["id_usuario"]);
    $respuesta  = trim($_POST["respuesta"]);
    if ($id_usuario > 0 && strlen($respuesta) > 0) {
        $ins = $conn->prepare("INSERT INTO chats (id_usuario, mensaje, enviado_por) VALUES (?, ?, 'nutricionista')");
        $ins->bind_param("is", $id_usuario, $respuesta);
        $ins->execute();
    }
    header("Location: panel_nutricionista.php?usuario=" . $id_usuario);
    exit;
}

// Cargar usuarios con chats
$usuarios_chat = [];
if ($logueada) {
    $res = $conn->query("
        SELECT u.id_usuario, u.nombre, u.correo,
               COUNT(CASE WHEN c.enviado_por = 'usuario' AND c.leido = 0 THEN 1 END) as sin_leer,
               MAX(c.fecha_envio) as ultimo_mensaje
        FROM chats c
        JOIN usuarios u ON u.id_usuario = c.id_usuario
        GROUP BY u.id_usuario
        ORDER BY sin_leer DESC, ultimo_mensaje DESC
    ");
    $usuarios_chat = $res->fetch_all(MYSQLI_ASSOC);
}

// Cargar conversación de un usuario
$conv = [];
$usuario_sel = null;
if ($logueada && isset($_GET["usuario"])) {
    $uid = intval($_GET["usuario"]);
    $stmt = $conn->prepare("SELECT * FROM chats WHERE id_usuario = ? ORDER BY fecha_envio ASC LIMIT 200");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $conv = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmt2 = $conn->prepare("SELECT nombre, correo, objetivo, tipo_dieta, peso_actual, altura, edad FROM usuarios WHERE id_usuario = ?");
    $stmt2->bind_param("i", $uid);
    $stmt2->execute();
    $usuario_sel = $stmt2->get_result()->fetch_assoc();
    $usuario_sel["id"] = $uid;

    // Marcar mensajes del usuario como leídos
    $conn->query("UPDATE chats SET leido = 1 WHERE id_usuario = $uid AND enviado_por = 'usuario'");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel Nutricionista — FocusMeal</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Unbounded:wght@200;400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="../css/styles.css">
  <style>
    body {
        background-color: var(--bg);
    }
    .nutri-layout {
        display: flex;
        height: calc(100vh - 70px);
        overflow: hidden;
    }
    /* Sidebar Conversaciones */
    .nutri-sidebar {
        width: 320px;
        background-color: var(--surface);
        border-right: 1px solid var(--border);
        display: flex;
        flex-direction: column;
        flex-shrink: 0;
    }
    .nutri-sidebar-header {
        padding: 20px;
        font-family: var(--font-headings);
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--navy);
        border-bottom: 1px solid var(--border);
        background: #fcfdfe;
    }
    .nutri-chat-list {
        flex: 1;
        overflow-y: auto;
    }
    .nutri-chat-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px 20px;
        border-bottom: 1px solid var(--border);
        text-decoration: none;
        color: var(--text);
        transition: var(--transition);
    }
    .nutri-chat-item:hover {
        background-color: rgba(22, 163, 74, 0.02);
    }
    .nutri-chat-item.activo {
        background-color: rgba(22, 163, 74, 0.06);
        border-left: 4px solid var(--green);
    }
    .nutri-chat-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background-color: var(--navy);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.95rem;
        flex-shrink: 0;
    }
    .nutri-chat-item.activo .nutri-chat-avatar {
        background-color: var(--green);
    }
    .nutri-chat-info {
        flex: 1;
        min-width: 0;
    }
    .nutri-chat-name {
        font-weight: 600;
        font-size: 0.88rem;
        color: var(--navy);
        margin-bottom: 2px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .nutri-chat-desc {
        font-size: 0.78rem;
        color: var(--text-light);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    /* Area de Chat */
    .nutri-chat-pane {
        flex: 1;
        display: flex;
        flex-direction: column;
        background-color: #f8fafc;
    }
    .nutri-pane-header {
        background-color: var(--surface);
        border-bottom: 1px solid var(--border);
        padding: 16px 24px;
        display: flex;
        align-items: center;
        gap: 15px;
    }
    .nutri-pane-messages {
        flex: 1;
        overflow-y: auto;
        padding: 24px;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    
    /* Burbujas */
    .chat-bubble-row {
        display: flex;
        gap: 10px;
        align-items: flex-end;
    }
    .chat-bubble-row.nutricionista {
        flex-direction: row-reverse;
    }
    .chat-bubble-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        font-weight: 700;
        flex-shrink: 0;
    }
    .chat-bubble-avatar.usuario {
        background: var(--navy-light);
        color: #fff;
    }
    .chat-bubble-avatar.nutricionista {
        background: var(--green);
        color: #fff;
    }
    .chat-bubble-content {
        max-width: 60%;
    }
    .chat-bubble {
        padding: 12px 16px;
        border-radius: var(--radius-md);
        font-size: 0.9rem;
        line-height: 1.5;
        box-shadow: var(--shadow-sm);
    }
    .chat-bubble-row.usuario .chat-bubble {
        background: var(--surface);
        border: 1px solid var(--border);
        color: var(--text);
        border-bottom-left-radius: 4px;
    }
    .chat-bubble-row.nutricionista .chat-bubble {
        background: var(--navy);
        color: #fff;
        border-bottom-right-radius: 4px;
    }
    .chat-bubble-time {
        font-size: 0.7rem;
        color: var(--text-light);
        margin-top: 4px;
        padding: 0 4px;
    }
    .chat-bubble-row.nutricionista .chat-bubble-time {
        text-align: right;
    }
    
    /* Input Form */
    .nutri-chat-input-area {
        background-color: var(--surface);
        border-top: 1px solid var(--border);
        padding: 18px 24px;
    }
    .nutri-chat-input-area textarea {
        background-color: var(--bg);
        border: 1.5px solid var(--border);
        border-radius: var(--radius-sm);
        padding: 12px 16px;
        font-size: 0.9rem;
        color: var(--text);
        resize: none;
        outline: none;
        transition: var(--transition);
        margin-bottom: 0;
    }
    .nutri-chat-input-area textarea:focus {
        border-color: var(--green);
        background-color: #fff;
        box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1);
    }
    
    /* Login Page */
    .login-body {
        background-color: var(--navy);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        padding: 20px;
    }
    .login-card {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.08);
        backdrop-filter: blur(10px);
        border-radius: var(--radius-lg);
        width: 100%;
        max-width: 420px;
        padding: 40px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    }
    .login-card .form-control {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: #fff;
    }
    .login-card .form-control:focus {
        background: rgba(255, 255, 255, 0.05);
        border-color: var(--green);
        color: #fff;
    }
  </style>
</head>
<body class="<?= !$logueada ? 'login-body' : '' ?>">

<?php if (!$logueada): ?>

  <!-- LOGIN NUTRICIONISTA -->
  <div class="login-card text-center">
    <div class="mb-4">
        <img src="../img/logo.png" alt="FocusMeal Logo" style="height: 48px;" class="mb-2">
        <h1 class="h4 font-headings fw-normal text-white mb-1">Panel Nutricionista</h1>
        <p class="text-muted small mb-0" style="color: rgba(255,255,255,0.4) !important;">Acceso exclusivo para el equipo de nutrición.</p>
    </div>
    
    <?php if (isset($error_login)): ?>
        <div class="alert alert-danger border-0 small py-2 mb-3" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border-radius: var(--radius-sm);">
            <i class="fa-solid fa-circle-xmark me-1"></i> <?= htmlspecialchars($error_login) ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="text-start">
        <input type="hidden" name="login_nutri" value="1">
        
        <div class="mb-3">
            <label class="form-label text-white-50">Usuario</label>
            <input type="text" name="usuario" class="form-control" placeholder="Nutricionista" required>
        </div>

        <div class="mb-4">
            <label class="form-label text-white-50">Contraseña</label>
            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2.5 rounded-pill border-0" style="background: var(--green); font-weight: 600;">Ingresar</button>
    </form>
  </div>

<?php else: ?>

  <!-- HEADER -->
  <header class="panel-header">
    <a href="admin.php" class="logo-container">
      <img src="../img/logo.png" alt="FocusMeal Logo">
      <span>Focus Meal</span>
    </a>
    <div class="d-flex align-items-center gap-3">
      <span class="small text-white-50 d-none d-sm-inline">🩺 Canal de Soporte Nutricional</span>
      <form method="POST" class="m-0">
        <button type="submit" name="logout_nutri" class="btn btn-danger px-3 py-1.5 rounded-pill text-decoration-none" style="font-size: 0.8rem;"><i class="fa-solid fa-right-from-bracket me-1"></i> Cerrar sesión</button>
      </form>
    </div>
  </header>

  <!-- PANEL PRINCIPAL -->
  <div class="nutri-layout">

    <!-- Sidebar: lista de usuarios -->
    <aside class="nutri-sidebar">
      <div class="nutri-sidebar-header">
        <i class="fa-regular fa-comments text-success me-2"></i> Conversaciones (<?= count($usuarios_chat) ?>)
      </div>

      <div class="nutri-chat-list">
        <?php if (empty($usuarios_chat)): ?>
          <div class="p-4 text-center text-muted small">
            <i class="fa-regular fa-folder-open mb-2 d-block h4 text-muted"></i>
            Aún no hay mensajes de usuarios.
          </div>
        <?php else: ?>
          <?php foreach ($usuarios_chat as $u): ?>
            <a href="panel_nutricionista.php?usuario=<?= $u['id_usuario'] ?>"
               class="nutri-chat-item <?= (isset($_GET['usuario']) && $_GET['usuario'] == $u['id_usuario']) ? 'activo' : '' ?>">
              <div class="nutri-chat-avatar">
                <?= strtoupper(substr($u['nombre'], 0, 1)) ?>
              </div>
              <div class="nutri-chat-info">
                <div class="nutri-chat-name">
                  <span><?= htmlspecialchars($u['nombre']) ?></span>
                  <?php if ($u['sin_leer'] > 0): ?>
                    <span class="badge bg-danger rounded-pill" style="font-size: 0.6rem; padding: 3px 6px;"><?= $u['sin_leer'] ?></span>
                  <?php endif; ?>
                </div>
                <div class="nutri-chat-desc"><?= htmlspecialchars($u['correo']) ?></div>
              </div>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </aside>

    <!-- Área de chat -->
    <main class="nutri-chat-pane">

      <?php if ($usuario_sel): ?>

        <!-- Header con perfil del usuario -->
        <div class="nutri-pane-header">
          <div class="nutri-chat-avatar bg-success">
            <?= strtoupper(substr($usuario_sel['nombre'], 0, 1)) ?>
          </div>
          <div>
            <h2 class="h6 mb-1 font-headings fw-bold text-dark" style="font-family: var(--font-headings);"><?= htmlspecialchars($usuario_sel['nombre']) ?></h2>
            <div class="d-flex flex-wrap gap-2 align-items-center">
              <span class="badge gris">Objetivo: <?= htmlspecialchars($usuario_sel['objetivo'] ?: '—') ?></span>
              <span class="badge gris">Dieta: <?= htmlspecialchars($usuario_sel['tipo_dieta'] ?: '—') ?></span>
              <span class="badge gris">Peso: <?= $usuario_sel['peso_actual'] ?: '—' ?> kg</span>
              <span class="badge gris">Altura: <?= $usuario_sel['altura'] ?: '—' ?> cm</span>
              <span class="badge gris">Edad: <?= $usuario_sel['edad'] ?: '—' ?> años</span>
            </div>
          </div>
        </div>

        <!-- Mensajes -->
        <div class="nutri-pane-messages" id="mensajes-area">
          <?php foreach ($conv as $msg): ?>
            <?php $es_nutri = $msg["enviado_por"] === "nutricionista"; ?>
            <div class="chat-bubble-row <?= $es_nutri ? 'nutricionista' : 'usuario' ?>">
              <div class="chat-bubble-avatar <?= $es_nutri ? 'nutricionista' : 'usuario' ?>">
                <?= $es_nutri ? '<i class="fa-solid fa-user-doctor"></i>' : strtoupper(substr($usuario_sel['nombre'], 0, 1)) ?>
              </div>
              <div class="chat-bubble-content">
                <div class="chat-bubble">
                  <?= nl2br(htmlspecialchars($msg["mensaje"])) ?>
                </div>
                <div class="chat-bubble-time"><?= date("d/m H:i", strtotime($msg["fecha_envio"])) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Formulario de respuesta -->
        <div class="nutri-chat-input-area">
          <form method="POST" action="panel_nutricionista.php?usuario=<?= $usuario_sel['id'] ?>" class="d-flex gap-2">
            <input type="hidden" name="id_usuario" value="<?= $usuario_sel['id'] ?>">
            <textarea name="respuesta" class="form-control" rows="1" placeholder="Escribe tu recomendación nutricional..." required id="resp-input" style="flex: 1;"></textarea>
            <button type="submit" class="btn btn-primary px-4 rounded-pill border-0 d-flex align-items-center gap-2" style="background: var(--green); font-weight: 600;"><i class="fa-regular fa-paper-plane"></i> <span>Enviar</span></button>
          </form>
        </div>

      <?php else: ?>
        <div class="flex-grow-1 d-flex flex-column align-items-center justify-content-center text-muted p-5">
            <div class="display-5 text-muted mb-3"><i class="fa-regular fa-comments"></i></div>
            <h3 class="h5 mb-1 font-headings text-dark" style="font-family: var(--font-headings); font-weight: 500;">Bandeja de Entrada</h3>
            <p class="small text-center text-muted" style="max-width: 320px;">Selecciona un cliente de la lista de conversaciones para comenzar la asesoría nutricional.</p>
        </div>
      <?php endif; ?>

    </main>
  </div>

<?php endif; ?>

<script>
const area = document.getElementById('mensajes-area');
if (area) area.scrollTop = area.scrollHeight;

const inp = document.getElementById('resp-input');
if (inp) {
  inp.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      if (this.value.trim()) this.closest('form').submit();
    }
  });
  // Auto-refresh cada 30 segundos para ver mensajes nuevos
  setTimeout(() => {
    // Solo recargar si no hay texto en el input
    if(!inp.value.trim()){
      location.reload();
    }
  }, 30000);
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>