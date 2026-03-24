/**
 * MIGRACIÓN: Agregar relación de tipos de dispositivos
 * Versión: 2.0 - Para producción
 * Fecha: 2026-03-24
 * Base de datos: jupiter
 * 
 * INSTRUCCIONES:
 * 1. Accede a phpMyAdmin o tu cliente MySQL
 * 2. Selecciona la base de datos "jupiter"
 * 3. Pega este SQL y ejecuta
 * 4. Verifica que no hay errores
 */

-- =====================================================
-- PASO 1: Verificar que existe la tabla tipos_dispositivos
-- =====================================================
-- SELECT * FROM tipos_dispositivos LIMIT 1;

-- =====================================================
-- PASO 2: Agregar columna tipo_dispositivo_id 
-- =====================================================
ALTER TABLE dispositivos_monitoreo 
ADD COLUMN tipo_dispositivo_id INT DEFAULT NULL AFTER descripcion;

-- =====================================================
-- PASO 3: Agregar Foreign Key
-- =====================================================
ALTER TABLE dispositivos_monitoreo 
ADD CONSTRAINT fk_tipo_dispositivo 
FOREIGN KEY (tipo_dispositivo_id) REFERENCES tipos_dispositivos(id) ON DELETE SET NULL;

-- =====================================================
-- PASO 4: Verificar que la migración fue exitosa
-- =====================================================
-- Mostrar la estructura de la tabla
DESCRIBE dispositivos_monitoreo;

-- Ver las relaciones (Foreign Keys)
SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE TABLE_NAME='dispositivos_monitoreo' AND CONSTRAINT_NAME='fk_tipo_dispositivo';

-- ✅ Si todo es correcto, ambas consultas deben ejecutarse sin errores
