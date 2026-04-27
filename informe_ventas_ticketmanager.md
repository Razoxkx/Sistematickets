# 📋 INFORME COMERCIAL — TICKET MANAGER
## Sistema Integral de Gestión de TI, Activos y Soporte Técnico

---

## 1. RESUMEN EJECUTIVO

**Ticket Manager** es una plataforma web integral desarrollada para departamentos de TI y mesas de ayuda que necesitan centralizar, controlar y auditar toda su operación diaria en un solo lugar. Desde la recepción de tickets de soporte hasta el monitoreo de dispositivos de red, pasando por la gestión de inventario de activos, documentación de procedimientos y custodia segura de credenciales.

> **En una frase:** *Ticket Manager transforma el caos operativo de TI en un centro de comando organizado, trazable y medible.*

---

## 2. LAS 8 PROBLEMÁTICAS CRÍTICAS QUE RESUELVE

### ❌ Problema 1: Tickets de soporte se pierden en correos, chats y llamadas
**Necesidad que resuelve:** Canalización y trazabilidad de incidencias.
- Los tickets se centralizan con número único (`DCD000001`).
- Flujo de estados: *Sin abrir → En conocimiento → En proceso → Pendiente de cierre → Cerrado*.
- Asignación de responsables con visibilidad de "Mis Tickets".
- Sub-tareas (tickets hijos) para descomponer problemas complejos.
- Cierre masivo con motivo documentado.

### ❌ Problema 2: No se sabe qué equipos se tienen, dónde están ni quién los usa
**Necesidad que resuelve:** Inventario inteligente de activos de TI.
- Catálogo de activos con código RFK (`AK79XXXXXXX`), tipo, fabricante, modelo, serie, ubicación y propietario.
- Búsqueda por código, título, tipo, fabricante, ubicación o propietario.
- Relación automática entre activos y tickets donde son mencionados.
- Historial de modificaciones con auditoría de usuario y fecha.

### ❌ Problema 3: Las contraseñas de servicios están en hojas de Excel o post-its
**Necesidad que resuelve:** Custodia segura de credenciales corporativas.
- Vault centralizado de cuentas de servicio (plataforma, correo, contraseña, descripción).
- Revelado de contraseñas solo con autenticación de administrador (`password_verify`).
- Auditoría de creación: quién creó la cuenta, cuándo y última modificación.
- Acceso exclusivo para administradores.

### ❌ Problema 4: El conocimiento técnico se va cuando el técnico se va
**Necesidad que resuelve:** Documentación institucional de procedimientos.
- Biblioteca de procedimientos técnicos y administrativos con código (`DCD.T0000001`).
- Soporte para adjuntar archivos PDF como respaldo documental.
- Menciones cruzadas entre procedimientos, tickets y activos.
- Clasificación por tipo (técnico / administrativo) y búsqueda por título.

### ❌ Problema 5: La red puede fallar y nadie se entera hasta que alguien lo reporta
**Necesidad que resuelve:** Monitoreo proactivo de infraestructura.
- Ping automático a dispositivos (servidores, routers, switches, cámaras, etc.).
- Estado en tiempo real: Online / Offline con latencia medida.
- Clasificación por tipos de dispositivo personalizables con iconos y colores.
- Modo pantalla completa para NOC / centro de monitoreo.
- Auto-actualización cada 60 segundos sin recargar la página.

### ❌ Problema 6: No hay visibilidad de productividad ni cuellos de botella
**Necesidad que resuelve:** Inteligencia operacional y reportes de gestión.
- Dashboard con tarjetas de estadísticas: total, cerrados, abiertos, en proceso.
- Gráfico circular (doughnut) de distribución por estado.
- Top 5 solicitantes del mes actual.
- Ranking de técnicos por tickets atendidos.
- Filtros avanzados por estado, solicitante, responsable y rango de fechas.
- Exportación a PDF: mes actual, últimos 7 días o personalizado.

### ❌ Problema 7: Buscar información lleva minutos (o no se encuentra)
**Necesidad que resuelve:** Búsqueda inteligente y global.
- **Búsqueda por patrón de usuario:** `#nombre.usuario` → muestra perfil, tickets y activos del usuario.
- **Búsqueda por activo:** `AK79XXXXXXX` → localiza el activo directamente.
- **Búsqueda por ticket:** `#DCD000042` → acceso directo al ticket.
- Búsqueda global unificada que escanea tickets, activos y usuarios en una sola consulta.
- Autocomplete de usuarios y contactos al crear tickets.

