# 📋 Migración Completa: Agregar Tipos de Dispositivos

## 🎯 Objetivo
Crear la tabla `tipos_dispositivos` con 6 tipos predefinidos (Switch, NVR, Access Point, Reloj Control, Máquina Virtual, Otro) y agregar la capacidad de clasificar dispositivos de monitoreo por tipo.

---

## 📦 Archivos incluidos

### 1. **migration_tipos_dispositivos_v2.php** (Recomendado) ⭐
   - Opción segura y controlada
   - Crea la tabla desde cero
   - Inserta datos predefinidos
   - Interfaz visual amigable
   - Verifica antes de ejecutar
   - Requiere estar logueado como admin

### 2. **migration_tipos_dispositivos.sql**
   - Script SQL puro y completo
   - Para ejecutar en phpMyAdmin
   - Crea tabla + datos + relaciones
   - Más rápido pero requiere acceso directo a BD

---

## 🚀 Instrucciones de Ejecución

### Opción A: Via PHP (RECOMENDADO)

1. **Sube el archivo**
   ```
   migration_tipos_dispositivos_v2.php
   ```
   Al servidor en la raíz del proyecto

2. **Accede desde el navegador**
   ```
   https://tudominio.com/jupiter/migration_tipos_dispositivos_v2.php
   ```

3. **Inicia sesión como admin** si no lo estás

4. **Verifica el resultado**
   - ✅ Verde = Éxito
   - ℹ️ Azul = Ya ejecutado
   - ❌ Rojo = Error

5. **Resultado esperado:**
   - ✅ Tabla 'tipos_dispositivos' creada
   - ✅ 6 tipos predefinidos insertados
   - ✅ Columna 'tipo_dispositivo_id' agregada a dispositivos_monitoreo
   - ✅ Foreign Key 'fk_tipo_dispositivo' creada

---

### Opción B: Via phpMyAdmin

1. **Accede a phpMyAdmin**
   ```
   https://tudominio.com/phpmyadmin
   ```

2. **Selecciona la base de datos `jupiter`**

3. **Ve a la pestaña SQL**

4. **Pega el contenido de:**
   ```
   migration_tipos_dispositivos.sql
   ```

5. **Haz clic en "Ejecutar"**

6. **Verifica que no hay errores** (aparecerá en verde)

---

## 📋 Qué se crea

### Tabla `tipos_dispositivos`
```sql
id (INT, PRIMARY KEY)
nombre (VARCHAR 100, UNIQUE)
color (VARCHAR 7, ej: #007bff)
icono (VARCHAR 50, ej: bi-diagram-3)
fecha_creacion (TIMESTAMP)
```

### Tipos Predefinidos Insertados

| ID | Nombre | Color | Icono |
|----|--------|-------|-------|
| 1 | Switch | #007bff | bi-diagram-3 |
| 2 | NVR | #e83e8c | bi-camera-video |
| 3 | Access Point | #17a2b8 | bi-wifi |
| 4 | Reloj Control | #ffc107 | bi-clock |
| 5 | Máquina Virtual | #6610f2 | bi-cpu |
| 6 | Otro | #6c757d | bi-device-hdd |

### Modificación a `dispositivos_monitoreo`
```sql
- Nueva columna: tipo_dispositivo_id (INT, DEFAULT NULL)
- Foreign Key: fk_tipo_dispositivo → tipos_dispositivos(id)
```

---

## ✓ Verificación Post-Migración

Después de ejecutar, verifica que todo está correcto:

```sql
-- Verificar tabla creada
SHOW TABLES LIKE 'tipos_dispositivos';

-- Verificar datos
SELECT * FROM tipos_dispositivos;

-- Verificar columna en dispositivos_monitoreo
SHOW COLUMNS FROM dispositivos_monitoreo LIKE 'tipo_dispositivo_id';

-- Verificar Foreign Key
SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
WHERE TABLE_NAME='dispositivos_monitoreo' AND CONSTRAINT_NAME='fk_tipo_dispositivo';
```

Todas las consultas deben retornar resultados.

---

## 🔄 ¿Qué ocurre si ya ejecuté la migración anterior?

No hay problema. Esta migración mejorada:
- ✅ Verifica primero si la tabla existe
- ✅ Si no existe → La crea
- ✅ Si ya existe → No la recrea
- ✅ Verifica si existen datos → No los duplica
- ✅ Crea la columna y Foreign Key si no existen

**Resultado:** Puedes ejecutarla múltiples veces sin problemas.

---

## 🛡️ Seguridad

- ✅ Solo admins pueden ejecutar la migración
- ✅ Requiere session iniciada
- ✅ Verifica antes de cada operación (evita duplicados)
- ✅ Transaccional (todo o nada)
- ✅ Manejo robusto de errores

---

## ⏱️ Duración

**Tiempo de ejecución:** < 1 segundo

---

## 📞 Soporte

Si ocurre algún error:

1. Verifica que tienes acceso admin
2. Comprueba que la conexión a BD es correcta
3. Revisa que no hay conflictos de permisos
4. Intenta nuevamente
5. Si persiste, revisa los logs del servidor MySQL

---

**Versión:** 3.0 - Completa desde cero  
**Fecha:** 2026-03-24  
**Base de datos:** jupiter  
**Estado:** ✅ Producción Ready
