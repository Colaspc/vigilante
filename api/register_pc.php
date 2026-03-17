<?php
/**
 * Endpoint: Activación de Equipos
 * URL: /api/register_pc.php
 * 
 * Función: Activa un equipo previamente creado desde el panel web
 * y devuelve su token de API para comunicaciones futuras.
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/../web/config/db.php';
require_once __DIR__ . '/config/rate_limiter.php'; 
require_once __DIR__ . '/config/security.php';     


header('Content-Type: application/json; charset=utf-8');

// Obtener IP del cliente
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];

// ============================================================
// VALIDACIONES DE SEGURIDAD
// ============================================================

// 1. Solo permitir método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode([
        'success' => false, 
        'message' => 'Solo se permite metodo POST'
    ]));
}

// 2. Validar API Key
if (!validateApiKey()) {
    http_response_code(401);
    exit(json_encode([
        'success' => false, 
        'message' => 'No autorizado - API Key invalida'
    ]));
}

// 3. Rate Limiting: Máximo 5 intentos cada 5 minutos
if (!checkRateLimit($ip, 'register_computer', 5, 300)) {
    http_response_code(429);
    exit(json_encode([
        'success' => false, 
        'message' => 'Demasiados intentos. Espera 5 minutos.'
    ]));
}

// ============================================================
// PROCESAMIENTO DE DATOS
// ============================================================

// Leer JSON del body
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['computer_code'])) {
    http_response_code(400);
    exit(json_encode([
        'success' => false, 
        'message' => 'JSON invalido o incompleto. Se requiere: computer_code'
    ]));
}

$computerCode = strtoupper(trim($input['computer_code']));

// Validar formato del código (8 caracteres alfanuméricos)
if (!preg_match('/^[A-Z0-9]{8}$/', $computerCode)) {
    exit(json_encode([
        'success' => false, 
        'message' => 'Código inválido. Debe ser 8 caracteres alfanuméricos.'
    ]));
}

// ============================================================
// LÓGICA DE REGISTRO
// ============================================================

try {
    $db = getDB();
    
    // Buscar equipo por código
    $stmt = $db->prepare('
        SELECT id, api_token, is_active 
        FROM computers 
        WHERE computer_code = ?
    ');
    $stmt->execute([$computerCode]);
    $computer = $stmt->fetch();
    
    if (!$computer) {
        exit(json_encode([
            'success' => false, 
            'message' => 'Codigo no existe. Verifica el código desde el panel web.'
        ]));
    }
    
    // Verificar si ya está activo
    if ($computer['is_active']) {
        exit(json_encode([
            'success' => false, 
            'message' => 'Este equipo ya esta activo. Si necesitas reconectarlo, usa "Refrescar conexión" desde el panel web.'
        ]));
    }
    
    // Activar equipo
    $stmt = $db->prepare('
        UPDATE computers 
        SET is_active = TRUE
        WHERE id = ?
    ');
    $stmt->execute([$computer['id']]);
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Equipo registrado correctamente',
        'api_token' => $computer['api_token']
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);

        echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()  // ver error real durante desarrollo
    ]);
    //error_log("Data processing error: " . $e->getMessage());
    //echo json_encode([
    //    'success' => false, 
    //   'message' => 'Error al procesar datos'
    //]);
}