<?php
session_start();
include("conexion.php");

// Verificar sesión
if (!isset($_SESSION["usuario"])) {
    header("Location: ../login.html");
    exit;
}

$id_usuario = $_SESSION["usuario"]["id"];

// Obtener datos del usuario
$sql = "SELECT * FROM usuarios WHERE id_usuario = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$resultado = $stmt->get_result();
$usuario = $resultado->fetch_assoc();

// Procesar actualización
$mensaje = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = $_POST['nombre'] ?? $usuario['nombre'];
    $edad = $_POST['edad'] ?? $usuario['edad'];
    $genero = $_POST['genero'] ?? $usuario['genero'];
    $peso_actual = $_POST['peso_actual'] ?? $usuario['peso_actual'];
    $altura = $_POST['altura'] ?? $usuario['altura'];
    $objetivo = $_POST['objetivo'] ?? $usuario['objetivo'];
    $tipo_dieta = $_POST['tipo_dieta'] ?? $usuario['tipo_dieta'];

    $sql_update = "UPDATE usuarios SET nombre=?, edad=?, genero=?, peso_actual=?, altura=?, objetivo=?, tipo_dieta=? WHERE id_usuario=?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("ssissssi", $nombre, $edad, $genero, $peso_actual, $altura, $objetivo, $tipo_dieta, $id_usuario);
    
    if ($stmt_update->execute()) {
        $_SESSION["usuario"]["nombre"] = $nombre;
        $mensaje = "✅ Perfil actualizado correctamente";
        // Actualizar datos locales
        $usuario['nombre'] = $nombre;
        $usuario['edad'] = $edad;
        $usuario['genero'] = $genero;
        $usuario['peso_actual'] = $peso_actual;
        $usuario['altura'] = $altura;
        $usuario['objetivo'] = $objetivo;
        $usuario['tipo_dieta'] = $tipo_dieta;
    } else {
        $mensaje = "❌ Error al actualizar el perfil";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - FocusMeal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/index.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="panel.php">Focus Meal</a>
    </div>
</nav>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <h1>⚙️ Mi Perfil</h1>
            <a href="panel.php" class="btn btn-secondary mb-3">⬅ Volver al panel</a>
            
            <?php if ($mensaje): ?>
                <div class="alert alert-info"><?= $mensaje ?></div>
            <?php endif; ?>

            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="POST" novalidate>
                        <div class="mb-3">
                            <label class="form-label">Nombre</label>
                            <input type="text" class="form-control" name="nombre" value="<?= htmlspecialchars($usuario['nombre']) ?>">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Edad</label>
                                <input type="number" class="form-control" name="edad" value="<?= htmlspecialchars($usuario['edad']) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Género</label>
                                <select class="form-control" name="genero">
                                    <option value="">Seleccionar</option>
                                    <option value="Masculino" <?= $usuario['genero'] === 'Masculino' ? 'selected' : '' ?>>Masculino</option>
                                    <option value="Femenino" <?= $usuario['genero'] === 'Femenino' ? 'selected' : '' ?>>Femenino</option>
                                    <option value="Otro" <?= $usuario['genero'] === 'Otro' ? 'selected' : '' ?>>Otro</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Peso actual (kg)</label>
                                <input type="number" step="0.1" class="form-control" name="peso_actual" value="<?= htmlspecialchars($usuario['peso_actual']) ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Altura (cm)</label>
                                <input type="number" step="0.1" class="form-control" name="altura" value="<?= htmlspecialchars($usuario['altura']) ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Objetivo</label>
                                <select class="form-control" name="objetivo">
                                    <option value="">Seleccionar</option>
                                    <option value="Bajar de peso" <?= $usuario['objetivo'] === 'Bajar de peso' ? 'selected' : '' ?>>Bajar de peso</option>
                                    <option value="Mantener peso" <?= $usuario['objetivo'] === 'Mantener peso' ? 'selected' : '' ?>>Mantener peso</option>
                                    <option value="Aumentar masa" <?= $usuario['objetivo'] === 'Aumentar masa' ? 'selected' : '' ?>>Aumentar masa</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tipo de dieta</label>
                                <select class="form-control" name="tipo_dieta">
                                    <option value="">Seleccionar</option>
                                    <option value="General" <?= $usuario['tipo_dieta'] === 'General' ? 'selected' : '' ?>>General</option>
                                    <option value="Vegetariana" <?= $usuario['tipo_dieta'] === 'Vegetariana' ? 'selected' : '' ?>>Vegetariana</option>
                                    <option value="Keto" <?= $usuario['tipo_dieta'] === 'Keto' ? 'selected' : '' ?>>Keto</option>
                                    <option value="Baja en carbohidratos" <?= $usuario['tipo_dieta'] === 'Baja en carbohidratos' ? 'selected' : '' ?>>Baja en carbohidratos</option>
                                    <option value="Alta en proteínas" <?= $usuario['tipo_dieta'] === 'Alta en proteínas' ? 'selected' : '' ?>>Alta en proteínas</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Correo (no editable)</label>
                            <input type="email" class="form-control" value="<?= htmlspecialchars($usuario['correo']) ?>" disabled>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100">💾 Guardar cambios</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
