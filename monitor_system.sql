-- ============================================================================
-- SISTEMA DE MONITOREO DE PCS
-- Script de creación de base de datos
-- ============================================================================

-- Eliminar base de datos si existe
DROP DATABASE IF EXISTS monitoring_system;

-- Crear base de datos
CREATE DATABASE monitoring_system 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Seleccionar la base de datos
USE monitoring_system;

-- ============================================================================
-- TABLA: users
-- Almacena los usuarios que pueden acceder al sistema web
-- ============================================================================

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY, -- ID único de cada usuario
    nombre VARCHAR(100) NOT NULL, -- Nombre del usuario
    apellidos VARCHAR(100) NOT NULL, -- Apellidos del usuario
    email VARCHAR(255) NOT NULL UNIQUE CHECK (email REGEXP '^[^@]+@[^@]+\.[^@]+$'), -- Email solo puede haber 1 
    password_hash VARCHAR(255) NOT NULL, -- Contraseña del usuario cifrada
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Cuando se ha creado el registro
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, -- Cuando se ha actualizado un dato
    
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: computers
-- Almacena información de cada PC registrado
-- ============================================================================

CREATE TABLE computers (
    id INT AUTO_INCREMENT PRIMARY KEY, -- ID numérico interno del PC (1, 2, 3...)
    user_id INT NOT NULL, -- Referencia a la tabla users (FK)
    computer_code VARCHAR(8) NOT NULL UNIQUE CHECK (computer_code REGEXP '^[A-Z0-9]{8}$'), -- El código que genera la web que se escribe en el ordenador (ej: A7K9M2X1)
    computer_name VARCHAR(100) NOT NULL, -- Nombre descriptivo del PC
    api_token VARCHAR(64) NOT NULL UNIQUE, -- Para confirmar el token que envía el ordenador con la API para que nadie más pueda usar la API
    token_activated_at TIMESTAMP NULL DEFAULT NULL, -- Cuando se activo el token
    is_active BOOLEAN DEFAULT FALSE, -- Saber si el ordenador está registrado/activo
    last_seen TIMESTAMP NULL DEFAULT NULL, -- La última vez que se conectó
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Cuando se creó el registro
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, -- Cuando se actualizó
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, -- Si se elimina un usuario automáticamente se elimina lo asociado con el ordenador
    
    INDEX idx_user_id (user_id),
    INDEX idx_computer_code (computer_code), 
    INDEX idx_api_token (api_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Crear trigger para actualizar token_activated_at
DELIMITER //

CREATE TRIGGER set_token_activated_at
BEFORE UPDATE ON computers
FOR EACH ROW
BEGIN
    IF NEW.is_active = TRUE AND OLD.is_active = FALSE THEN
        SET NEW.token_activated_at = CURRENT_TIMESTAMP;
    END IF;
END//

DELIMITER ;



-- ============================================================================
-- TABLA: computer_data
-- Almacena los datos enviados por cada PC
-- ============================================================================

CREATE TABLE computer_data (
    id INT AUTO_INCREMENT PRIMARY KEY, -- ID único del registro de datos
    computer_id INT NOT NULL, -- Esta va referenciada con la tabla computers (usando el ID numérico, NO el código)
    parametro JSON NOT NULL, -- Esta simplemente es la info, se pueden añadir más columnas para añadir más datos
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Cuando se envió el dato
    FOREIGN KEY (computer_id) REFERENCES computers(id) ON DELETE CASCADE, -- Si se elimina un PC, se eliminan todos sus datos
    INDEX idx_computer_id (computer_id), -- Para buscar datos de un PC específico
    INDEX idx_created_at (created_at) -- Con este al ser una fecha puede decir que te muestre el que su fecha sea de cierto tiempo a cierto tiempo y rápido
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: computer_history
-- Registra acciones importantes (logs de auditoría)
-- ============================================================================

CREATE TABLE computer_history (
    id INT AUTO_INCREMENT PRIMARY KEY, -- ID único del registro de historial
    computer_id INT NOT NULL, -- Referencia al PC (ID numérico)
    evento VARCHAR(255) NOT NULL, 
    sitio VARCHAR(255) NULL,
    metodo VARCHAR(255) NULL,  
    equipo VARCHAR(255) NULL,
    usuario VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Cuando ocurrió la acción
    
    FOREIGN KEY (computer_id) REFERENCES computers(id) ON DELETE CASCADE, -- Si se elimina un PC, se elimina su historial
    
    INDEX idx_computer_id (computer_id), -- Para buscar historial de un PC
    INDEX idx_evento (evento), -- Para filtrar por tipo de evento
    INDEX idx_created_at (created_at) -- Para buscar por fecha
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: computer_screenshots
-- Guarda las últimas 3 capturas de pantalla por equipo 
-- ============================================================================

CREATE TABLE computer_screenshots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    computer_id INT NOT NULL,
    imagen LONGBLOB NOT NULL,           -- Imagen en binario (viene como base64, se guarda en BLOB)
    mime_type VARCHAR(20) DEFAULT 'image/jpeg',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (computer_id) REFERENCES computers(id) ON DELETE CASCADE,

    INDEX idx_computer_id (computer_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- ============================================================================
-- TABLA: rate_limits
-- Control de tasa de peticiones por IP y endpoint
-- ============================================================================

CREATE TABLE rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key_hash VARCHAR(64) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, 

    INDEX idx_hash_time (key_hash, created_at)    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: pc_config
-- Datos de configuración de equipos
-- ============================================================================

CREATE TABLE pc_config (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    computer_id INT  NOT NULL,
    bloqueos    TEXT NOT NULL DEFAULT '',   -- dominios, uno por línea (facebook, tiktok…)
    carpetas    TEXT NOT NULL DEFAULT '',   -- rutas Windows, una por línea
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (computer_id) REFERENCES computers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_computer (computer_id)   -- máximo 1 fila por equipo
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- DATOS DE PRUEBA
-- ============================================================================


INSERT INTO users (nombre, apellidos, email, password_hash) VALUES
('Admin', 'Prueba', 'a@a.a', '$2y$10$d0FZza23OnJ1RBOSfCHuwO3c38sBLt5j9UEVuDrwmbtHhz.cjDWXO');

INSERT INTO computers (user_id, computer_code, computer_name, api_token, is_active) VALUES
(1, 'TEST0001', 'PC Prueba', 'test_token_1234567890abcdef1234567890abcdef1234567890abcdef12', TRUE);


SHOW TABLES;
