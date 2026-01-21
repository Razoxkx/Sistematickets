# AI Coding Assistant Instructions for Ticket Management System

## Project Overview
This is a **Spanish-language IT ticket management system** built with PHP + MySQL, deployed on MAMP at `localhost:8889/test`. It handles ticket lifecycle (creation, assignment, status tracking, closure) with role-based access control. Not a production system—intentional security patterns for learning/demo.

## Architecture & Key Components

### Database Structure
- **users** table: Role-based access (`admin`, `tisupport`, `viewer`); passwords hashed with `password_hash(PASSWORD_BCRYPT)`
- **tickets** table: Core entity with status workflow (`sin abrir` → `en conocimiento` → `en proceso` → `pendiente de cierre`)
- **comentarios_tickets** table: Audit trail; tracks creator, timestamp, and editor (usuario_modificado_por)
- **Nuevas columnas:** `motivo_cierre` (motivo de cierre masivo), `estado_cancelacion` (motivo de cancelación)

### Role-Based Access Pattern
```php
// Consistent permission check across all pages
$permisos = ['tisupport', 'admin'];
if (!in_array($_SESSION["role"] ?? "viewer", $permisos)) {
    header("Location: dashboard.php");
    exit();
}
```
- `admin`: Full system access (create/edit users, manage tickets)
- `tisupport`: Create/manage tickets, add users
- `viewer`: View dashboard only (login page redirects to [index.php](index.php))

### Session Management
- All pages check `if (!isset($_SESSION["user_id"]))` and redirect to [index.php](index.php)
- Session variables: `user_id`, `username`, `role`
- Logout via [index.php?logout=1](index.php?logout=1)

## Critical Patterns & Conventions

### Ticket Creation Flow ([crear_ticket.php](crear_ticket.php))
1. Generate temporary ticket number: `"DCD" . uniqid()`
2. Insert ticket into DB, retrieve auto-increment `id`
3. Regenerate permanent number: `"DCD" . str_pad($ticket_id, 6, "0", STR_PAD_LEFT)` (e.g., `DCD000042`)
4. Add initial comment via `comentarios_tickets` table with reporter name
5. **Responsable es obligatorio** - Validación frontend y backend
6. **Redirección:** `header("Location: tickets.php?success=creado")`

### Status Transition Logic ([ver_ticket.php](ver_ticket.php))
- Auto-upgrade `"sin abrir"` → `"en conocimiento"` when ticket is first viewed
- Allowed statuses: `sin abrir`, `en conocimiento`, `en proceso`, `pendiente de cierre`, `ticket cerrado`
- Use `es_cerrado` flag (0/1) to filter open vs. closed tickets, not status alone
- **Cancelación de tickets:** Modal con motivos predefinidos (Duplicado, Solucionado, etc.)
- **Cache Prevention:** Headers HTTP para evitar que atrás del navegador use caché
  - `Cache-Control: no-store, no-cache, must-revalidate`
  - Event listener: `pageshow` with `event.persisted` para recargar automáticamente

### Cierre Masivo ([tickets.php](tickets.php))
- Checkboxes en columna izquierda para selección múltiple
- Botón dinámico "Cerrar Seleccionados" (solo aparece si hay selección)
- Modal obligatorio con motivo: Spam, Ticket repetido, Resuelto, No procede, Otros
- Procesa múltiples tickets en una transacción
- Crea comentarios automáticos con `array_filter(array_map('intval', explode(',', $ids)))`
- Toast verde en esquina superior derecha confirma cierre exitoso

### Reportes ([tickets.php](tickets.php) modal + [generar_reporte_pdf.php](generar_reporte_pdf.php))
- Modal integrado sin navegar a página separada
- Filtros: Estado, Solicitante, Rango de fechas
- Dos tipos: Completo (detallado) o Resumido (tabla simple)
- Abre en nueva ventana con `target="_blank"` y diálogo de impresión
- Generación HTML que el navegador puede imprimir como PDF

### Data Formatting
- **Dates**: Custom function [includes/config.php#L41-L47](includes/config.php#L41-L47) converts to Spanish abbreviated format: `"ENE 15 2026 - 14:30"`
- **Roles**: [includes/config.php#L20-L25](includes/config.php#L20-L25) translates `admin`→`"Admin"`, `tisupport`→`"Soporte TI"`, `viewer`→`"Lector"`
- **Form input**: Always sanitize with `htmlspecialchars()` before display

### Search & Filter Pattern ([tickets.php](tickets.php))
- Supports multi-field search: ticket number, title, requester name via `%LIKE%`
- Sortable columns validated against whitelist: `ticket_number`, `titulo`, `estado`, `fecha`, `solicitante`, `responsable`
- Prevents SQL injection: parameters bound with `?` placeholders; direction validated (`ASC|DESC`)
- Pagination: 9 tickets/page; uses `$offset = ($page-1) * 9`

### UI Components
- **Bootstrap 5.3.8** CDN (no local build required)
- **Navbar** ([includes/navbar.php](includes/navbar.php)): Included in all authenticated pages; shows role badge + dark mode toggle
- **Dark mode**: Persisted in `localStorage['darkMode']` on client side
- **Color coding**: Badge colors based on role: `admin` → danger (red), `tisupport` → warning (yellow), `viewer` → info (blue)

## Setup & Prerequisites
- MAMP running locally; DB: `test`, User: `root`, Password: `r652Is-scVT1HX3@`, Port: `8889`
- MySQL tables created by [insert_tickets.sql](insert_tickets.sql) (includes sample data)
- No build step; plain PHP served directly by MAMP

## Common Tasks

### Adding a New Restricted Page
1. Start with session check + permission check (see [crear_ticket.php](crear_ticket.php) lines 1–15)
2. Include [includes/navbar.php](includes/navbar.php) in HTML body
3. Require [includes/config.php](includes/config.php) for DB connection

### Modifying Ticket Status Workflow
- Edit allowed statuses array in [tickets.php](tickets.php#L25) and [ver_ticket.php](ver_ticket.php) dropdown
- Remember: `es_cerrado` flag controls view filters, not status string alone
- Auto-status change happens in [ver_ticket.php](ver_ticket.php#L38-L41)

### Adding Database Columns
- Migrations in [migration_finalizado.php](migration_finalizado.php); run directly or reference for schema understanding
- Always include `fecha_ultima_modificacion` timestamp for audit trail

## Spanish UI Terminology
- Ticket states are Spanish: `sin abrir` (unstarted), `en conocimiento` (acknowledged), `en proceso` (in progress), `pendiente de cierre` (pending closure), `ticket cerrado` (closed)
- User roles: `admin`, `tisupport`, `viewer` (always lowercase in DB)
- Common fields: `titulo`, `descripción`, `estado`, `responsable`, `solicitante`

## Security Notes
- Credentials hardcoded in [includes/config.php](includes/config.php) (demo only; use env vars in production)
- No CSRF tokens on forms; add if moving toward production
- Uses `password_verify()` for auth; new users created with `password_hash(PASSWORD_BCRYPT)`
