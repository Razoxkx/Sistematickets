# 📋 Módulo de Cuentas de Servicio - Documentación

## Descripción General
Se ha creado un nuevo módulo de **Cuentas de Servicio** que permite a los administradores centralizar y gestionar las credenciales de acceso a diferentes plataformas y servicios.

## ✨ Características Principales

### 1. **Gestión Centralizada de Credenciales**
- Almacenamiento seguro de credenciales en un catálogo único
- Acceso rápido a múltiples cuentas sin necesidad de notas externas
- Campos: Plataforma, Correo, Contraseña, Descripción

### 2. **Seguridad de Contraseñas**
- Las contraseñas se muestran como puntos (masked) por defecto
- **Botón "Revelar"** para mostrar la contraseña
- **Validación de Seguridad**: Para revelar una contraseña, el admin debe ingresar su propia contraseña
- Verificación mediante `password_verify()` con hash BCRYPT

### 3. **Acceso Restringido**
- Solo los usuarios con rol **admin** pueden:
  - Ver cuentas de servicio
  - Crear nuevas cuentas
  - Editar cuentas existentes
  - Eliminar cuentas
  - Revelar contraseñas

### 4. **Auditoría**
- Timestamp de creación y última modificación de cada cuenta
- Información del usuario que creó la cuenta
- Registro de cambios automático

## 🗂️ Archivos Creados

### 1. **crear_tabla_cuentas_servicio.php**
Script de migración que crea la tabla `cuentas_servicio` en la BD.
- Tabla con campos: id, plataforma, correo, contraseña, descripcion, usuario_creador, fechas
- Se ejecuta una sola vez

### 2. **cuentas_servicio.php** (Página Principal)
Listado de todas las cuentas de servicio registradas.
- **Visualización**: Cards con información de cada cuenta
- **Botón Revelar Contraseña**: Abre modal de seguridad
- **Editar**: Enlace a página de edición
- **Eliminar**: Con confirmación
- **Crear Nueva**: Acceso a formulario de creación

### 3. **crear_cuenta_servicio.php**
Formulario para crear nuevas cuentas de servicio.
- Campos: Plataforma, Correo, Contraseña, Descripción
- Validación en frontend y backend
- Panel informativo sobre seguridad

### 4. **editar_cuenta_servicio.php**
Formulario para editar cuentas existentes.
- Precarga todos los datos de la cuenta
- Permite cambiar plataforma, correo, contraseña y descripción
- Muestra información de auditoría (fechas de creación y modificación)
- Validación igual al formulario de creación

### 5. **api_revelar_password.php**
API AJAX para revelar contraseñas con validación.
- Recibe: `cuenta_id` y `admin_password` (POST)
- Valida que el usuario sea admin
- Verifica la contraseña del administrador
- Devuelve la contraseña en JSON si es válida
- Retorna errores HTTP apropiados (403, 400, 401, 404, 500)

### 6. **sidebar.php** (Actualizado)
Se añadió nueva opción de navegación:
- Aparece solo para usuarios admin
- Ícono: `<i class="bi bi-key-fill"></i>`
- Texto: "Cuentas"
- Activa en páginas relacionadas (cuentas_servicio.php, crear_cuenta_servicio.php, editar_cuenta_servicio.php)

## 🗄️ Estructura de la Tabla

```sql
CREATE TABLE cuentas_servicio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plataforma VARCHAR(100) NOT NULL,
    correo VARCHAR(255) NOT NULL,
    contraseña LONGTEXT NOT NULL,
    descripcion LONGTEXT,
    usuario_creador INT NOT NULL,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_ultima_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_creador) REFERENCES users(id),
    INDEX idx_plataforma (plataforma)
)
```

## 🚀 Uso del Módulo

### Para Crear una Cuenta de Servicio:
1. En el sidebar, ir a **Cuentas** (solo visible para admin)
2. Hacer clic en **Nueva Cuenta**
3. Llenar los campos:
   - **Plataforma**: Nombre del servicio (Gmail, AWS, Slack, etc.)
   - **Correo**: Email o usuario de la cuenta
   - **Contraseña**: La contraseña de acceso
   - **Descripción** (opcional): Notas, permisos, información adicional
