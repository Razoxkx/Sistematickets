# 🎯 Sistema de Perfiles y Búsqueda Global

## Nuevas Características Implementadas

### 1. **Página de Perfil de Usuario** (`perfil_usuario.php`)

Cada usuario ahora tiene un perfil completo con tres secciones principales:

#### **Acceder al Perfil:**
- Desde el **Navbar**: Click en tu nombre → "Mi Perfil"
- Desde el **Sidebar**: Click en "Mi Perfil"
- URL directa: `perfil_usuario.php?username=pablo.orellana`

#### **Secciones del Perfil:**

##### 📋 **Tickets Creados**
- Muestra todos los tickets que el usuario ha creado (sin cerrar)
- Incluye: Número, título, estado, responsable
- Tiene buscador para filtrar por: ticket number, título o descripción

##### 💬 **Mencionado En**
- Muestra todos los tickets donde el usuario fue mencionado en comentarios
- Busca automáticamente menciones con `@usuario` en los comentarios
- Buscador para filtrar resultados

##### 📦 **Activos**
- Muestra todos los activos asignados al usuario
- Información: RFK, tipo, fabricante, ubicación
- Buscador por nombre o número de activo

### 2. **Búsqueda Global Mejorada** (`busqueda_global.php`)

Nueva página de búsqueda unificada para encontrar todo rápidamente.

#### **Acceder:**
- Desde el **Sidebar**: Click en "Búsqueda"
- URL directa: `busqueda_global.php?q=búsqueda`

#### **Tipos de Búsqueda:**

| Patrón | Ejemplo | Resultado |
|--------|---------|-----------|
| **Número de Ticket** | `#DCD000001` | Busca ticket específico |
| **Nombre de Usuario** | `#pablo.orellana` | Busca usuario y sus datos |
| **Número de Activo** | `AK79XXXX` | Busca activo por número |
| **Texto Libre** | `servidor` | Busca en títulos y descripciones |

### 3. **Búsqueda en Listados**

#### **En Tickets** (`tickets.php`)
- Búsqueda normal: Por número, título o solicitante
- **Búsqueda por usuario**: `#pablo.orellana` → Muestra tickets creados o donde es responsable

#### **En Activos** (`activos.php`)
- Búsqueda normal: Por RFK, título, ubicación, etc.
- **Búsqueda por código**: `AK790001` → Detección automática y búsqueda rápida

### 4. **Página de Detalles de Activo** (`ver_activo.php`)

Mejorada para mostrar toda la información de forma clara:

#### **Características:**
- ✅ Buscar por ID o número de activo (AK79XXXX)
- ✅ Información completa organizada en secciones
- ✅ Visualización de fallas activas (en alerta roja)
- ✅ Botones para editar o volver

#### **Acceder:**
- Desde listado de activos → Click en "Ver"
- URL por ID: `ver_activo.php?id=5`
- URL por código: `ver_activo.php?id=AK790001`

---

## 📚 Ejemplos de Uso

### Caso 1: Ver todos los tickets que creó Pablo
1. **Opción A**: Navbar → "Mi Perfil" (muestra tu perfil)
2. **Opción B**: Sidebar → "Búsqueda" → Escribe `#pablo.orellana`
3. **Opción C**: Tickets → Busca `#pablo.orellana` → Muestra sus tickets

**Resultado**: Lista de todos los tickets creados por Pablo

---

### Caso 2: Buscar un activo específico
1. Sidebar → "Búsqueda"
2. Escribe el código: `AK790001`
3. Click en "Buscar"

**Resultado**: Muestra el activo con toda su información

---

### Caso 3: Ver todo lo que aparece un usuario
1. Sidebar → "Búsqueda"
2. Escribe: `#nombre.usuario`
3. Click en "Buscar"

**Resultado**: 
- ✅ Todos sus activos
- ✅ Su información de perfil
- ✅ Tickets donde fue mencionado

---

### Caso 4: Ver perfil de otro usuario
1. Sidebar → "Búsqueda"
2. Busca `#otro.usuario`
3. Click en "Mi Perfil" de ese usuario
4. O accede directamente: `perfil_usuario.php?username=otro.usuario`

**Resultado**: Perfil completo de ese usuario con sus tickets y activos

---

## 🎨 Características de Interfaz

### **Navegación Mejorada**
- ✅ Dropdown en el navbar con opción "Mi Perfil"
- ✅ Links nuevos en sidebar: "Búsqueda" y "Mi Perfil"
- ✅ Botones para cambiar entre secciones en perfil

### **Paginación**
- Todos los listados tienen paginación (9 items por página)
- Botones: Primera, Anterior, Páginas, Siguiente, Última

### **Responsive**
- ✅ Funciona perfectamente en móvil
- ✅ Tarjetas adaptables
- ✅ Diseño limpio y moderno

---

## 🔍 Detalles Técnicos

### **Campos Buscables**

#### Tickets:
- `ticket_number`: Número único (DCD000001)
- `titulo`: Título del ticket
- `descripcion`: Descripción completa
- `nombre_solicitante`: Quién reportó
- `responsable`: Quién está asignado

#### Activos:
- `numero_activo`: Código AK79XXXX
- `rfk`: Identificador interno
- `titulo`: Nombre del activo
- `descripcion`: Descripción
- `propietario`: Usuario asignado
- `ubicacion`: Dónde está
- `tipo`: Categoría
- `fabricante`: Fabricante

#### Usuarios:
- `username`: Nombre de usuario (para búsqueda con #)
- `role`: Admin, Soporte TI, Lector
- `email`: Correo electrónico

### **Detección de Patrones**

El sistema detecta automáticamente:
- **Usuario**: `#[a-z0-9._]+` → Busca username
- **Activo**: `AK\d{5,7}` → Busca por código
- **Número Ticket**: Mantiene búsqueda normal (puede incluir #DCD)

---

## 📝 Notas Importantes

1. **Permisos**: La búsqueda de activos solo está disponible para `tisupport` y `admin`
2. **Menciones**: Se buscan comentarios con formato `@usuario`
3. **Paginación**: Los perfiles muestran 9 items por página
4. **Cache**: Las páginas no están cacheadas para datos actualizados
5. **Dark Mode**: Todos los nuevos archivos soportan modo oscuro

---

## 🚀 Próximas Mejoras Sugeridas

- [ ] Agregar filtros por fecha en perfil
- [ ] Exportar resultados de búsqueda a PDF
- [ ] Historial de búsquedas recientes
- [ ] Búsqueda por rango de fechas
- [ ] Añadir filtros avanzados

---

**Última actualización**: 26 de enero de 2026
**Sistema**: Ticket Manager v2.0
