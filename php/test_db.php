<?php
require 'conexion.php';

echo "<h3>Diagnóstico de Base de Datos - FocusMeal</h3>";

// 1. Verificar conexión
if ($conn->connect_error) {
    echo "<p style='color:red;'>❌ Error de conexión: " . $conn->connect_error . "</p>";
    exit;
} else {
    echo "<p style='color:green;'>✅ Conexión establecida correctamente.</p>";
}

// 2. Listar tablas
$result = $conn->query("SHOW TABLES");
echo "<h4>Tablas encontradas:</h4><ul>";
if ($result) {
    while ($row = $result->fetch_row()) {
        echo "<li>" . $row[0] . "</li>";
    }
} else {
    echo "<p style='color:red;'>❌ Error al listar tablas: " . $conn->error . "</p>";
}
echo "</ul>";

// 3. Listar usuarios
$result = $conn->query("SELECT id_usuario, nombre, correo, fecha_registro FROM usuarios");
echo "<h4>Usuarios registrados:</h4>";
if ($result) {
    if ($result->num_rows > 0) {
        echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
        echo "<tr><th>ID</th><th>Nombre</th><th>Correo</th><th>Fecha Registro</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id_usuario'] . "</td>";
            echo "<td>" . htmlspecialchars($row['nombre']) . "</td>";
            echo "<td>" . htmlspecialchars($row['correo']) . "</td>";
            echo "<td>" . $row['fecha_registro'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No hay usuarios registrados en la tabla.</p>";
    }
} else {
    echo "<p style='color:red;'>❌ Error al consultar la tabla de usuarios: " . $conn->error . "</p>";
}
?>
