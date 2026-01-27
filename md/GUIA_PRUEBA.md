# 🧪 Guía de Prueba - Sistema de Perfiles y Búsqueda

## 🚀 Pasos para Probar las Nuevas Funcionalidades

### Paso 1: Acceder al Sistema
1. Abre `localhost:8889/test` en tu navegador
2. Inicia sesión con cualquier usuario (ej: `pablo.orellana` / `123456`)
3. Te llevarán al dashboard

### Paso 2: Acceder a Tu Perfil
**Opción A - Por Navbar:**
1. Click en tu nombre en la esquina superior derecha
2. Select "Mi Perfil" del dropdown
3. Se abre `perfil_usuario.php` con tu perfil

**Opción B - Por Sidebar:**
1. Click en "Mi Perfil" en el sidebar izquierdo
2. Se abre tu perfil

**Opción C - Acceso Directo:**
1. URL: `perfil_usuario.php?username=pablo.orellana`
2. Reemplaza `pablo.orellana` con tu usuario

### Paso 3: Explorar Secciones del Perfil
Tu perfil tiene 3 pestañas:

#### 📋 **Tickets Creados**
- Click en la pestaña "Tickets Creados"
- Verás todos los tickets que creaste
- Prueba el buscador escribiendo un número o título
- Click en cualquier ticket para ver detalles

#### 💬 **Mencionado En**
- Click en la pestaña "Mencionado En"
- Verás tickets donde apareces mencionado
- Prueba buscando por título
- (Si no hay resultados, agrega comentarios mencionando tu usuario)

#### 📦 **Activos**
- Click en la pestaña "Activos"
- Verás tus activos asignados
- Prueba buscando por nombre o código
- Click en cualquier activo para ver detalles

### Paso 4: Búsqueda por Usuario
Prueba buscar otros usuarios:

**Desde Tickets:**
1. Ir a "Tickets"
2. En el buscador, escribe: `#nombre.usuario`
3. Click "Buscar"
4. Verás los tickets de ese usuario

**Ejemplo:**
- Si tienes usuarios: pablo.orellana, juan.garcia, maria.lopez
- Escribe: `#juan.garcia`
- Resultado: Tickets de Juan

**Desde Búsqueda Global:**
1. Sidebar → "Búsqueda"
2. Escribe: `#juan.garcia`
3. Click "Buscar"
4. Verás:
   - Tarjeta del usuario
   - Sus tickets
   - Sus activos

### Paso 5: Búsqueda por Código de Activo
Prueba buscar activos por código:

**Desde Activos:**
1. Ir a "Activos"
2. En el buscador, escribe: `AK790001` (o el código que tengas)
3. Click "Buscar"
4. Resultado: Solo ese activo

**Desde Búsqueda Global:**
1. Sidebar → "Búsqueda"
2. Escribe: `AK790001`
3. Click "Buscar"
4. Verás la tarjeta del activo

### Paso 6: Búsqueda por Ticket
Prueba buscar tickets específicos:

**Desde Búsqueda Global:**
1. Sidebar → "Búsqueda"
2. Escribe: `DCD000001` (o `#DCD000001`)
3. Click "Buscar"
4. Resultado: Ese ticket específico

### Paso 7: Ver Perfil de Otro Usuario
1. Sidebar → "Búsqueda"
2. Escribe: `#otro.usuario`
3. Click "Buscar"
4. En resultados, verás su tarjeta de usuario
5. Click en la tarjeta
6. Se abre el perfil de ese usuario

**O acceso directo:**
- URL: `perfil_usuario.php?username=otro.usuario`

### Paso 8: Ver Detalles de Activo Mejorados
1. Desde Activos o búsqueda → Click en activo
2. Verás página mejorada con:
   - Header con gradient y nombre del activo
   - Secciones organizadas
   - Toda la información visible
   - Botones de acción

**O acceso directo:**
- Por ID: `ver_activo.php?id=5`
- Por código: `ver_activo.php?id=AK790001`
- Por RFK: `ver_activo.php?id=RFK123`

---

## 📋 Checklist de Prueba

### ✅ Funcionalidad Básica
- [ ] Puedo acceder a mi perfil desde navbar
- [ ] Puedo acceder a mi perfil desde sidebar
- [ ] Mi perfil muestra los 3 tabs
- [ ] Puedo cambiar entre tabs
- [ ] Cada tab tiene un buscador

### ✅ Tickets Creados
- [ ] Veo mis tickets creados
- [ ] El buscador filtra por número
- [ ] El buscador filtra por título
- [ ] La paginación funciona
- [ ] Click en ticket me lleva a detalles

### ✅ Mencionado En
- [ ] Veo tickets donde me mencionan
- [ ] El buscador funciona
- [ ] La paginación funciona

