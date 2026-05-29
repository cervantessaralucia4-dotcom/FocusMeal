<?php
session_start();
include("conexion.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    $correo = trim($_POST['correo'] ?? '');
    $contraseña = $_POST['contraseña'] ?? '';

    // Buscar usuario por correo (tabla bien escrita)
    $sql = "SELECT * FROM usuarios WHERE correo = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $correo);
    $stmt->execute();

    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {

        $usuario = $resultado->fetch_assoc();

        // Verificar contraseña encriptada
        if (password_verify($contraseña, $usuario['contraseña'])) {

            $_SESSION["usuario"] = [
                "id" => $usuario["id_usuario"],
                "nombre" => $usuario["nombre"],
                "correo" => $usuario["correo"]
            ];

            header("Location: panel.php");
            exit;
        } else {
            echo "<script>alert('❌ Contraseña incorrecta'); window.history.back();</script>";
        }
    } else {
        echo "<script>alert('❌ Correo no encontrado'); window.history.back();</script>";
    }
}
?>
