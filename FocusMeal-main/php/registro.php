<?php
include("conexion.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Obtener datos del formulario
    $nombre = $_POST['nombre'] ?? '';
    $correo = $_POST['correo'] ?? '';
    $contraseña = $_POST['contraseña'] ?? '';
    $confirmar = $_POST['confirmarContraseña'] ?? '';
    $edad = $_POST['edad'] ?? null;
    $genero = $_POST['genero'] ?? '';
    $peso = $_POST['peso_actual'] ?? null;
    $altura = $_POST['altura'] ?? null;
    $objetivo = $_POST['objetivo'] ?? '';
    $tipo_dieta = $_POST['tipo_dieta'] ?? '';

    // Validar contraseñas iguales
    if ($contraseña !== $confirmar) {
        echo "<script>alert('⚠️ Las contraseñas no coinciden.'); window.history.back();</script>";
        exit;
    }

    // Encriptar contraseña
    $contraseña_segura = password_hash($contraseña, PASSWORD_DEFAULT);

    // Sentencia SQL (ajustar nombres de columnas según tu tabla)
    $sql = "INSERT INTO usuarios 
            (nombre, correo, contraseña, edad, genero, peso_actual, altura, objetivo, tipo_dieta) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        die("❌ Error en prepare(): " . $conn->error);
    }

    $stmt->bind_param(
        "sssisddss",
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
        echo "<script>alert('✅ Registro exitoso. Ya puedes iniciar sesión.'); window.location='../login.html';</script>";
    } else {
        if ($conn->errno == 1062) {
            echo "<script>alert('⚠️ Este correo ya está registrado.'); window.history.back();</script>";
        } else {
            echo "<script>alert('❌ Error al registrar: " . $conn->error . "');</script>";
        }
    }

    $stmt->close();
    $conn->close();
}
?>
