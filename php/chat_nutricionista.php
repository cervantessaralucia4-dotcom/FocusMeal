<?php
session_start();
require "conexion.php";
require "esPremium.php";

if (!isset($_SESSION["usuario"])) {
    header("Location: ../html/login.html");
    exit;
}

$usuario_id   = $_SESSION["usuario"]["id"];
$nombre_user  = $_SESSION["usuario"]["nombre"];

if (!esPremium($conn, $usuario_id)) {
    header("Location: planes.php");
    exit;
}

// Enviar mensaje
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $mensaje = trim($_POST["mensaje"] ?? "");
    if (strlen($mensaje) > 0 && strlen($mensaje) <= 1000) {
        $ins = $conn->prepare("
            INSERT INTO chats (id_usuario, mensaje, enviado_por)
            VALUES (?, ?, 'usuario')
        ");
        $ins->bind_param("is", $usuario_id, $mensaje);
        $ins->execute();
    }
    header("Location: chat_nutricionista.php");
    exit;
}

// Cargar mensajes
$stmt = $conn->prepare("
    SELECT * FROM chats
    WHERE id_usuario = ?
    ORDER BY fecha_envio ASC
    LIMIT 100
");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$mensajes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Marcar mensajes de nutricionista como leídos
$conn->prepare("UPDATE chats SET leido = 1 WHERE id_usuario = ? AND enviado_por = 'nutricionista'")->execute();
// No necesitamos bind_param aquí, usamos query directa
$conn->query("UPDATE chats SET leido = 1 WHERE id_usuario = $usuario_id AND enviado_por = 'nutricionista'");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Chat Nutricionista — FocusMeal</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Unbounded:wght@200;400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/dashboard.css">
  <style>
    .chat-wrap { max-width: 760px; margin: 0 auto; padding: 32px 24px 48px; }

    .chat-header {
      display: flex; align-items: center; gap: 14px;
      background: var(--surface); border: 1.5px solid var(--border);
      border-radius: 14px; padding: 18px 22px; margin-bottom: 20px;
    }
    .nutricionista-avatar {
      width: 48px; height: 48px; border-radius: 50%;
      background: var(--green); display: flex; align-items: center;
      justify-content: center; font-size: 1.4rem; flex-shrink: 0;
    }
    .nutricionista-info strong { display: block; font-size: 0.95rem; color: var(--navy); }
    .nutricionista-info span   { font-size: 0.8rem; color: var(--text-light); }
    .estado-online {
      display: inline-flex; align-items: center; gap: 5px;
      font-size: 0.75rem; color: var(--green); font-weight: 600;
    }
    .estado-online::before {
      content: ''; width: 7px; height: 7px;
      background: var(--green); border-radius: 50%; display: inline-block;
    }
    .badge-premium-chat {
      margin-left: auto; background: var(--green); color: #fff;
      font-size: 0.7rem; font-weight: 700; padding: 4px 12px;
      border-radius: 999px; letter-spacing: 0.5px;
    }

    /* Burbuja de mensajes */
    .chat-box {
      background: var(--surface); border: 1.5px solid var(--border);
      border-radius: 14px; padding: 20px;
      height: 440px; overflow-y: auto;
      display: flex; flex-direction: column; gap: 12px;
      margin-bottom: 16px; scroll-behavior: smooth;
    }
    .chat-box::-webkit-scrollbar { width: 5px; }
    .chat-box::-webkit-scrollbar-track { background: transparent; }
    .chat-box::-webkit-scrollbar-thumb { background: var(--border); border-radius: 99px; }

    .burbuja-wrap { display: flex; align-items: flex-end; gap: 8px; }
    .burbuja-wrap.usuario { flex-direction: row-reverse; }

    .avatar-chat {
      width: 32px; height: 32px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 0.85rem; font-weight: 700; flex-shrink: 0;
    }
    .avatar-nutricionista { background: var(--green); color: #fff; }
    .avatar-usuario       { background: var(--navy);  color: #fff; }

    .burbuja {
      max-width: 72%; padding: 11px 16px;
      border-radius: 16px; font-size: 0.88rem; line-height: 1.55;
    }
    .burbuja.nutricionista {
      background: var(--bg); border: 1px solid var(--border);
      color: var(--text); border-bottom-left-radius: 4px;
    }
    .burbuja.usuario {
      background: var(--navy); color: #fff;
      border-bottom-right-radius: 4px;
    }
    .burbuja-hora {
      font-size: 0.7rem; color: var(--text-light);
      margin-top: 3px; text-align: right;
    }
    .burbuja-wrap.nutricionista .burbuja-hora { text-align: left; }

    /* Estado vacío */
    .chat-vacio {
      flex: 1; display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      color: var(--text-light); text-align: center;
    }
    .chat-vacio .icono { font-size: 2.5rem; margin-bottom: 10px; }
    .chat-vacio p { font-size: 0.85rem; max-width: 280px; line-height: 1.6; }

    /* Input de mensaje */
    .chat-input-wrap {
      display: flex; gap: 10px; align-items: flex-end;
    }
    .chat-input-wrap textarea {
      flex: 1; padding: 12px 16px;
      border: 1.5px solid var(--border); border-radius: 12px;
      font-family: 'Inter', sans-serif; font-size: 0.9rem;
      resize: none; min-height: 48px; max-height: 120px;
      color: var(--text); background: var(--bg);
      transition: border-color 0.2s;
      margin-bottom: 0;
    }
    .chat-input-wrap textarea:focus {
      outline: none; border-color: var(--green);
      box-shadow: 0 0 0 3px rgba(22,163,74,0.1);
    }
    .btn-enviar {
      width: 48px; height: 48px; border-radius: 50%;
      background: var(--green); color: #fff; border: none;
      font-size: 1.1rem; cursor: pointer; flex-shrink: 0;
      transition: background 0.2s, transform 0.15s;
      display: flex; align-items: center; justify-content: center;
    }
    .btn-enviar:hover { background: #15803D; transform: scale(1.05); }

    .aviso-respuesta {
      text-align: center; font-size: 0.78rem; color: var(--text-light);
      margin-top: 10px;
    }
  </style>
</head>
<body>

<div class="panel-header">
  <div class="logo-container">
    <img src="../img/logo.png" alt="FocusMeal">
    <span>Focus Meal</span>
  </div>
  <a href="logout.php" class="btn-danger">Cerrar sesión</a>
</div>

<div class="chat-wrap">

  <!-- Header nutricionista -->
  <div class="chat-header">
    <div class="nutricionista-avatar">👩‍⚕️</div>
    <div class="nutricionista-info">
      <strong>Nutricionista FocusMeal</strong>
      <span class="estado-online">En línea · Responde en menos de 24h</span>
    </div>
    <span class="badge-premium-chat">⭐ Premium</span>
  </div>

  <!-- Caja de mensajes -->
  <div class="chat-box" id="chat-box">

    <?php if (empty($mensajes)): ?>
      <div class="chat-vacio">
        <div class="icono">💬</div>
        <p>Aún no hay mensajes. ¡Escribe tu primera pregunta a la nutricionista!</p>
      </div>
    <?php else: ?>

      <?php foreach ($mensajes as $msg): ?>
        <?php $es_usuario = $msg["enviado_por"] === "usuario"; ?>
        <div class="burbuja-wrap <?= $es_usuario ? 'usuario' : 'nutricionista' ?>">

          <div class="avatar-chat <?= $es_usuario ? 'avatar-usuario' : 'avatar-nutricionista' ?>">
            <?= $es_usuario ? strtoupper(substr($nombre_user, 0, 1)) : '👩‍⚕️' ?>
          </div>

          <div>
            <div class="burbuja <?= $es_usuario ? 'usuario' : 'nutricionista' ?>">
              <?= nl2br(htmlspecialchars($msg["mensaje"])) ?>
            </div>
            <div class="burbuja-hora">
              <?= date("d/m H:i", strtotime($msg["fecha_envio"])) ?>
            </div>
          </div>

        </div>
      <?php endforeach; ?>

    <?php endif; ?>

  </div>

  <!-- Input de mensaje -->
  <form method="POST" action="chat_nutricionista.php" id="form-chat">
    <div class="chat-input-wrap">
      <textarea
        name="mensaje"
        id="input-mensaje"
        placeholder="Escribe tu pregunta a la nutricionista..."
        maxlength="1000"
        rows="1"
        required
      ></textarea>
      <button type="submit" class="btn-enviar" title="Enviar">➤</button>
    </div>
  </form>

  <p class="aviso-respuesta">
    🔒 Tu conversación es privada. La nutricionista responde en horario hábil (Lun–Vie 8am–6pm).
  </p>

  <br>
  <a href="panel.php" style="color:var(--text-light); font-size:0.88rem;">← Volver al panel</a>

</div>

<script>
// Scroll al último mensaje
const chatBox = document.getElementById('chat-box');
chatBox.scrollTop = chatBox.scrollHeight;

// Auto-resize del textarea
const textarea = document.getElementById('input-mensaje');
textarea.addEventListener('input', function() {
  this.style.height = 'auto';
  this.style.height = Math.min(this.scrollHeight, 120) + 'px';
});

// Enviar con Enter (Shift+Enter = salto de línea)
textarea.addEventListener('keydown', function(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    if (this.value.trim()) {
      document.getElementById('form-chat').submit();
    }
  }
});
</script>

</body>
</html>