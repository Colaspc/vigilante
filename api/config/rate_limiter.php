<?php

/**
 * FUNCION - checkRateLimit()
 */
if (!defined('APP_ACCESS')) {
    die('Acceso denegado');
}

/**
 * Verifica si una IP/acción ha excedido el límite de peticiones
 * @param string $ip Dirección IP del cliente
 * @param string $action Identificador de la acción (register, send_data, etc)
 * @param int $max Máximo de peticiones permitidas
 * @param int $window Ventana de tiempo en segundos
 * @return bool True si está dentro del límite
 */
function checkRateLimit($ip, $action, $max, $window)
{
    try {
        $db = getDB();
        $key = "{$action}:{$ip}";
        $keyHash = hash('sha256', $key);

        // Contar peticiones recientes
        $stmt = $db->prepare('
            SELECT COUNT(*) AS cnt
            FROM rate_limits 
            WHERE key_hash = ? 
            AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ');
        $stmt->execute([$keyHash, $window]);
        $count = $stmt->fetchColumn();

        if ($count >= $max) {
            return false;
        }

        // Registrar esta petición
        $stmt = $db->prepare('INSERT INTO rate_limits (key_hash, created_at) VALUES (?, NOW())');
        $stmt->execute([$keyHash]);

        // Limpieza periódica (1% de probabilidad)
        if (rand(1, 100) === 1) {
            $db->exec('DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)');
        }

        return true;
    } catch (PDOException $e) {
        error_log("Rate limit error: " . $e->getMessage());
        return true;
    }
}