### ✅ Activos
- [ ] Veo mis activos
- [ ] El buscador filtra
- [ ] La paginación funciona
- [ ] Click en activo me lleva a detalles

### ✅ Búsqueda por Usuario (#usuario)
- [ ] En Tickets: `#usuario` funciona
- [ ] En Búsqueda Global: `#usuario` funciona
- [ ] Muestra usuario encontrado
- [ ] Muestra sus tickets
- [ ] Muestra sus activos

### ✅ Búsqueda por Activo (AK79XXXX)
- [ ] En Activos: `AK79XXXX` funciona
- [ ] En Búsqueda Global: `AK79XXXX` funciona
- [ ] Detección automática de código
- [ ] Muestra ese activo específico
- [ ] No es case-sensitive

### ✅ Búsqueda Global
- [ ] Puedo acceder desde sidebar
- [ ] Busca por usuario (#usuario)
- [ ] Busca por activo (AK79XXXX)
- [ ] Busca por ticket (#DCD)
- [ ] Busca por texto libre
- [ ] Resultados organizados por tipo
- [ ] Links funcionales en resultados

### ✅ Página de Activo Mejorada
- [ ] Se ve con diseño nuevo
- [ ] Muestra toda la información
- [ ] Puedo acceder por ID
- [ ] Puedo acceder por código
- [ ] Puedo acceder por RFK
- [ ] Fallas activas se ven destacadas
- [ ] Botones funcionan (Volver, Editar)

### ✅ Interfaz y Navegación
- [ ] Dark mode funciona
- [ ] Dark mode se aplica en páginas nuevas
- [ ] Responsive en móvil
- [ ] Navbar dropdown funciona
- [ ] Sidebar links funcionan
- [ ] Paginación funciona correctamente
- [ ] Colores son consistentes

### ✅ Seguridad y Permisos
- [ ] No logueado → Redirige a login
- [ ] Viewer no ve activos (solo tickets)
- [ ] Admin puede ver todo
- [ ] Soporte TI puede ver todo
- [ ] Datos escapados (sin HTML crudo)

---

## 🐛 Problemas Comunes y Soluciones

### Problema: No aparecen resultados en búsqueda
**Solución:**
1. Verifica que haya datos en BD
2. Intenta búsqueda sin patrón especial (solo texto)
3. Revisa que el usuario exista
4. Verifica permisos

### Problema: Página en blanco
**Solución:**
1. Refresca (Ctrl+F5)
2. Verifica que esté logueado
3. Revisa la URL
4. Comprueba la consola (F12)

### Problema: Dark mode no funciona
**Solución:**
1. Borra localStorage: `localStorage.clear()`
2. Refresca la página
3. Click en "Oscuro" del sidebar

### Problema: No veo "Mi Perfil" en navbar
**Solución:**
1. Refresca la página
2. Verifica que el navbar.php esté actualizado
3. Revisa que estés logueado

### Problema: Búsqueda de usuario no funciona
**Solución:**
1. Asegúrate de escribir: `#username` (con #)
2. Verifica el username correcto
3. Usuario debe existir en BD

### Problema: Búsqueda de activo no funciona
**Solución:**
1. Usa formato: `AK79XXXX` (sin espacios)
2. Verifica que el código sea correcto
3. Solo funciona con patrón exacto

---

## 📊 Datos de Prueba Sugeridos

Si necesitas datos de prueba:

### Crear usuarios:
```sql
INSERT INTO users (username, password, role, email, necesita_cambiar_password) 
VALUES 
('pablo.orellana', [hash], 'admin', 'pablo@test.com', 0),
('juan.garcia', [hash], 'tisupport', 'juan@test.com', 0),
('maria.lopez', [hash], 'viewer', 'maria@test.com', 0);
```

### Crear tickets:
- Algunos creados por pablo
- Algunos creados por juan
- Algunos por maria

### Crear activos:
- Algunos de pablo
- Algunos de juan
- Algunos sin asignar

### Agregar menciones:
En comentarios, escribe: `@pablo.orellana para que aparezca en "Mencionado En"`

---

## 🎯 Prueba de Rendimiento

Tiempo esperado para operaciones:

| Operación | Tiempo Esperado |
|-----------|-----------------|
| Cargar perfil | < 500ms |
| Buscar usuario | < 300ms |
| Buscar activo | < 200ms |
| Cambiar tab | < 100ms |
| Ir a siguiente página | < 300ms |

---

## 📞 Reporte de Problemas

Si encuentras problemas:

1. **Abre el navegador (F12)** → Console
2. Busca errores en rojo
3. Copia el error
4. Verifica:
   - ¿Está logueado?
   - ¿Tiene permisos?
   - ¿URL está correcta?
   - ¿Datos existen en BD?

---

**¡Listo para probar!** 🎉

Diviértete explorando el nuevo sistema de perfiles y búsqueda.
