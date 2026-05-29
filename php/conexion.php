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

// Inicialización automática y sincronización de base de datos
$required_tables = ['alimentos', 'comidas', 'historial_progreso', 'planes', 'recomendaciones', 'usuarios', 'planes_disponibles', 'planes_premium', 'suscripciones', 'pqrs', 'chats', 'plan_generado'];

$res = $conn->query("SHOW TABLES");
$existing_tables = [];
if ($res) {
    while ($row = $res->fetch_row()) {
        $existing_tables[] = strtolower($row[0]);
    }
}

$missing = false;
foreach ($required_tables as $table) {
    if (!in_array($table, $existing_tables)) {
        $missing = true;
        break;
    }
}

// También verificar si faltan columnas críticas en tablas existentes
if (!$missing) {
    // Verificar es_premium en usuarios
    $col_check1 = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'es_premium'");
    if (!$col_check1 || $col_check1->num_rows === 0) {
        $missing = true;
    }
    // Verificar fecha en comidas
    $col_check2 = $conn->query("SHOW COLUMNS FROM comidas LIKE 'fecha'");
    if (!$col_check2 || $col_check2->num_rows === 0) {
        $missing = true;
    }
}

if ($missing) {
    $sql_file = dirname(__DIR__) . '/database/focusmeal.sql';
    if (file_exists($sql_file)) {
        $sql_content = file_get_contents($sql_file);
        
        // Desactivar restricciones de claves foráneas para limpiar y reestructurar
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");
        
        // Dropear todas las tablas existentes para evitar colisiones
        foreach ($existing_tables as $tbl) {
            $conn->query("DROP TABLE IF EXISTS `$tbl` CASCADE");
        }
        
        // Ejecutar las sentencias de importación
        if ($conn->multi_query($sql_content)) {
            do {
                if ($result = $conn->store_result()) {
                    $result->free();
                }
            } while ($conn->next_result());
        }
        
        // Reactivar restricciones de claves foráneas
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    }
}
?>