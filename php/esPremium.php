<?php
/**
 * Verifica si el usuario tiene suscripción premium activa.
 * Actualiza el campo es_premium en la tabla usuarios.
 * Incluir con: require "es_premium.php";
 * Usar: $premium = esPremium($conn, $usuario_id);
 */
function esPremium($conn, $usuario_id) {
    $stmt = $conn->prepare("
        SELECT id_suscripcion FROM suscripciones
        WHERE id_usuario = ?
          AND estado = 'activa'
          AND fecha_vencimiento >= CURDATE()
        LIMIT 1
    ");
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $es = $stmt->get_result()->num_rows > 0;

    // Sincronizar campo en tabla usuarios
    $upd = $conn->prepare("UPDATE usuarios SET es_premium = ? WHERE id_usuario = ?");
    $val = $es ? 1 : 0;
    $upd->bind_param("ii", $val, $usuario_id);
    $upd->execute();

    return $es;
}