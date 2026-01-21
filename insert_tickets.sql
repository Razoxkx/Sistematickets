-- Insertar 10 tickets de prueba con estados variados
-- Asumiendo que usuario_creador = 1 (admin) y propietario puede ser 2 o 3 (soporte TI)

INSERT INTO tickets (ticket_number, titulo, descripcion, estado, es_cerrado, usuario_creador, nombre_solicitante, propietario, fecha_creacion, fecha_ultima_modificacion) VALUES

('DCD000001', '[URGENTE] Error en el login - No se puede acceder al sistema', 'Desde hace 2 horas no puedo acceder a mi cuenta. Intento con mi usuario y contraseña pero me aparece error 401. Necesito acceso urgente.', 'en proceso', 0, 1, 'Pablo Orellana', 2, NOW(), NOW()),

('DCD000002', '[INFO] Solicitud de actualización de permisos en carpeta compartida', 'Necesito permisos de escritura en la carpeta /shared/proyectos_2026. Actualmente solo tengo lectura y no puedo guardar mis cambios.', 'sin abrir', 0, 1, 'Felipe Muñoz', NULL, NOW(), NOW()),

('DCD000003', '[CRÍTICO] Base de datos respondiendo lentamente', 'Las consultas a la base de datos están tardando más de 30 segundos. Esto está afectando el desempeño de toda la aplicación. Requiere atención inmediata.', 'pendiente de cierre', 0, 1, 'Marcos Concha', 3, NOW(), NOW()),

('DCD000004', '[SOPORTE] Reseteo de contraseña de usuario corporativo', 'Usuario: jlizana@empresa.com. Ha olvidado su contraseña y no puede recuperarla por el método automático.', 'en conocimiento', 0, 1, 'John Lizana', 2, NOW(), NOW()),

('DCD000005', '[BUG] Reporte incorrecto en módulo de estadísticas', 'Al generar el reporte de enero, los números no coinciden con los cálculos manuales. Hay una diferencia de aprox 15% en las ventas totales.', 'ticket cerrado', 1, 1, 'Pablo Orellana', 3, NOW(), NOW()),

('DCD000006', '[HARDWARE] Printer parada en piso 3', 'La impresora HP LJ Pro M404 del piso 3 no imprime. Muestra error de papel atascado pero ya fue revisada.', 'en proceso', 0, 1, 'Felipe Muñoz', 2, NOW(), NOW()),

('DCD000007', '[SOLICITUD] Instalación de software especializado - AutoCAD 2024', 'Se necesita instalar AutoCAD 2024 en 5 máquinas del departamento de diseño. Favor verificar licencias disponibles.', 'sin abrir', 0, 1, 'Marcos Concha', NULL, NOW(), NOW()),

('DCD000008', '[FALLO] Sincronización de carpeta OneDrive no funciona', 'La carpeta de proyecto no se sincroniza con OneDrive. Cambios locales no se reflejan en la nube y causa conflictos con el equipo remoto.', 'en conocimiento', 0, 1, 'John Lizana', 3, NOW(), NOW()),

('DCD000009', '[MANTENIMIENTO] Actualización de software corporativo - Office 365', 'Se requiere actualizar Office 365 a la última versión. Favor agendar la ventana de mantenimiento para el próximo fin de semana.', 'pendiente de cierre', 0, 1, 'Pablo Orellana', 2, NOW(), NOW()),

('DCD000010', '[INCIDENT] VPN desconecta después de 30 minutos de inactividad', 'Cuando trabajo remoto, la VPN se desconecta automáticamente cada 30 minutos. Tengo que reconectarme constantemente, muy incómodo.', 'ticket cerrado', 1, 1, 'Felipe Muñoz', NULL, NOW(), NOW());

-- Insertar comentarios de ejemplo para algunos tickets
INSERT INTO comentarios_tickets (ticket_id, usuario_id, contenido, fecha, fecha_modificacion, usuario_modificado_por) VALUES

(1, 1, 'Ticket reportado por: Pablo Orellana', NOW(), NOW(), 1),
(1, 2, 'Se está investigando el problema de autenticación en el servidor.', NOW(), NOW(), 2),
(1, 2, 'Se encontró que el certificado SSL ha expirado. Procediendo a renovarlo.', NOW(), NOW(), 2),

(2, 1, 'Ticket reportado por: Felipe Muñoz', NOW(), NOW(), 1),

(3, 1, 'Ticket reportado por: Marcos Concha', NOW(), NOW(), 1),
(3, 3, 'Iniciadas pruebas de carga en la base de datos. Problema identificado en índices faltantes.', NOW(), NOW(), 3),
(3, 3, 'Índices recreados. Desempeño normalizado. Pendiente validación final.', NOW(), NOW(), 3),

(4, 1, 'Ticket reportado por: John Lizana', NOW(), NOW(), 1),
(4, 2, 'Solicitando credenciales de backup. Intentaremos recuperar acceso.', NOW(), NOW(), 2),

(5, 1, 'Ticket reportado por: Pablo Orellana', NOW(), NOW(), 1),
(5, 3, 'Problema de cálculo en fórmula de comisiones identificado y corregido.', NOW(), NOW(), 3),
(5, 3, 'Reportes validados. Cifras correctas. Ticket resuelto.', NOW(), NOW(), 3),

(10, 1, 'Ticket reportado por: Felipe Muñoz', NOW(), NOW(), 1),
(10, 2, 'Configuración de timeout de VPN ajustada. Cliente actualizado.', NOW(), NOW(), 2),
(10, 2, 'Usuario reporta que problema ha sido solucionado.', NOW(), NOW(), 2);
