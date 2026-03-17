<?php
/**
 * Endpoint: Obtener Configuración del Equipo
 * URL: /api/get_config.php
 *
 * Función: El agente hace POST con su X-Computer-Token y recibe
 * las listas de bloqueos y carpetas configuradas desde el panel.
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/../web/config/db.php';
require_once __DIR__ . '/config/rate_limiter.php'; 
require_once __DIR__ . '/config/security.php';     

header('Content-Type: application/json; charset=utf-8');

$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];

// ============================================================
// VALIDACIONES DE SEGURIDAD
// ============================================================

// 1. Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Solo se permite metodo POST']));
}

// 2. Validar API Key global
if (!validateApiKey()) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'No autorizado - API Key invalida']));
}

// 3. Rate Limiting: 60 peticiones por minuto (mismo que send_data)
if (!checkRateLimit($ip, 'get_config', 60, 60)) {
    http_response_code(429);
    exit(json_encode(['success' => false, 'message' => 'Limite de peticiones excedido']));
}

// ============================================================
// VALIDACIÓN DEL TOKEN DEL EQUIPO
// ============================================================

$headers = getallheaders();
$computerToken = $headers['X-Computer-Token'] ?? '';

if (empty($computerToken)) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Token de equipo requerido (Header: X-Computer-Token)']));
}

try {
    $db = getDB();

    // Verificar token y obtener ID del equipo
    $stmt = $db->prepare('SELECT id FROM computers WHERE api_token = ? AND is_active = TRUE');
    $stmt->execute([$computerToken]);
    $computer = $stmt->fetch();

    if (!$computer) {
        http_response_code(403);
        exit(json_encode(['success' => false, 'message' => 'Token invalido o equipo inactivo']));
    }

    $computerId = $computer['id'];

    // ============================================================
    // OBTENER CONFIGURACIÓN
    // ============================================================

    $stmt = $db->prepare('SELECT bloqueos, carpetas FROM pc_config WHERE computer_id = ?');
    $stmt->execute([$computerId]);
    $config = $stmt->fetch();

    // Si no hay fila todavía, devolver listas vacías (el agente usará sus defaults)
    if (!$config) {
        echo json_encode([
            'success'  => true,
            'bloqueos' => [],
            'carpetas' => []
        ]);
        exit;
    }

    // Convertir texto (una línea = un elemento) a arrays, filtrando líneas vacías
    $bloqueos = array_values(array_filter(
        array_map('trim', explode("\n", $config['bloqueos'] ?? '')),
        fn($l) => $l !== ''
    ));

    $carpetas = array_values(array_filter(
        array_map('trim', explode("\n", $config['carpetas'] ?? '')),
        fn($l) => $l !== ''
    ));

    echo json_encode([
        'success'  => true,
        'bloqueos' => $bloqueos,
        'carpetas' => $carpetas
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("get_config error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno']);
}