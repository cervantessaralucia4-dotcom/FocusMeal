<?php
session_start();
require __DIR__ . '/conexion.php';
require __DIR__ . '/esPremium.php';

if (!isset($_SESSION["usuario"])) {
    header("Location: ../html/login.html");
    exit();
}

$usuario_id = $_SESSION["usuario"]["id"];

// Datos del usuario
$stmt = $conn->prepare("SELECT nombre, objetivo, tipo_dieta, peso_actual FROM usuarios WHERE id_usuario = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();

// Plan activo
$stmt2 = $conn->prepare("SELECT id_plan, nombre_plan, calorias_diarias FROM planes WHERE id_usuario = ? AND estado = 'Activo' LIMIT 1");
$stmt2->bind_param("i", $usuario_id);
$stmt2->execute();
$plan_activo = $stmt2->get_result()->fetch_assoc();
$es_premium = esPremium($conn, $usuario_id);

// Calorías consumidas hoy desde tabla comidas
$cal_hoy = 0;
if ($plan_activo) {
    $stmt3 = $conn->prepare("SELECT SUM(calorias) as total FROM comidas WHERE id_plan = ? AND fecha = CURDATE()");
    $stmt3->bind_param("i", $plan_activo["id_plan"]);
    $stmt3->execute();
    $cal_hoy = intval($stmt3->get_result()->fetch_assoc()["total"]);
}

// Último peso registrado en historial
$stmt4 = $conn->prepare("SELECT peso, fecha FROM historial_progreso WHERE id_usuario = ? ORDER BY fecha DESC LIMIT 1");
$stmt4->bind_param("i", $usuario_id);
$stmt4->execute();
$ultimo_peso = $stmt4->get_result()->fetch_assoc();

// Comidas registradas hoy
$num_comidas_hoy = 0;
if ($plan_activo) {
    $stmt5 = $conn->prepare("SELECT COUNT(*) as total FROM comidas WHERE id_plan = ? AND fecha = CURDATE()");
    $stmt5->bind_param("i", $plan_activo["id_plan"]);
    $stmt5->execute();
    $num_comidas_hoy = intval($stmt5->get_result()->fetch_assoc()["total"]);
}

// Porcentaje de calorías del día
$meta_cal = $plan_activo["calorias_diarias"] ?? 0;
$pct_cal  = $meta_cal > 0 ? min(100, round($cal_hoy / $meta_cal * 100)) : 0;

$nombre     = $usuario["nombre"];
$objetivo   = $usuario["objetivo"];
$tipo_dieta = $usuario["tipo_dieta"];
$active_page = 'dashboard';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel — FocusMeal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Unbounded:wght@200;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
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
                <h1 class="h3 font-headings mb-1" style="font-family: var(--font-headings); font-weight: 500;">👋 ¡Hola, <?= htmlspecialchars($nombre) ?>!</h1>
                <p class="text-muted mb-0" style="font-size: 0.9rem;"><?= date("l, d \d\e F \d\e Y") ?></p>
            </div>
            <?php if ($es_premium): ?>
                <span class="badge bg-success py-2 px-3 rounded-pill" style="background-color: var(--green) !important;"><i class="fa-solid fa-crown me-1"></i> Premium</span>
            <?php endif; ?>
        </div>

        <!-- PLAN CARD -->
        <div class="card p-4 mb-4 border-0 shadow-sm" style="background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%); color: #fff; position: relative; overflow: hidden; border-radius: var(--radius-lg);">
            <div style="position: absolute; right: -20px; bottom: -20px; font-size: 8rem; color: rgba(255,255,255,0.03); font-family: var(--font-headings); font-weight: 600; line-height: 1;">FM</div>
            <div class="row align-items-center position-relative" style="z-index: 1;">
                <div class="col-md-8">
                    <h4 class="mb-2" style="font-family: var(--font-headings); font-weight: 500; font-size: 1.25rem;">Tu Plan Nutricional</h4>
                    <?php if ($plan_activo): ?>
                        <p class="mb-3 opacity-75" style="font-size: 0.95rem;">Estás siguiendo el plan <strong><?= htmlspecialchars($plan_activo["nombre_plan"]) ?></strong>. Tu meta diaria es consumir <strong><?= $meta_cal ?> kcal</strong>.</p>
                    <?php else: ?>
                        <p class="mb-3 opacity-75" style="font-size: 0.95rem;">Aún no tienes un plan alimenticio activo para hoy.</p>
                    <?php endif; ?>
                    
                    <div class="d-flex flex-wrap gap-2">
                        <?php if ($objetivo): ?>
                            <span class="badge bg-white text-dark py-2 px-3 rounded-pill" style="font-size: 0.78rem; font-weight: 600; color: var(--navy) !important;"><i class="fa-solid fa-bullseye me-1 text-success"></i> <?= htmlspecialchars($objetivo) ?></span>
                        <?php endif; ?>
                        <?php if ($tipo_dieta): ?>
                            <span class="badge bg-white text-dark py-2 px-3 rounded-pill" style="font-size: 0.78rem; font-weight: 600; color: var(--navy) !important;"><i class="fa-solid fa-leaf me-1 text-success"></i> <?= htmlspecialchars($tipo_dieta) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <?php if ($plan_activo): ?>
                        <a href="planes.php" class="btn btn-primary px-4 py-2 rounded-pill border-0" style="background: var(--green); font-size: 0.9rem; font-weight: 600;"><i class="fa-solid fa-arrows-rotate me-1"></i> Cambiar Plan</a>
                    <?php else: ?>
                        <a href="planes.php" class="btn btn-primary px-4 py-2 rounded-pill border-0" style="background: var(--green); font-size: 0.9rem; font-weight: 600;"><i class="fa-solid fa-circle-plus me-1"></i> Seleccionar Plan</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- RESUMEN DEL DÍA -->
        <h4 class="mb-3" style="font-family: var(--font-headings); font-weight: 500; font-size: 1.1rem; color: var(--navy);">Resumen del día</h4>
        <div class="stats mb-4">
            <div class="stat-box shadow-sm">
                <div class="icon-wrap" style="background: rgba(22, 163, 74, 0.1); color: var(--green);"><i class="fa-solid fa-fire-flame-curved"></i></div>
                <div>
                    <h2><?= $cal_hoy ?> kcal</h2>
                    <p>Consumidas hoy<?= $meta_cal ? " de $meta_cal" : "" ?></p>
                </div>
            </div>
            <div class="stat-box shadow-sm">
                <div class="icon-wrap" style="background: rgba(14, 165, 233, 0.1); color: #0ea5e9;"><i class="fa-solid fa-percent"></i></div>
                <div>
                    <h2><?= $pct_cal ?>%</h2>
                    <p>Progreso diario</p>
                </div>
            </div>
            <div class="stat-box shadow-sm">
                <div class="icon-wrap" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;"><i class="fa-solid fa-utensils"></i></div>
                <div>
                    <h2><?= $num_comidas_hoy ?></h2>
                    <p>Comidas hoy</p>
                </div>
            </div>
            <div class="stat-box shadow-sm">
                <div class="icon-wrap" style="background: rgba(99, 102, 241, 0.1); color: #6366f1;"><i class="fa-solid fa-weight-scale"></i></div>
                <div>
                    <h2><?= $ultimo_peso ? $ultimo_peso["peso"] . " kg" : "—" ?></h2>
                    <p>Último peso registrado</p>
                </div>
            </div>
        </div>

        <!-- ACCESOS RÁPIDOS GRID -->
        <h4 class="mb-3" style="font-family: var(--font-headings); font-weight: 500; font-size: 1.1rem; color: var(--navy);">Accesos Rápidos</h4>
        <div class="dashboard-grid">
            <a href="progreso.php" class="dashboard-card shadow-sm">
                <i class="fa-solid fa-chart-line fa-2x mb-3" style="color: var(--green);"></i>
                <h3>📊 Mi Progreso</h3>
                <p>Evolución de peso y calorías</p>
            </a>
            <a href="agregar_comida.php" class="dashboard-card shadow-sm">
                <i class="fa-solid fa-plus-circle fa-2x mb-3" style="color: var(--green);"></i>
                <h3>🍽 Agregar Comida</h3>
                <p>Registrar alimentos del día</p>
            </a>
            <a href="planes.php" class="dashboard-card shadow-sm">
                <i class="fa-solid fa-calendar-days fa-2x mb-3" style="color: var(--green);"></i>
                <h3>🥗 Mis Planes</h3>
                <p>Ver o cambiar tu plan activo</p>
            </a>
            <?php if ($es_premium): ?>
                <a href="generar_plan.php" class="dashboard-card shadow-sm" style="border-left: 4px solid var(--green);">
                    <i class="fa-solid fa-robot fa-2x mb-3" style="color: var(--green);"></i>
                    <h3>🤖 Mi Plan IA</h3>
                    <p>Ver tu plan alimenticio personalizado</p>
                </a>
                <a href="chat_nutricionista.php" class="dashboard-card shadow-sm" style="border-left: 4px solid var(--green);">
                    <i class="fa-solid fa-comments fa-2x mb-3" style="color: var(--green);"></i>
                    <h3>💬 Nutricionista</h3>
                    <p>Chat en vivo con tu nutricionista</p>
                </a>
            <?php else: ?>
                <a href="planes.php" class="dashboard-card shadow-sm" style="border-left: 4px solid #f59e0b;">
                    <i class="fa-solid fa-star fa-2x mb-3" style="color: #f59e0b;"></i>
                    <h3>⭐ Activar Premium</h3>
                    <p>Desbloquea el plan IA y el chat</p>
                </a>
            <?php endif; ?>
            <a href="ajustes.php" class="dashboard-card shadow-sm">
                <i class="fa-solid fa-gear fa-2x mb-3" style="color: var(--green);"></i>
                <h3>⚙ Ajustes</h3>
                <p>Editar tu perfil y metas</p>
            </a>
        </div>
    </main>
</div>
</body>
</html>