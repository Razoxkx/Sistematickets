# 🔐 Instrucciones de Instalación - Cuentas de Servicio

## Paso 1: Crear la Tabla en la Base de Datos

### Opción A: Vía Navegador (Recomendado)
1. Inicia sesión en MAMP como administrador
2. Navega a: `http://localhost:8889/test/crear_tabla_cuentas_servicio.php`
3. Deberías ver el mensaje: **"✅ Tabla cuentas_servicio creada correctamente"**

### Opción B: Vía Terminal (si la opción A no funciona)
```bash
# Asegúrate de que MAMP está corriendo
# Luego ejecuta desde el directorio del proyecto:
mysql -h localhost -u root -p"r652Is-scVT1HX3@" test < /Applications/MAMP/htdocs/Dev/proyecto1/sql/cuentas_servicio.sql
```

## Paso 2: Verificar que el Módulo está Disponible

1. Inicia sesión como **admin**
2. En el sidebar, deberías ver una nueva opción: **"Cuentas"** con un ícono de llave 🔑
3. Haz clic para ir a la página principal de Cuentas de Servicio

## Paso 3: Crear tu Primera Cuenta de Servicio

1. Haz clic en el botón **"Nueva Cuenta"**
2. Llena los formularios:
   - **Plataforma**: Ej: "Gmail", "AWS", "GitHub"
   - **Correo**: Tu email o usuario
   - **Contraseña**: La contraseña de acceso
   - **Descripción**: Información adicional (opcional)
3. Haz clic en **"Crear Cuenta"**

## Paso 4: Probar la Función de Revelar Contraseña

1. En la lista de cuentas, encuentra la que acabas de crear
2. Haz clic en el botón **Ojo** (Revelar)
3. En el modal que aparece, ingresa tu contraseña de admin
4. Si es correcta, verás la contraseña en texto plano
5. Haz clic en el botón Ojo tachado para volver a ocultarla

---

## ⚙️ Configuración Recomendada

### Mejoras de Seguridad (Opcionales)

Si deseas mejorar la seguridad, puedes:

1. **Encriptar contraseñas en la BD**:
   - Usar OpenSSL para cifrar/descifrar
   - Implementar en `api_revelar_password.php`

2. **Registrar accesos**:
   - Crear tabla `logs_cuentas_servicio`
   - Registrar cada revelación de contraseña

3. **Agregar logs de cambios**:
   - Crear tabla `historial_cuentas_servicio`
   - Similar a cómo se hace en procedimientos

---

## 🧪 Pruebas

### Verificar Acceso Restringido
- Intenta acceder a `cuentas_servicio.php` como `viewer` o `tisupport`
- Deberías ser redirigido al dashboard

### Verificar Validación de Contraseña
1. Crea una cuenta de prueba
2. Intenta revelar con contraseña incorrecta
3. Deberías ver: "Contraseña de administrador incorrecta"
4. Intenta con la correcta
5. Deberías ver la contraseña revelada

---

## 📊 Estructura de Carpetas

```
proyecto1/
├── cuentas_servicio.php                    # Página principal (listado)
├── crear_cuenta_servicio.php               # Formulario crear
├── editar_cuenta_servicio.php              # Formulario editar
├── api_revelar_password.php                # API para revelar contraseña
├── crear_tabla_cuentas_servicio.php        # Script de migración
├── includes/
│   └── sidebar.php                         # ACTUALIZADO: Agregada opción de Cuentas
└── md/
    └── CUENTAS_SERVICIO.md                 # Documentación completa
```

---

## 🆘 Troubleshooting

### "La tabla no existe"
- Ejecuta `crear_tabla_cuentas_servicio.php` desde el navegador
- Verifica que tengas permisos de admin

### "No aparece la opción de Cuentas en el sidebar"
- Verifica estar logueado como admin
- Recarga la página (F5 o Cmd+R)
- Limpia el cache del navegador

### "Error al revelar contraseña"
- Verifica que ingresaste correctamente tu contraseña de admin
- Asegúrate de estar logueado como el mismo usuario

### "Erro de conexión a BD"
- Verifica que MAMP esté corriendo
- Revisa las credenciales en `includes/config.php`

---

## 📝 Próximos Pasos (Sugerencias)

1. ✅ Crear primeras cuentas de servicio
2. ⏳ Encriptar contraseñas en BD (futuro)
3. ⏳ Agregar registro detallado de accesos (futuro)
4. ⏳ Integración con notificaciones (futuro)

---

**Instalación completada exitosamente!** 🎉
