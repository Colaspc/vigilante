<?php
/**
 * Endpoint: Recepcion de Datos de Monitoreo
 * URL: /api/send_data.php
 * 
 * Funcion: Recibe datos del powershell 
 * y los almacena en la base de datos.
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

// 1. Solo permitir metodo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode([
        'success' => false, 
        'message' => 'Solo se permite metodo POST'
    ]));
}

// 2. Validar API Key global
if (!validateApiKey()) {
    http_response_code(401);
    exit(json_encode([
        'success' => false, 
        'message' => 'No autorizado - API Key invalida'
    ]));
}

// 3. Rate Limiting: Maximo 60 envios por minuto 
if (!checkRateLimit($ip, 'send_data', 60, 60)) {
    http_response_code(429);
    exit(json_encode([
        'success' => false, 
        'message' => 'Limite de peticiones excedido'
    ]));
}

// ============================================================
// VALIDACION DE TOKEN DEL EQUIPO
// ============================================================

$headers = getallheaders();
$computerToken = $headers['X-Computer-Token'] ?? '';

if (empty($computerToken)) {
    http_response_code(401);
    exit(json_encode([
        'success' => false, 
        'message' => 'Token de equipo requerido (Header: X-Computer-Token)'
    ]));
}

try {
    $db = getDB();
    
    // Validar token y obtener ID del equipo
    $stmt = $db->prepare('
        SELECT id 
        FROM computers 
        WHERE api_token = ? AND is_active = TRUE
    ');
    $stmt->execute([$computerToken]);
    $computer = $stmt->fetch();
    
    if (!$computer) {
        http_response_code(403);
        exit(json_encode([
            'success' => false, 
            'message' => 'Token invalido o equipo inactivo'
        ]));
    }
    
    $computerId = $computer['id'];
    
    // Actualizar ultima conexion
    $stmt = $db->prepare('UPDATE computers SET last_seen = NOW() WHERE id = ?');
    $stmt->execute([$computerId]);
    
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Token validation error: " . $e->getMessage());
    exit(json_encode([
        'success' => false, 
        'message' => 'Error de validacion'
    ]));
}

// ============================================================
// PROCESAMIENTO DE EVENTOS
// ============================================================

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    exit(json_encode([
        'success' => false, 
        'message' => 'JSON invalido'
    ]));
}

// Normalizar a array de eventos
$eventos = is_array($input) && isset($input[0]) ? $input : [$input];
$procesados = 0;
$errores = 0;

try {
    $db->beginTransaction();
    
    foreach ($eventos as $evento) {
        $tipo = $evento['Evento'] ?? '';
        
        // TIPO 1: Metricas del Sistema (CPU, RAM, Disco)
        if ($tipo === 'METRICAS_SISTEMA') {
            $metricas = json_encode([
                'cpu_uso' => $evento['cpu_uso'] ?? 0,
                'ram_uso' => $evento['ram_uso'] ?? 0,
                'disco_libre_gb' => $evento['disco_libre_gb'] ?? 0,
                'disco_libre_pct' => $evento['disco_libre_pct'] ?? 0,
                'timestamp' => $evento['Timestamp'] ?? date('Y-m-d H:i:s')
            ], JSON_UNESCAPED_UNICODE);
            
            $stmt = $db->prepare('
                INSERT INTO computer_data (computer_id, parametro, created_at) 
                VALUES (?, ?, NOW())
            ');
            
            if ($stmt->execute([$computerId, $metricas])) {
                $procesados++;
            } else {
                $errores++;
            }
        }
        
        // TIPO 2: Keep-Alive (mantener conexion, no guardar)
        elseif ($tipo === 'KEEP_ALIVE') {
            // Solo actualiza last_seen (ya hecho arriba)
            $procesados++;
        } elseif ($tipo === 'SCREENSHOT_TAKEN') {
    $imagenBase64 = $evento['ImagenBase64'] ?? '';

    if (!empty($imagenBase64)) {
        // Decodificar base64 a binario
        $imagenBinario = base64_decode($imagenBase64);

        if ($imagenBinario !== false) {
            // Insertar la nueva captura
            $stmt = $db->prepare('
                INSERT INTO computer_screenshots (computer_id, imagen, mime_type, created_at)
                VALUES (?, ?, "image/jpeg", NOW())
            ');
            if ($stmt->execute([$computerId, $imagenBinario])) {
                $procesados++;

                // Mantener solo las últimas 3 capturas — borrar las más antiguas
                $stmt2 = $db->prepare('
                    DELETE FROM computer_screenshots
                    WHERE computer_id = ?
                    AND id NOT IN (
                        SELECT id FROM (
                            SELECT id FROM computer_screenshots
                            WHERE computer_id = ?
                            ORDER BY created_at DESC
                            LIMIT 3
                        ) AS ultimas
                    )
                ');
                $stmt2->execute([$computerId, $computerId]);
            } else {
                $errores++;
            }
        } else {
            $errores++; // base64 inválido
        }
    }
    // Si no hay imagen, simplemente ignoramos (no es error crítico)
}
        
        // TIPO 3: Eventos de Archivos y Web
        else {
            $stmt = $db->prepare('
                INSERT INTO computer_history 
                (computer_id, evento, sitio, equipo, usuario, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ');
            
            $resultado = $stmt->execute([
                $computerId,
                $tipo,
                $evento['Sitio'] ?? $evento['Ruta'] ?? null,
                $evento['Equipo'] ?? null,
                $evento['Usuario'] ?? null
            ]);
            
            if ($resultado) {
                $procesados++;
            } else {
                $errores++;
            }
        }
    }
    
$db->commit();

    $db->exec('DELETE FROM computer_data WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');
    $db->exec('DELETE FROM computer_history WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');
    $db->exec('DELETE FROM computer_screenshots WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');

    echo json_encode(['success' => true, 'processed' => $procesados, 'errors' => $errores]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}