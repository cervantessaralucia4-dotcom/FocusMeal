<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require "conexion.php";

if (!isset($_SESSION["usuario"])) {
    die("No hay sesión iniciada");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $usuario_id = $_SESSION["usuario"]["id"];
    $id_plan = $_POST["id_plan"];

    echo "Usuario: " . $usuario_id . "<br>";
    echo "Plan elegido: " . $id_plan . "<br>";

    $stmt = $conn->prepare("SELECT * FROM planes_disponibles WHERE id_plan = ?");
    if (!$stmt) {
        die("Error en SELECT: " . $conn->error);
    }

    $stmt->bind_param("i", $id_plan);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $plan = $resultado->fetch_assoc();

    if (!$plan) {
        die("No se encontró el plan");
    }

    echo "Plan encontrado: " . $plan["nombre_plan"] . "<br>";

    $stmt2 = $conn->prepare("INSERT INTO planes
    (id_usuario, nombre_plan, calorias_diarias, fecha_inicio, fecha_fin, estado)
    VALUES (?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'activo')");

    if (!$stmt2) {
        die("Error en INSERT: " . $conn->error);
    }

    $stmt2->bind_param("isi",
        $usuario_id,
        $plan["nombre_plan"],
        $plan["calorias_diarias"]
    );

    if ($stmt2->execute()) {
        echo "Plan guardado correctamente";
    } else {
        echo "Error al ejecutar INSERT: " . $stmt2->error;
    }

}
?>