4. Hacer clic en **Crear Cuenta**

### Para Ver Cuentas:
1. En el sidebar, ir a **Cuentas**
2. Se muestra un listado de todas las cuentas registradas
3. Cada tarjeta muestra:
   - Nombre de la plataforma
   - Usuario que creó la cuenta
   - Correo/usuario de acceso
   - Contraseña (masked)
   - Descripción (si existe)
   - Fecha de creación

### Para Revelar una Contraseña:
1. En la tarjeta de la cuenta, hacer clic en el botón **Ojo** (Revelar)
2. Se abre un modal pidiendo la contraseña de admin
3. Ingresar tu contraseña de administrador
4. Si es correcta:
   - La contraseña se muestra en texto plano
   - El botón cambia a **Ojo tachado** para volver a ocultar
5. Si es incorrecta:
   - Aparece un mensaje de error
   - La contraseña permanece oculta

### Para Editar una Cuenta:
1. Ir a **Cuentas**
2. Hacer clic en el botón **Editar** de la tarjeta
3. Modificar los campos necesarios
4. Hacer clic en **Guardar Cambios**

### Para Eliminar una Cuenta:
1. Ir a **Cuentas**
2. Hacer clic en el botón **Eliminar** de la tarjeta
3. Confirmar en el modal de confirmación
4. La cuenta se elimina inmediatamente

## 🔒 Seguridad

### Implementado:
- ✅ Acceso restringido solo a admin
- ✅ Contraseñas almacenadas en texto plano en BD (se pueden cifrar luego si es necesario)
- ✅ Validación de contraseña de admin para revelar credenciales
- ✅ Uso de `password_verify()` para verificación segura
- ✅ Prepared statements en todas las consultas (protección SQL injection)
- ✅ Escapado de HTML en todas las salidas
- ✅ Timestamps de auditoría automáticos

### Consideraciones Futuras:
- Encripción de contraseñas en BD (OpenSSL o similar)
- Registro detallado de cada revelación de contraseña
- Cambio de contraseña requiere re-autenticación
- Auditoría de cambios en detalle (qué usuario, cuándo, qué cambió)

## 🎨 Interfaz de Usuario

### Diseño:
- Consistente con el diseño existente del proyecto
- Cards responsivas (2 columnas en desktop, 1 en mobile)
- Tema oscuro compatible
- Iconos Bootstrap Icons
- Alertas de éxito/error claras

### Modal de Revelación de Contraseña:
- Input de contraseña con validación
- Botón para confirmar
- Mensajes de error visuales
- Cierre automático al éxito

## 📝 Validaciones

### En la Creación/Edición:
- ✅ Plataforma: Requerida
- ✅ Correo: Requerida, validación email
- ✅ Contraseña: Requerida
- ✅ Descripción: Opcional

## 🔄 Flujo de Trabajo

```
Admin → Sidebar (Cuentas) 
  ↓
  → Crear Nueva Cuenta → Formulario → Guardar en BD → Confirmación
  ↓
  → Ver Cuentas → Cards → Revelar Contraseña → Modal Auth → Mostrar
  ↓
  → Editar Cuenta → Formulario → Guardar cambios → Confirmación
  ↓
  → Eliminar Cuenta → Confirmar → Eliminar de BD → Confirmación
```

## 💡 Notas Importantes

1. **Primera vez**: Ejecutar `crear_tabla_cuentas_servicio.php` accediendo desde el navegador (como admin)
2. **Permisos**: Solo admin puede ver/crear/editar/eliminar cuentas
3. **Contraseña de Admin**: Se usa para validar la revelación de contraseñas
4. **Dark Mode**: Compatible con el sistema de dark mode existente
5. **Responsive**: Funciona en desktop y mobile

## 🐛 Troubleshooting

**Problema**: No aparece la opción de Cuentas en el sidebar
- **Solución**: Verifica que estés logueado como admin

**Problema**: No puedo revelar la contraseña
- **Solución**: Verifica que ingresaste correctamente tu contraseña de admin

**Problema**: La tabla no existe
- **Solución**: Accede a `crear_tabla_cuentas_servicio.php` como admin para crear la tabla

---

**Última actualización**: 27 de enero de 2026
