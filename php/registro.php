<?php
include("conexion.php");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../html/registro.html");
    exit;
}

// Recoger datos
$nombre     = trim($_POST['nombre']            ?? '');
$correo     = trim($_POST['correo']            ?? '');
$contraseña = $_POST['contraseña']             ?? '';
$confirmar  = $_POST['confirmarContraseña']    ?? '';
$edad       = $_POST['edad']                   ?? '';
$genero     = trim($_POST['genero']            ?? '');
$peso       = $_POST['peso_actual']            ?? '';
$altura     = $_POST['altura']                 ?? '';
$objetivo   = trim($_POST['objetivo']          ?? '');
$tipo_dieta = trim($_POST['tipo_dieta']        ?? '');

$errores = [];

// ── Validaciones obligatorias ──────────────────────────
if (strlen($nombre) < 2) {
    $errores[] = "El nombre debe tener al menos 2 caracteres.";
}

if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    $errores[] = "El correo electrónico no es válido.";
}

if (strlen($contraseña) < 8) {
    $errores[] = "La contraseña debe tener al menos 8 caracteres.";
}

if ($contraseña !== $confirmar) {
    $errores[] = "Las contraseñas no coinciden.";
}

// ── Validaciones opcionales (solo si vienen rellenas) ──
if ($edad !== '' && (!is_numeric($edad) || $edad < 10 || $edad > 120)) {
    $errores[] = "La edad debe estar entre 10 y 120 años.";
}

if ($peso !== '' && (!is_numeric($peso) || $peso <= 0 || $peso > 500)) {
    $errores[] = "El peso debe estar entre 1 y 500 kg.";
}

if ($altura !== '' && (!is_numeric($altura) || $altura <= 0 || $altura > 300)) {
    $errores[] = "La altura debe estar entre 1 y 300 cm.";
}

// Valores nulos si vienen vacíos
$edad   = $edad   !== '' ? intval($edad)      : null;
$peso   = $peso   !== '' ? floatval($peso)    : null;
$altura = $altura !== '' ? floatval($altura)  : null;

// ── Si hay errores, volver al formulario ───────────────
if (!empty($errores)) {
    $lista = implode('\n', $errores);
    echo "<script>alert('⚠️ Por favor corrige lo siguiente:\\n\\n$lista'); window.history.back();</script>";
    exit;
}

// ── Verificar correo duplicado ─────────────────────────
$chk = $conn->prepare("SELECT id_usuario FROM usuarios WHERE correo = ?");
$chk->bind_param("s", $correo);
$chk->execute();
if ($chk->get_result()->num_rows > 0) {
    echo "<script>alert('⚠️ Este correo ya está registrado. Intenta iniciar sesión.'); window.history.back();</script>";
    exit;
}

// ── Insertar ───────────────────────────────────────────
$contraseña_segura = password_hash($contraseña, PASSWORD_DEFAULT);

$stmt = $conn->prepare("
    INSERT INTO usuarios (nombre, correo, contraseña, edad, genero, peso_actual, altura, objetivo, tipo_dieta)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    die("Error en prepare(): " . $conn->error);
}

$stmt->bind_param("sssisddss",
    $nombre,
    $correo,
    $contraseña_segura,
    $edad,
    $genero,
    $peso,
    $altura,
    $objetivo,
    $tipo_dieta
);

if ($stmt->execute()) {
    echo "<script>alert('✅ Cuenta creada correctamente. Ya puedes iniciar sesión.'); window.location='../html/login.html';</script>";
} else {
    echo "<script>alert('❌ Error al registrar. Intenta de nuevo.'); window.history.back();</script>";
}

$stmt->close();
$conn->close();