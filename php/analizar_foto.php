<?php
session_start();
require "conexion.php";

if (!isset($_SESSION["usuario"])) {
    http_response_code(401);
    echo json_encode(["error" => "No autenticado"]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_FILES["foto"])) {
    http_response_code(400);
    echo json_encode(["error" => "No se recibió ninguna imagen"]);
    exit;
}

$archivo = $_FILES["foto"];

// Validar tipo
$tipos_permitidos = ["image/jpeg", "image/png", "image/webp"];
if (!in_array($archivo["type"], $tipos_permitidos)) {
    echo json_encode(["error" => "Formato no válido. Usa JPG, PNG o WEBP."]);
    exit;
}

// Validar tamaño máximo 5MB
if ($archivo["size"] > 5 * 1024 * 1024) {
    echo json_encode(["error" => "La imagen no puede pesar más de 5MB."]);
    exit;
}

// Convertir a base64
$imagen_base64 = base64_encode(file_get_contents($archivo["tmp_name"]));
$media_type    = $archivo["type"];

// --- CONFIGURACIÓN ---
$api_key = "AIzaSyChly6SkzHsa0ohjC4MQ_L01wxCJG6_Vyo"; // Obtén la tuya gratis en aistudio.google.com
$modelo  = "gemini-1.5-flash"; // Modelo gratuito con visión
$url     = "https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent?key={$api_key}";

$prompt = "Analiza esta imagen de comida y estima sus valores nutricionales para una porción normal visible.
Responde ÚNICAMENTE con un JSON válido con este formato exacto, sin texto adicional ni bloques de código:
{
  \"nombre\": \"nombre del plato o alimento\",
  \"tipo_comida\": \"Desayuno\" o \"Almuerzo\" o \"Cena\" o \"Snack\",
  \"calorias\": numero entero,
  \"proteinas\": numero decimal,
  \"carbohidratos\": numero decimal,
  \"grasas\": numero decimal,
  \"descripcion\": \"breve descripción de lo que ves en el plato\"
}";

$body = json_encode([
    "contents" => [
        [
            "parts" => [
                [
                    "inline_data" => [
                        "mime_type" => $media_type,
                        "data"      => $imagen_base64
                    ]
                ],
                [
                    "text" => $prompt
                ]
            ]
        ]
    ],
    "generationConfig" => [
        "temperature"     => 0.2,
        "maxOutputTokens" => 500
    ]
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $body,
    CURLOPT_HTTPHEADER     => ["Content-Type: application/json"]
]);

$respuesta = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code !== 200) {
    $err = json_decode($respuesta, true);
    $msg = $err["error"]["message"] ?? "Error al contactar Gemini. Código: $http_code";
    echo json_encode(["error" => $msg]);
    exit;
}

$data = json_decode($respuesta, true);
$texto = trim($data["candidates"][0]["content"]["parts"][0]["text"] ?? "");

if (!$texto) {
    echo json_encode(["error" => "Gemini no devolvió respuesta."]);
    exit;
}

// Limpiar posibles bloques ```json ... ```
$texto = preg_replace('/```json|```/i', '', $texto);
$texto = trim($texto);

// Extraer JSON
preg_match('/\{.*\}/s', $texto, $matches);
if (empty($matches)) {
    echo json_encode(["error" => "No se pudo interpretar la respuesta de la IA."]);
    exit;
}

$resultado = json_decode($matches[0], true);
if (!$resultado) {
    echo json_encode(["error" => "JSON inválido en la respuesta."]);
    exit;
}

header("Content-Type: application/json");
echo json_encode($resultado);