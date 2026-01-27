# 📚 Guía de Uso: Sistema de Gestión de Usuarios Unificado

## 🎯 Descripción General

Se ha implementado un **sistema unificado de gestión de usuarios** en `usuarios.php` que consolida todas las operaciones de usuario en una sola interfaz intuitiva.

---

## 🔐 Cambios Principales

### ✅ Antes
- Página `register.php` para crear usuarios
- Página `usuarios.php` para gestionar usuarios (cambiar rol, eliminar)
- Contraseñas manuales sin contraseña por defecto
- Sin obligación de cambiar contraseña en primer login

### ✅ Ahora
- Una sola página `usuarios.php` unificada
- Interfaz de **pestañas** (Listar, Crear, Editar)
- **Contraseña temporal automática**: 111111
- **Cambio obligatorio** en primer login
- Mejor UX y seguridad mejorada

---

## 📋 Interfaz Principal: `usuarios.php`

### Acceso
- **URL**: `http://localhost:8889/test/usuarios.php`
- **Permisos**: Solo **admin** puede acceder
- **Redirección**: Si no eres admin → Dashboard

---

## 📑 Pestaña 1: "Listado de Usuarios"

Muestra **tabla completa** de todos los usuarios del sistema.

### Columnas
| Columna | Descripción |
|---------|------------|
| **Usuario** | Nombre de usuario |
| **Email** | Correo electrónico |
| **Rol** | Admin (rojo), Soporte TI (amarillo), Lector (azul) |
| **Estado** | "Cambio de contraseña pendiente" o "Activo" |
| **Acciones** | Botones Editar y Eliminar |

### Acciones Disponibles
- 🔧 **Editar**: Abre pestaña de edición con datos prepoblados
- 🗑️ **Eliminar**: Elimina usuario (con confirmación)
  - ⚠️ No puedes eliminar tu propia cuenta

---

## ➕ Pestaña 2: "Crear Usuario"

Formulario para agregar nuevos usuarios al sistema.

### Campos
```
┌──────────────────────────────────┐
│ Nombre de Usuario *    [_________]│
│ Email                  [_________]│
│ Rol *                  [Dropdown] │
│                         • Lector  │
│                         • Soporte │
│                         • Admin   │
└──────────────────────────────────┘
```

### Proceso de Creación

1. **Ingresar nombre de usuario** (obligatorio, único)
   - Ej: `juan`, `maria`, `carlos`

2. **Email opcional**
   - Ej: `juan@empresa.com`

3. **Seleccionar rol** (obligatorio)
   - **Lector**: Solo vista de dashboard
   - **Soporte TI**: Crear y gestionar tickets/activos
   - **Administrador**: Acceso completo + gestión de usuarios

4. **Hacer clic en "Crear Usuario"**

5. **Confirmación automática**:
   - ✅ Mensaje verde: "Usuario creado exitosamente"
   - 🔑 **Contraseña temporal: 111111**
   - 📌 Usuario marcado como "necesita cambiar contraseña"

### Ejemplo
```
Usuario: pepe_garcia
Email: pepe@company.com
Rol: Soporte TI
→ ✅ Usuario creado. Contraseña temporal: 111111
```

---

## ✏️ Pestaña 3: "Editar Usuario"

Accede por hacer clic en botón "Editar" en listado.

### Campos Editables
```
┌────────────────────────────────────┐
│ Nombre de Usuario    [pepe_garcia] │
│ Email                [pepe@co.com] │
│ Rol                  [Soporte TI]  │
│ Nueva Contraseña     [____________]│
│ (opcional)                         │
└────────────────────────────────────┘
```

### Casos de Uso

#### 📝 Cambiar Rol de Usuario
1. Ir a Listado → "Editar" usuario
2. Cambiar el Rol en el dropdown
3. Dejar "Nueva Contraseña" en blanco
4. Guardar → Usuario mantiene su contraseña actual

#### 🔑 Resetear Contraseña de Usuario
1. Ir a Listado → "Editar" usuario
2. Ingresar nueva contraseña en campo "Nueva Contraseña"
3. Guardar
4. ⚠️ Sistema marca automáticamente: `necesita_cambiar_password = 0`
5. Usuario puede acceder normalmente (sin obligación de cambio)

#### 📧 Actualizar Email
1. Ir a Listado → "Editar" usuario
2. Modificar Email
3. Guardar

#### 🔄 Cambiar Todo
1. Cambiar nombre (❌ nota: esto podría ser riesgoso, evitar en producción)
2. Cambiar email
3. Cambiar rol
4. Opcionalmente nueva contraseña
5. Guardar

---

## 🚀 Flujo de Primer Login: Usuario Nuevo

### Paso a Paso

```
PASO 1: Admin crea usuario "ana"
├─ Usuario: ana
├─ Email: ana@empresa.com
├─ Rol: Lector
└─ ✅ Creado con contraseña temporal: 111111

PASO 2: Ana recibe credenciales
├─ Usuario: ana
├─ Contraseña: 111111
└─ URL: http://localhost:8889/test

PASO 3: Ana accede a login
├─ Ingresa: ana / 111111
├─ ✅ Credenciales correctas
└─ Sistema verifica: ¿necesita_cambiar_password?

PASO 4: Sistema detecta = TRUE
├─ ❌ NO accede a dashboard
├─ 🔄 REDIRECCIONA a cambiar_contrasena.php
└─ Mensaje: "Por seguridad, debe cambiar su contraseña temporal"

PASO 5: Ana cambia su contraseña
├─ Ingresa contraseña actual: 111111
├─ Ingresa nueva contraseña: MiContraseña123
├─ Confirma nueva: MiContraseña123
└─ ✅ Guardar cambios

PASO 6: Sistema actualiza
├─ Hashea nueva contraseña
├─ Marca: necesita_cambiar_password = 0
└─ ✅ REDIRECCIONA a dashboard.php

PASO 7: Ana accede al sistema
├─ Tendrá acceso completo según su rol (Lector)
└─ En futuros logins, simplemente: ana / MiContraseña123
```

