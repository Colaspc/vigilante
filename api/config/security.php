<?php
/**
 * FUNCION validateApiKey() 
 */

if (!defined('APP_ACCESS')) {
    die('Acceso denegado');
}

function validateApiKey() {
    $headers = getallheaders();
    $apiKey = $headers['X-API-Key'] ?? '';
    
    // Comparación timing-safe para prevenir timing attacks
    return hash_equals($_ENV['API_KEY'], $apiKey);
}