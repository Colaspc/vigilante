<?php
/**
 * Endpoint: get_screenshot.php
 * 
 * Sirve una captura de pantalla desde la BD de forma segura.
 * Solo accesible si el usuario está logueado y la captura pertenece
 * a uno de sus equipos.
 * 
 * URL: get_screenshot.php?id=123
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/db.php'; 

session_start();

// 1. Verificar que el usuario está logueado
if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    exit('No autorizado');
}

// 2. Validar el parámetro id
$screenshotId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$screenshotId || $screenshotId <= 0) {
    http_response_code(400);
    exit('ID inválido');
}

try {
    $db = getDB();

    // 3. Obtener la imagen verificando que pertenece a un equipo del usuario logueado
    //    (JOIN con computers y users para seguridad)
    $stmt = $db->prepare('
        SELECT s.imagen, s.mime_type
        FROM computer_screenshots s
        INNER JOIN computers c ON s.computer_id = c.id
        WHERE s.id = ?
          AND c.user_id = ?
    ');
    $stmt->execute([$screenshotId, $_SESSION['user_id']]);
    $screenshot = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$screenshot) {
        http_response_code(404);
        exit('Captura no encontrada');
    }

    // 4. Servir la imagen directamente
    header('Content-Type: ' . $screenshot['mime_type']);
    header('Cache-Control: private, max-age=300'); // Cache 5 min en el navegador
    echo $screenshot['imagen'];

} catch (PDOException $e) {
    http_response_code(500);
    error_log("Screenshot serve error: " . $e->getMessage());
    exit('Error al obtener la imagen');
}
