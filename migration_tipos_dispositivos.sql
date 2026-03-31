/**
 * MIGRACIÓN COMPLETA: Crear tabla tipos_dispositivos y agregar relación
 * Versión: 3.0 - Para producción (Desde cero)
 * Fecha: 2026-03-24
 * Base de datos: jupiter
 * 
 * INSTRUCCIONES:
 * 1. Accede a phpMyAdmin o tu cliente MySQL
 * 2. Selecciona la base de datos "jupiter"
 * 3. Pega este SQL completo y ejecuta
 * 4. Verifica que no hay errores
 */

-- =====================================================
-- PASO 1: Crear tabla tipos_dispositivos
-- =====================================================
CREATE TABLE IF NOT EXISTS tipos_dispositivos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    color VARCHAR(7) NOT NULL DEFAULT '#6c757d',
    icono VARCHAR(50) NOT NULL DEFAULT 'bi-device-hdd',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- PASO 2: Insertar tipos de dispositivos predefinidos
-- =====================================================
INSERT INTO tipos_dispositivos (nombre, color, icono) VALUES 
    ('Switch', '#007bff', 'bi-diagram-3'),
    ('NVR', '#e83e8c', 'bi-camera-video'),
    ('Access Point', '#17a2b8', 'bi-wifi'),
    ('Reloj Control', '#ffc107', 'bi-clock'),
    ('Máquina Virtual', '#6610f2', 'bi-cpu'),
    ('Otro', '#6c757d', 'bi-device-hdd')
ON DUPLICATE KEY UPDATE id=id;

-- =====================================================
-- PASO 3: Agregar columna tipo_dispositivo_id a dispositivos_monitoreo
-- =====================================================
ALTER TABLE dispositivos_monitoreo 
ADD COLUMN IF NOT EXISTS tipo_dispositivo_id INT DEFAULT NULL AFTER descripcion;

-- =====================================================
-- PASO 4: Agregar Foreign Key
-- =====================================================
ALTER TABLE dispositivos_monitoreo 
ADD CONSTRAINT IF NOT EXISTS fk_tipo_dispositivo 
FOREIGN KEY (tipo_dispositivo_id) REFERENCES tipos_dispositivos(id) ON DELETE SET NULL;

-- =====================================================
-- PASO 5: Verificar que la migración fue exitosa
-- =====================================================
-- Mostrar la estructura de la tabla tipos_dispositivos
DESCRIBE tipos_dispositivos;

-- Mostrar los tipos creados
SELECT * FROM tipos_dispositivos;

-- Ver la tabla dispositivos_monitoreo con su nueva columna
DESCRIBE dispositivos_monitoreo;

-- Ver las relaciones (Foreign Keys)
SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE TABLE_NAME='dispositivos_monitoreo' AND CONSTRAINT_NAME='fk_tipo_dispositivo';

-- ✅ Si todo es correcto, todas las consultas deben ejecutarse sin errores
