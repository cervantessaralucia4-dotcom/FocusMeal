<?php
/**
 * PayU llama a esta URL (POST) cuando el pago se confirma.
 * Aquí activamos la suscripción Premium del usuario.
 */
require "conexion.php";

$payu_api_key     = "4Vj8eK4rloUd272L48hsrarnUA"; // Mismo que pago_premium.php
$merchant_id      = $_POST["merchant_id"]      ?? "";
$referencia       = $_POST["reference_sale"]   ?? "";
$monto            = $_POST["value"]            ?? "";
$moneda           = $_POST["currency"]         ?? "";
$estado           = $_POST["state_pol"]        ?? "";
$firma_payu       = $_POST["sign"]             ?? "";
$usuario_id       = intval($_POST["extra1"]    ?? 0);
$tipo             = $_POST["extra2"]           ?? "mensual";

// Verificar firma de PayU
$firma_local = md5($payu_api_key . "~" . $merchant_id . "~" . $referencia . "~" . number_format($monto, 1, '.', '') . "~" . $moneda . "~" . $estado);

if ($firma_local !== $firma_payu) {
    http_response_code(400);
    exit("Firma inválida");
}

// Solo procesar si el pago fue aprobado (state_pol = 4)
if ($estado != "4") {
    exit("Pago no aprobado");
}

if ($usuario_id <= 0) {
    exit("Usuario inválido");
}

// Obtener id_plan_premium
$plan = $conn->query("SELECT id_plan_premium FROM planes_premium WHERE activo = 1 LIMIT 1")->fetch_assoc();
if (!$plan) exit("Plan no encontrado");

$id_plan_premium = $plan["id_plan_premium"];

// Calcular vencimiento
$fecha_inicio     = date("Y-m-d");
$fecha_vencimiento = $tipo === "anual"
    ? date("Y-m-d", strtotime("+1 year"))
    : date("Y-m-d", strtotime("+1 month"));

// Cancelar suscripciones activas previas
$upd = $conn->prepare("UPDATE suscripciones SET estado = 'cancelada' WHERE id_usuario = ? AND estado = 'activa'");
$upd->bind_param("i", $usuario_id);
$upd->execute();

// Insertar nueva suscripción
$ins = $conn->prepare("
    INSERT INTO suscripciones (id_usuario, id_plan_premium, tipo, estado, fecha_inicio, fecha_vencimiento, referencia_payu)
    VALUES (?, ?, ?, 'activa', ?, ?, ?)
");
$ins->bind_param("iissss", $usuario_id, $id_plan_premium, $tipo, $fecha_inicio, $fecha_vencimiento, $referencia);
$ins->execute();

// Marcar usuario como premium
$upd2 = $conn->prepare("UPDATE usuarios SET es_premium = 1 WHERE id_usuario = ?");
$upd2->bind_param("i", $usuario_id);
$upd2->execute();

http_response_code(200);
echo "OK";