---

## 🔒 Página de Cambio de Contraseña

### Acceso
- **URL**: `http://localhost:8889/test/cambiar_contrasena.php`
- **Acceso normal**: Cerrado (redirige a dashboard si ya cambió)
- **Acceso en primer login**: Automático (obliga cambio)

### Validaciones

| Validación | Mensaje de Error |
|-----------|-----------------|
| Contraseña actual incorrecta | "La contraseña actual es incorrecta" |
| Contraseña nueva < 6 caracteres | "La nueva contraseña debe tener al menos 6 caracteres" |
| Las contraseñas no coinciden | "Las contraseñas no coinciden" |
| Campo vacío | "Todos los campos son obligatorios" |

### Diseño
- Gradiente púrpura atractivo
- Campos de contraseña tipo `password` (caracteres ocultos)
- Botón grande y visible
- Mensajes de error y éxito destacados

---

## 📊 Estados de Usuario

### Nuevo Usuario Creado
```
┌─ Usuario: juan
├─ Email: juan@co.com
├─ Rol: Soporte TI
├─ Contraseña: hash(111111)
└─ necesita_cambiar_password: 1 ✓ CAMBIO OBLIGATORIO
```

### Usuario que cambió contraseña
```
┌─ Usuario: juan
├─ Email: juan@co.com
├─ Rol: Soporte TI
├─ Contraseña: hash(nueva_secreta)
└─ necesita_cambiar_password: 0 ✓ ACCESO NORMAL
```

### Usuario antiguo (pre-existente)
```
┌─ Usuario: admin
├─ Email: admin@co.com
├─ Rol: Administrador
├─ Contraseña: hash(original)
└─ necesita_cambiar_password: 0 ✓ NO AFECTADO
```

---

## 🛡️ Características de Seguridad

✅ **Contraseñas hasheadas**: `PASSWORD_BCRYPT` (estándar PHP)
✅ **Validaciones en backend**: No confíes en validación del cliente
✅ **Protección de acceso**: Solo admin puede crear/editar usuarios
✅ **Cambio obligatorio**: Primer login obliga cambio de contraseña
✅ **Emails únicos**: No se permiten usuarios duplicados (por lógica de BD)
✅ **Mensajes seguros**: No revela información sobre usuarios existentes
✅ **Protección de cuenta propia**: No puedes eliminar o modificarte a ti mismo indebidamente

---

## 💡 Tips de Uso

### ✅ Buenas Prácticas
- Usar nombres de usuario descriptivos (ej: `juan.garcia`, `maria.lopez`)
- Cambiar contraseña temporal en primer login (no mantener 111111)
- Guardar contraseñas en gestor de contraseñas seguro
- Usar roles apropiados según responsabilidades
- Revisar lista de usuarios periódicamente
- Eliminar usuarios que ya no usan el sistema

### ❌ Evitar
- Crear usuarios con nombres genéricos (`user1`, `user2`)
- Compartir contraseña entre usuarios
- Usar contraseña 111111 permanentemente
- Dar rol Admin a usuarios que no lo necesitan
- Olvidar email de contacto del usuario

---

## 📞 Preguntas Frecuentes

### ❓ ¿Qué pasa si olvido la contraseña de un usuario?
→ Admin puede editar el usuario e ingresar nueva contraseña

### ❓ ¿Puedo cambiar el nombre de usuario?
→ Sí, en la pestaña Editar, pero evita hacerlo si el usuario ya conoce su usuario

### ❓ ¿Qué es "necesita_cambiar_password"?
→ Flag que indica si usuario debe cambiar contraseña en primer login (1=Sí, 0=No)

### ❓ ¿Por qué se obliga cambiar contraseña en primer login?
→ Por seguridad, la contraseña temporal (111111) es conocida por el admin y debe cambiarse

### ❓ ¿Cuánto duran las contraseñas?
→ No hay expiración implementada. Admin debe resetear si es necesario

### ❓ ¿Qué pasa si un usuario intenta acceder a cambiar_contrasena.php después de cambiar?
→ Sistema redirige automáticamente al dashboard (protección adicional)

---

## 🔧 Información Técnica

### Columnas de BD Usadas
```sql
users.id
users.username
users.password (hash)
users.email
users.role (viewer|tisupport|admin)
users.necesita_cambiar_password (0|1)
```

### Archivos Relacionados
- `usuarios.php` - Interfaz principal unificada
- `cambiar_contrasena.php` - Página de cambio obligatorio
- `includes/login.php` - Lógica de verificación de cambio
- `migration_password_change.php` - Migración de BD

### Seguridad de Contraseñas
```php
// Crear hash
$hash = password_hash("111111", PASSWORD_BCRYPT);

// Verificar
if (password_verify("111111", $hash)) {
    // Correcto
}
```

---

## 📋 Checklist de Implementación

- ✅ Crear usuario con contraseña temporal 111111
- ✅ Nuevo usuario debe cambiar en primer login
- ✅ Cambio se valida y actualiza en BD
- ✅ Admin puede editar/eliminar usuarios
- ✅ Admin puede resetear contraseñas
- ✅ Interfaz unificada con pestañas
- ✅ Mensajes de error y éxito claros
- ✅ Protecciones de seguridad
- ✅ Redirecciones automáticas
- ✅ Backward compatible con usuarios antiguos

---

**Última actualización**: 2025-01-15
**Versión**: 2.0 (Sistema unificado con cambio obligatorio de contraseña)