### ❌ Problema 8: Riesgo de accesos no autorizados y falta de control de usuarios
**Necesidad que resuelve:** Seguridad, roles y gobernanza de accesos.
- Sistema de roles jerárquicos: **Administrador → Soporte TI → Lector → Contacto**.
- Cambio obligatorio de contraseña en primer inicio de sesión (flujo forzado).
- Protección CSRF en todos los formularios.
- Rate limiting para prevenir ataques de fuerza bruta.
- Validación de contraseña para acciones sensibles (revelar credenciales, editar cuentas).
- Backups programados y descargables de la base de datos completa.

---

## 3. MÓDULOS Y FUNCIONALIDADES CLAVE

| Módulo | Funcionalidades Destacadas |
|--------|---------------------------|
| **🎫 Tickets** | Creación, asignación, estados, sub-tareas, comentarios, cierre masivo, búsqueda por #usuario, paginación, ordenamiento |
| **📦 Activos** | Inventario con RFK, búsqueda multi-campo, relación con tickets, historial de auditoría, edición con validación |
| **👥 Usuarios & Contactos** | Gestión de usuarios del sistema y contactos organizacionales, perfiles individuales, búsqueda global, paginación |
| **📑 Procedimientos** | Documentación técnica y administrativa, adjuntos PDF, menciones cruzadas, clasificación por tipo |
| **🔐 Cuentas de Servicio** | Vault de credenciales, revelado con autenticación de admin, auditoría de creación/modificación |
| **📡 Monitoreo** | Ping a dispositivos, latencia, estados online/offline, tipos personalizables, modo fullscreen para NOC |
| **📊 Reportes** | Dashboard visual, gráficos Chart.js, filtros avanzados, exportación a PDF, top solicitantes, ranking de técnicos |
| **🔍 Búsqueda Global** | Búsqueda unificada con detección de patrones (#usuario, AK79XXXX, #DCDXXXXXX) |
| **💾 Backups** | Backup completo de base de datos descargable o guardable en servidor, con registro histórico |
| **🛡️ Seguridad** | Roles, CSRF, rate limiting, cambio de contraseña obligatorio, validación de acciones sensibles |
| **🌙 UX/UI** | Dark mode persistente, interfaz responsive (mobile/tablet/desktop), Bootstrap 5.3, iconos Bootstrap Icons |

---

## 4. BENEFICIOS POR PERFIL DE CLIENTE

### 👔 Gerente de TI / Director de Infraestructura
> **Necesidad:** Visibilidad, control y reducción de riesgos.

- **Toma de decisiones basada en datos:** reportes de productividad del equipo, identificación de cuellos de botella.
- **Auditoría completa:** sabe quién creó qué, cuándo y qué cambió.
- **Reducción de riesgos:** las contraseñas ya no están dispersas en hojas de cálculo.
- **Cumplimiento:** backups programados, trazabilidad de incidencias, control de accesos.

### 🔧 Técnico de Soporte TI
> **Necesidad:** Eficiencia, claridad y menos tiempo administrativo.

- **Bandeja organizada:** "Mis Tickets" para saber exactamente qué debe atender.
- **Menos búsquedas:** menciones inteligentes (`#usuario`, `AK79XXXX`) que vinculan información relacionada automáticamente.
- **Procedimientos a mano:** cuando surge un incidente, puede consultar la biblioteca de procedimientos en segundos.
- **Comunicación centralizada:** todo lo relacionado con un ticket queda documentado en el ticket.

### 📞 Mesa de Ayuda / Service Desk
> **Necesidad:** Rapidez en la atención y registro de incidencias.

- **Creación rápida de tickets:** autocomplete de usuarios reportantes evita errores de tipeo.
- **Seguimiento visible:** cada ticket tiene estado, responsable y comentarios en un solo lugar.
- **Escalamiento estructurado:** sub-tareas permiten derivar partes de un problema a otros técnicos sin perder el hilo.

### 🏢 Organización / Empresa (Visión Global)
> **Necesidad:** Estandarización, gobernanza y continuidad operativa.

- **Conocimiento institucional:** los procedimientos quedan en la empresa, no en la cabeza de un empleado.
- **Continuidad ante rotación:** un nuevo técnico puede ver el historial completo de tickets, activos y procedimientos.
- **Estandarización:** todos los técnicos usan el mismo flujo, los mismos códigos y el mismo lenguaje.
- **Reducción de downtime:** el monitoreo detecta caídas antes de que los usuarios reporten.

---

## 5. DIFERENCIADORES COMPETITIVOS

| Característica | Ticket Manager | Soluciones Genéricas |
|----------------|---------------|---------------------|
| **Búsqueda inteligente por patrones** | ✅ #usuario, AK79XXXX, #DCDXXXXXX | ❌ Solo búsqueda de texto libre |
| **Monitoreo de red integrado** | ✅ Ping + latencia + estado visual | ❌ Requiere herramienta aparte (Zabbix, PRTG) |
| **Vault de credenciales con autenticación de admin** | ✅ Revelado controlado con `password_verify` | ❌ No incluido o inseguro |
| **Sub-tareas / Tickets hijos** | ✅ Descomposición de problemas complejos | ❌ Solo tickets planos |
| **Modo pantalla completa para NOC** | ✅ Diseñado para centros de monitoreo | ❌ No contemplado |
| **Cierre masivo de tickets** | ✅ Con selección múltiple y motivo | ❌ Cierre uno a uno |
| **Dark mode nativo y persistente** | ✅ En todas las páginas | ❌ No siempre disponible |
| **Menciones cruzadas enriquecidas** | ✅ Tickets, activos, usuarios y procedimientos se vinculan automáticamente | ❌ Texto plano |
| **Responsive para tablets** | ✅ Diseño optimizado para tablets de campo | ❌ Solo desktop |
| **Backups descargables y guardables** | ✅ Con registro histórico en BD | ❌ Manual o externo |

---

## 6. SEGURIDAD Y COMPLIANCE

| Capa de Seguridad | Implementación |
|-------------------|---------------|
| **Autenticación** | Sesiones PHP seguras, contraseñas hasheadas con `PASSWORD_BCRYPT` |
| **Autorización** | Roles jerárquicos (admin, tisupport, viewer, contacto) con permisos granulares |
| **Protección de formularios** | Tokens CSRF en todas las operaciones de escritura |
| **Prevención de ataques** | Rate limiting para evitar fuerza bruta |
| **Sanitización** | `htmlspecialchars`, prepared statements, validación de entrada en backend |
| **Política de contraseñas** | Cambio obligatorio en primer login, mínimo 6 caracteres |
| **Auditoría** | Timestamps de creación y modificación en tickets, activos, cuentas y procedimientos |
| **Backups** | Exportación SQL completa de la base de datos, con registro histórico |
| **Revelado de secrets** | Doble factor de autenticación implícito: ser admin + conocer tu propia contraseña |

---

## 7. RETORNO DE INVERSIÓN (ROI) ESTIMADO

### Ahorro de Tiempo (Mensual)
| Actividad | Antes | Con Ticket Manager | Ahorro |
|-----------|-------|-------------------|--------|
| Buscar un activo / equipo | 5-10 min | 10 seg | **~95%** |
| Crear y asignar un ticket | 10 min | 2 min | **~80%** |
| Encontrar una contraseña | 5-15 min (buscar en archivos) | 10 seg | **~98%** |
| Generar reporte de productividad | 2-4 horas (manual) | 1 clic (PDF) | **~99%** |
| Identificar quién reportó qué | Consultas dispersas | Búsqueda `#usuario` | **~90%** |
| Detectar caída de dispositivo | Cuando alguien reporta | Alerta visual inmediata | **~100%** |

### Beneficios Cualitativos
- **Trazabilidad legal:** en auditorías o disputas, todo queda documentado con usuario, fecha y motivo.
- **Reducción de downtime:** el monitoreo proactivo detecta fallas antes de que escale.
- **Retención de conocimiento:** los procedimientos documentados reducen la curva de aprendizaje de nuevos técnicos.
- **Satisfacción del usuario interno:** tiempos de respuesta más rápidos y comunicación más clara.

---

## 8. RECOMENDACIÓN FINAL

**Ticket Manager no es solo un "sistema de tickets". Es un centro de comando para departamentos de TI que necesitan pasar de operar a ciegas a operar con visibilidad total.**

### Ideal para:
- Empresas con 20 a 500 colaboradores que tienen (o quieren tener) un área de TI.
- Proveedores de servicios de TI (MSP) que necesitan gestionar múltiples clientes o infraestructuras.
- Instituciones públicas, municipios o gobiernos regionales que requieren trazabilidad y auditoría.
- Cualquier organización que hoy gestione tickets por WhatsApp, correo o Excel y quiera profesionalizar su operación.

### Modelo de implementación sugerido:
- **Despliegue on-premise** (servidor propio) o **en hosting privado**.
- **Tiempo de implementación:** 1-2 días.
- **Capacitación:** 2-4 horas para usuarios finales, 4-8 horas para administradores.
- **Soporte:** basado en el stack estándar PHP + MySQL, compatible con cualquier hosting comercial o servidor Linux/Windows con Apache/Nginx.

---

> **"Dejar de perder tiempo buscando información y empezar a resolver problemas. Eso es Ticket Manager."**

---

*Documento preparado para fines comerciales y de propuesta de valor.*
*Sistema analizado: Ticket Manager v2026 — PHP 8.x, MySQL, Bootstrap 5.3*

