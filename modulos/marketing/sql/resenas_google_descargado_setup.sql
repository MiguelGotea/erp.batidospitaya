-- 1. Crear tabla ResenasGoogle
CREATE TABLE IF NOT EXISTS `ResenasGoogle` (
  `locationId` varchar(50) DEFAULT NULL,
  `locationName` varchar(50) DEFAULT NULL,
  `reviewId` varchar(100) DEFAULT NULL,
  `reviewerName` varchar(100) DEFAULT NULL,
  `starRating` varchar(20) DEFAULT NULL,
  `comment` varchar(3000) DEFAULT NULL,
  `createTime` varchar(50) DEFAULT NULL,
  `updateTime` varchar(50) DEFAULT NULL,
  `reviewReplyComment` varchar(3000) DEFAULT NULL,
  `reviewReplyUpdateTime` varchar(50) DEFAULT NULL,
  `reviewReplyOwnerName` varchar(100) DEFAULT NULL,
  `reviewSource` varchar(20) DEFAULT NULL,
  `reviewType` varchar(20) DEFAULT NULL,
  `reviewIsEdited` varchar(20) DEFAULT NULL,
  `reviewHasResponse` varchar(20) DEFAULT NULL,
  `reviewResponseRate` varchar(20) DEFAULT NULL,
  `reviewImageUrls` varchar(20) DEFAULT NULL,
  `reviewVideoUrls` varchar(20) DEFAULT NULL,
  `reviewThumbnailUrls` varchar(20) DEFAULT NULL,
  `reviewOwnerVisitTime` varchar(20) DEFAULT NULL,
  `reviewOwnerComment` varchar(20) DEFAULT NULL,
  `reviewOwnerResponseTime` varchar(20) DEFAULT NULL,
  `reviewOwnerResponseComment` varchar(3000) DEFAULT NULL,
  `extractionDate` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Registrar la herramienta en tools_erp
INSERT INTO tools_erp (
    nombre, 
    titulo, 
    tipo_componente, 
    grupo, 
    descripcion, 
    url_real, 
    url_alias, 
    icono, 
    orden, 
    activo
)
VALUES (
    'resenas_google_descargado',
    'Reseñas de Google',
    'herramienta',
    'marketing',
    'Visualización y actualización de reseñas de Google Business',
    '/modulos/marketing/resenas_google_descargado.php',
    'resenas-google-descargado',
    'fas fa-star',
    20,
    1
)
ON DUPLICATE KEY UPDATE 
    titulo = 'Reseñas de Google',
    descripcion = 'Visualización y actualización de reseñas de Google Business',
    url_real = '/modulos/marketing/resenas_google_descargado.php',
    url_alias = 'resenas-google-descargado',
    icono = 'fas fa-star';

-- 3. Crear acciones para la herramienta
-- Primero obtenemos el ID de la herramienta
SET @tool_id = (SELECT id FROM tools_erp WHERE nombre = 'resenas_google_descargado');

INSERT INTO acciones_tools_erp (tool_erp_id, nombre_accion, descripcion)
VALUES 
(@tool_id, 'vista', 'Permite ver la herramienta de reseñas'),
(@tool_id, 'actualizacion', 'Permite habilitar el botón de actualizar datos de reseñas')
ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion);

-- 4. Asignar permisos iniciales (ejemplo para Gerencia General CodNivelesCargos = 16)
-- Y para el usuario que está realizando la solicitud (Gerencia Proyectos = 49)
SET @accion_vista_id = (SELECT id FROM acciones_tools_erp WHERE tool_erp_id = @tool_id AND nombre_accion = 'vista');
SET @accion_actualizar_id = (SELECT id FROM acciones_tools_erp WHERE tool_erp_id = @tool_id AND nombre_accion = 'actualizacion');

INSERT INTO permisos_tools_erp (accion_tool_erp_id, CodNivelesCargos, permiso)
VALUES 
(@accion_vista_id, 16, 'allow'),
(@accion_actualizar_id, 16, 'allow'),
(@accion_vista_id, 49, 'allow'), -- Gerencia Proyectos
(@accion_actualizar_id, 49, 'allow')
ON DUPLICATE KEY UPDATE permiso = 'allow';
