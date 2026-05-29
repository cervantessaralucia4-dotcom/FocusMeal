<?php
$servername = getenv('DB_HOST') ?: 'localhost';
$port       = getenv('DB_PORT') ?: 3306;
$username   = getenv('DB_USER') ?: 'root';
$password   = getenv('DB_PASS') ?: '';
$dbname     = getenv('DB_NAME') ?: 'focusmeal';

$conn = new mysqli($servername, $username, $password, $dbname, (int)$port);

if ($conn->connect_error) {
    die("❌ Error de conexión: " . $conn->connect_error);
}

// Inicialización automática de la base de datos si la tabla 'usuarios' no existe
$table_check = $conn->query("SHOW TABLES LIKE 'usuarios'");
if ($table_check && $table_check->num_rows === 0) {
    $sql_file = dirname(__DIR__) . '/database/focusmeal.sql';
    if (file_exists($sql_file)) {
        $sql_content = file_get_contents($sql_file);
        
        // Ejecutar las sentencias del script SQL
        if ($conn->multi_query($sql_content)) {
            do {
                // Limpiar resultados para mantener la conexión sincronizada
                if ($result = $conn->store_result()) {
                    $result->free();
                }
            } while ($conn->next_result());
        }
    }
}
?>