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
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Unbounded:wght@200;400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/dashboard.css">
  <style>
    body { background: var(--bg); }
    .nutri-wrap { display: flex; height: calc(100vh - 64px); overflow: hidden; }

    /* Sidebar usuarios */
    .sidebar {
      width: 300px; flex-shrink: 0;
      background: var(--surface); border-right: 1px solid var(--border);
      overflow-y: auto; display: flex; flex-direction: column;
    }
    .sidebar-titulo {
      padding: 18px 20px; font-weight: 700; font-size: 0.9rem;
      color: var(--navy); border-bottom: 1px solid var(--border);
      background: var(--surface); position: sticky; top: 0;
    }
    .usuario-item {
      display: block; padding: 14px 20px; text-decoration: none;
      border-bottom: 1px solid var(--border); transition: background 0.15s;
    }
    .usuario-item:hover { background: var(--bg); }
    .usuario-item.activo { background: #f0fdf4; border-left: 3px solid var(--green); }
    .usuario-nombre { font-weight: 600; font-size: 0.88rem; color: var(--navy); display: flex; align-items: center; justify-content: space-between; }
    .usuario-correo { font-size: 0.78rem; color: var(--text-light); margin-top: 2px; }
    .badge-sin-leer {
      background: var(--green); color: #fff;
      font-size: 0.7rem; font-weight: 700;
      padding: 2px 7px; border-radius: 999px;
    }
    .sidebar-vacio { padding: 32px 20px; text-align: center; color: var(--text-light); font-size: 0.85rem; }

    /* Área de chat */
    .chat-area { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
    .chat-area-header {
      padding: 16px 24px; background: var(--surface);
      border-bottom: 1px solid var(--border);
      display: flex; align-items: center; gap: 12px;
    }
    .perfil-mini { font-size: 0.8rem; color: var(--text-light); }
    .perfil-mini strong { color: var(--text); }

    .mensajes-area {
      flex: 1; overflow-y: auto; padding: 20px 24px;
      display: flex; flex-direction: column; gap: 12px;
    }

    .burbuja-wrap { display: flex; align-items: flex-end; gap: 8px; }
    .burbuja-wrap.nutricionista { flex-direction: row-reverse; }
    .avatar-chat {
      width: 30px; height: 30px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 0.8rem; font-weight: 700; flex-shrink: 0;
    }
    .av-user  { background: var(--navy); color: #fff; }
    .av-nutri { background: var(--green); color: #fff; }
    .burbuja {
      max-width: 68%; padding: 10px 14px; border-radius: 14px;
      font-size: 0.88rem; line-height: 1.5;
    }
    .burbuja.usuario       { background: var(--bg); border: 1px solid var(--border); color: var(--text); border-bottom-left-radius: 4px; }
    .burbuja.nutricionista { background: var(--navy); color: #fff; border-bottom-right-radius: 4px; }
    .burbuja-hora { font-size: 0.68rem; color: var(--text-light); margin-top: 3px; }
    .burbuja-wrap.nutricionista .burbuja-hora { text-align: right; }

    /* Input respuesta */
    .respuesta-form {
      padding: 16px 24px; background: var(--surface);
      border-top: 1px solid var(--border);
      display: flex; gap: 10px; align-items: flex-end;
    }
    .respuesta-form textarea {
      flex: 1; padding: 11px 14px; border: 1.5px solid var(--border);
      border-radius: 10px; font-family: 'Inter', sans-serif;
      font-size: 0.88rem; resize: none; min-height: 44px; max-height: 120px;
      transition: border-color 0.2s; margin-bottom: 0;
    }
    .respuesta-form textarea:focus { outline: none; border-color: var(--green); }
    .btn-resp {
      padding: 11px 20px; background: var(--green); color: #fff;
      border: none; border-radius: 999px; font-family: 'Inter', sans-serif;
      font-size: 0.88rem; font-weight: 700; cursor: pointer; white-space: nowrap;
      transition: background 0.2s;
    }
    .btn-resp:hover { background: #15803D; }

    .selecciona-txt {
      flex: 1; display: flex; align-items: center; justify-content: center;
      color: var(--text-light); font-size: 0.9rem; text-align: center; padding: 40px;
    }

    /* Login */
    .login-wrap {
      max-width: 400px; margin: 80px auto; padding: 0 24px;
    }
  </style>
</head>
<body>

<div class="panel-header">
  <div class="logo-container">
    <img src="../img/logo.png" alt="FocusMeal">
    <span>Focus Meal</span>
  </div>
  <?php if ($logueada): ?>
    <form method="POST" style="margin:0">
      <button name="logout_nutri" class="btn-danger">Cerrar sesión</button>
    </form>
  <?php endif; ?>
</div>

<?php if (!$logueada): ?>

  <!-- LOGIN NUTRICIONISTA -->
  <div class="login-wrap">
    <div class="card">
      <h2 style="margin-bottom:6px">👩‍⚕️ Panel Nutricionista</h2>
      <p style="color:var(--text-light); font-size:0.88rem; margin-bottom:20px">Acceso exclusivo para el equipo de nutrición.</p>

      <?php if (isset($error_login)): ?>
        <p><strong>❌ <?= htmlspecialchars($error_login) ?></strong></p>
      <?php endif; ?>

      <form method="POST">
        <label>Usuario</label>
        <input type="text" name="usuario" required placeholder="nutricionista">
        <label>Contraseña</label>
        <input type="password" name="password" required>
        <br>
        <button type="submit" name="login_nutri" class="btn-primary" style="width:100%">Ingresar</button>
      </form>
    </div>
  </div>

<?php else: ?>

  <!-- PANEL PRINCIPAL -->
  <div class="nutri-wrap">

    <!-- Sidebar: lista de usuarios -->
    <div class="sidebar">
      <div class="sidebar-titulo">💬 Conversaciones (<?= count($usuarios_chat) ?>)</div>

      <?php if (empty($usuarios_chat)): ?>
        <div class="sidebar-vacio">Aún no hay mensajes de usuarios.</div>
      <?php else: ?>
        <?php foreach ($usuarios_chat as $u): ?>
          <a href="panel_nutricionista.php?usuario=<?= $u['id_usuario'] ?>"
             class="usuario-item <?= (isset($_GET['usuario']) && $_GET['usuario'] == $u['id_usuario']) ? 'activo' : '' ?>">
            <div class="usuario-nombre">
              <?= htmlspecialchars($u['nombre']) ?>
              <?php if ($u['sin_leer'] > 0): ?>
                <span class="badge-sin-leer"><?= $u['sin_leer'] ?></span>
              <?php endif; ?>
            </div>
            <div class="usuario-correo"><?= htmlspecialchars($u['correo']) ?></div>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Área de chat -->
    <div class="chat-area">

      <?php if ($usuario_sel): ?>

        <!-- Header con perfil del usuario -->
        <div class="chat-area-header">
          <div class="avatar-chat av-user" style="width:38px; height:38px; font-size:1rem">
            <?= strtoupper(substr($usuario_sel['nombre'], 0, 1)) ?>
          </div>
          <div>
            <strong style="font-size:0.95rem; color:var(--navy)"><?= htmlspecialchars($usuario_sel['nombre']) ?></strong>
            <div class="perfil-mini">
              <strong>Objetivo:</strong> <?= htmlspecialchars($usuario_sel['objetivo'] ?: '—') ?> &nbsp;|&nbsp;
              <strong>Dieta:</strong> <?= htmlspecialchars($usuario_sel['tipo_dieta'] ?: '—') ?> &nbsp;|&nbsp;
              <strong>Peso:</strong> <?= $usuario_sel['peso_actual'] ?: '—' ?> kg &nbsp;|&nbsp;
              <strong>Edad:</strong> <?= $usuario_sel['edad'] ?: '—' ?> años
            </div>
          </div>
        </div>

        <!-- Mensajes -->
        <div class="mensajes-area" id="mensajes-area">
          <?php foreach ($conv as $msg): ?>
            <?php $es_nutri = $msg["enviado_por"] === "nutricionista"; ?>
            <div class="burbuja-wrap <?= $es_nutri ? 'nutricionista' : 'usuario' ?>">
              <div class="avatar-chat <?= $es_nutri ? 'av-nutri' : 'av-user' ?>">
                <?= $es_nutri ? '👩‍⚕️' : strtoupper(substr($usuario_sel['nombre'], 0, 1)) ?>
              </div>
              <div>
                <div class="burbuja <?= $es_nutri ? 'nutricionista' : 'usuario' ?>">
                  <?= nl2br(htmlspecialchars($msg["mensaje"])) ?>
                </div>
                <div class="burbuja-hora"><?= date("d/m H:i", strtotime($msg["fecha_envio"])) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <!-- Formulario de respuesta -->
        <div class="respuesta-form">
          <form method="POST" action="panel_nutricionista.php?usuario=<?= $usuario_sel['id'] ?>" style="display:flex; gap:10px; flex:1; align-items:flex-end; margin:0">
            <input type="hidden" name="id_usuario" value="<?= $usuario_sel['id'] ?>">
            <textarea name="respuesta" placeholder="Escribe tu respuesta..." required id="resp-input"></textarea>
            <button type="submit" class="btn-resp">Enviar</button>
          </form>
        </div>

      <?php else: ?>
        <div class="selecciona-txt">
          <div>
            <div style="font-size:2.5rem; margin-bottom:12px">💬</div>
            <p>Selecciona una conversación del panel izquierdo para ver los mensajes.</p>
          </div>
        </div>
      <?php endif; ?>

    </div>
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
  setTimeout(() => location.reload(), 30000);
}
</script>

</body>
</html>