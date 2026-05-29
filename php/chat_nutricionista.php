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
$es_premium = esPremium($conn, $usuario_id);

if (!$es_premium) {
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
$conn->query("UPDATE chats SET leido = 1 WHERE id_usuario = $usuario_id AND enviado_por = 'nutricionista'");
$active_page = 'nutricionista';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Chat Nutricionista — FocusMeal</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Unbounded:wght@200;400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link rel="stylesheet" href="../css/styles.css">
  <style>
    .chat-container-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      display: flex;
      flex-direction: column;
      height: calc(100vh - 180px);
      min-height: 500px;
      overflow: hidden;
      box-shadow: var(--shadow-sm);
    }

    .chat-header-bar {
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 16px 24px;
      border-bottom: 1px solid var(--border);
      background: var(--surface);
    }

    .nutricionista-avatar {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      background: rgba(22, 163, 74, 0.1);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.3rem;
      flex-shrink: 0;
      border: 1px solid rgba(22, 163, 74, 0.2);
    }

    .nutricionista-info strong {
      display: block;
      font-size: 0.95rem;
      color: var(--navy);
      font-family: var(--font-headings);
      font-weight: 500;
    }

    .estado-online {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      font-size: 0.78rem;
      color: var(--green);
      font-weight: 600;
    }

    .estado-online::before {
      content: '';
      width: 7px;
      height: 7px;
      background: var(--green);
      border-radius: 50%;
      display: inline-block;
    }

    /* Burbuja de mensajes */
    .chat-box {
      flex: 1;
      padding: 24px;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      gap: 16px;
      background: var(--background);
    }

    .burbuja-wrap {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      max-width: 80%;
    }

    .burbuja-wrap.usuario {
      flex-direction: row-reverse;
      align-self: flex-end;
    }

    .burbuja-wrap.nutricionista {
      align-self: flex-start;
    }

    .avatar-chat {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.85rem;
      font-weight: 700;
      flex-shrink: 0;
    }

    .avatar-nutricionista {
      background: rgba(22, 163, 74, 0.1);
      color: var(--green);
      border: 1px solid rgba(22, 163, 74, 0.2);
    }

    .avatar-usuario {
      background: var(--navy);
      color: #fff;
    }

    .burbuja {
      padding: 12px 18px;
      border-radius: var(--radius-md);
      font-size: 0.9rem;
      line-height: 1.55;
    }

    .burbuja.nutricionista {
      background: var(--surface);
      border: 1px solid var(--border);
      color: var(--text-dark);
      border-top-left-radius: 0px;
    }

    .burbuja.usuario {
      background: var(--green);
      color: #fff;
      border-top-right-radius: 0px;
    }

    .burbuja-hora {
      font-size: 0.72rem;
      color: var(--text-light);
      margin-top: 4px;
      text-align: right;
      font-weight: 500;
    }

    .burbuja-wrap.nutricionista .burbuja-hora {
      text-align: left;
    }

    /* Estado vacío */
    .chat-vacio {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      color: var(--text-light);
      text-align: center;
    }

    /* Input de mensaje */
    .chat-footer {
      padding: 16px 24px;
      background: var(--surface);
      border-top: 1px solid var(--border);
    }

    .chat-input-wrap {
      display: flex;
      gap: 12px;
      align-items: center;
    }

    .chat-input-wrap textarea {
      flex: 1;
      padding: 12px 18px;
      border: 1px solid var(--border);
      border-radius: 999px;
      font-family: 'Inter', sans-serif;
      font-size: 0.9rem;
      resize: none;
      min-height: 46px;
      max-height: 100px;
      color: var(--text-dark);
      background: var(--background);
      transition: var(--transition);
    }

    .chat-input-wrap textarea:focus {
      outline: none;
      border-color: var(--green);
      background: var(--surface);
    }

    .btn-enviar {
      width: 46px;
      height: 46px;
      border-radius: 50%;
      background: var(--green);
      color: #fff;
      border: none;
      font-size: 1rem;
      cursor: pointer;
      flex-shrink: 0;
      transition: var(--transition);
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .btn-enviar:hover {
      background: #15803D;
      transform: scale(1.05);
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
                <h1 class="h3 font-headings mb-1" style="font-family: var(--font-headings); font-weight: 500;">👩‍⚕️ Asesoría Profesional</h1>
                <p class="text-muted mb-0" style="font-size: 0.9rem;">Consulta tus dudas con nuestra nutricionista licenciada</p>
            </div>
            <span class="badge bg-success py-2 px-3 rounded-pill" style="background-color: var(--green) !important;"><i class="fa-solid fa-crown me-1"></i> Premium</span>
        </div>

        <div class="chat-container-card">
            <!-- Header chat -->
            <div class="chat-header-bar">
                <div class="nutricionista-avatar">👩‍⚕️</div>
                <div>
                    <strong>Nutricionista FocusMeal</strong>
                    <span class="estado-online ms-2">En línea</span>
                </div>
            </div>

            <!-- Caja de mensajes -->
            <div class="chat-box" id="chat-box">
                <?php if (empty($mensajes)): ?>
                    <div class="chat-vacio">
                        <i class="fa-regular fa-comments fa-3x mb-3 text-muted opacity-25"></i>
                        <h5 class="font-headings mb-1" style="color: var(--navy);">Chat Privado Encriptado</h5>
                        <p class="text-muted small max-width-350">Comienza a chatear. Cuéntale a la nutricionista sobre tu rutina, objetivos o intolerancias.</p>
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

            <!-- Input footer -->
            <div class="chat-footer">
                <form method="POST" action="chat_nutricionista.php" id="form-chat">
                    <div class="chat-input-wrap">
                        <textarea
                            name="mensaje"
                            id="input-mensaje"
                            placeholder="Escribe tu mensaje aquí..."
                            maxlength="1000"
                            rows="1"
                            required
                        ></textarea>
                        <button type="submit" class="btn-enviar" title="Enviar"><i class="fa-solid fa-paper-plane"></i></button>
                    </div>
                </form>
                <div class="text-center mt-2" style="font-size: 0.75rem; color: var(--text-light);">
                    <i class="fa-solid fa-lock me-1"></i> Los datos compartidos en este chat se tratan bajo confidencialidad profesional médica.
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Scroll al último mensaje
const chatBox = document.getElementById('chat-box');
chatBox.scrollTop = chatBox.scrollHeight;

// Auto-resize del textarea
const textarea = document.getElementById('input-mensaje');
textarea.addEventListener('input', function() {
  this.style.height = 'auto';
  this.style.height = Math.min(this.scrollHeight, 100) + 'px';
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