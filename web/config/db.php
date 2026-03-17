<?php
/**
 * FUNCION - getDB()
 */

if (!defined('APP_ACCESS')) {
    die('Acceso denegado');
}


function loadEnv($path) {
    if (!file_exists($path)) {
        throw new Exception('Archivo .env no encontrado en: ' . $path);
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {

        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $_ENV[trim($name)] = trim($value);
        }
    }
}


loadEnv(__DIR__ . '/.env');

function getDB() {
    $dsn = 'mysql:host=' . $_ENV['DB_HOST']
         . ';dbname=' . $_ENV['DB_NAME']
         . ';charset=' . $_ENV['DB_CHARSET'];

    $db = new PDO(
        $dsn,
        $_ENV['DB_USER'],
        $_ENV['DB_PASS']
    );

    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    return $db;
}