-- =============================================================================
-- SQL SCHEMA - GESTIÓN DE PROYECTOS GANTT
-- =============================================================================

-- 1. CREACIÓN DE LA TABLA DE PROYECTOS
CREATE TABLE IF NOT EXISTS gestion_proyectos_proyectos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- DATOS BÁSICOS DEL PROYECTO
    nombre VARCHAR(255) NOT NULL COMMENT 'Nombre del proyecto visible en barra',
    descripcion TEXT DEFAULT NULL COMMENT 'Descripción detallada - solo visible en tooltip al hacer hover',
    
    -- ASIGNACIÓN Y FECHAS
    CodNivelesCargos INT NOT NULL COMMENT 'Cargo del equipo de liderazgo (EquipoLiderazgo=1)',
    fecha_inicio DATE NOT NULL COMMENT 'Fecha de inicio del proyecto',
    fecha_fin DATE NOT NULL COMMENT 'Fecha de finalización del proyecto',
    
    -- JERARQUÍA Y ORGANIZACIÓN
    orden_visual INT DEFAULT 0 COMMENT 'Orden vertical dentro del cargo para proyectos traslapados',
    es_subproyecto TINYINT(1) DEFAULT 0 COMMENT '0=Proyecto padre, 1=Subproyecto',
    proyecto_padre_id INT DEFAULT NULL COMMENT 'NULL si es proyecto padre, ID del padre si es subproyecto',
    esta_expandido TINYINT(1) DEFAULT 0 COMMENT 'Estado visual de expansión de subproyectos (1=expandido, 0=contraído)',
    
    -- AUDITORÍA
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    creado_por INT NOT NULL COMMENT 'CodOperario del usuario que creó el proyecto',
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    modificado_por INT DEFAULT NULL COMMENT 'CodOperario del último usuario que modificó',
    
    -- FOREIGN KEYS
    FOREIGN KEY (CodNivelesCargos) REFERENCES NivelesCargos(CodNivelesCargos),
    FOREIGN KEY (proyecto_padre_id) REFERENCES gestion_proyectos_proyectos(id) ON DELETE CASCADE,
    FOREIGN KEY (creado_por) REFERENCES Operarios(CodOperario),
    FOREIGN KEY (modificado_por) REFERENCES Operarios(CodOperario),
    
    -- ÍNDICES PARA OPTIMIZACIÓN
    INDEX idx_cargo (CodNivelesCargos),
    INDEX idx_fechas (fecha_inicio, fecha_fin),
    INDEX idx_padre (proyecto_padre_id),
    INDEX idx_orden (orden_visual)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Gestión de proyectos tipo Gantt para equipo de liderazgo - Batidos Pitaya ERP';

-- 2. REGISTRO EN tools_erp
INSERT INTO tools_erp (nombre, grupo, descripcion, url_real, url_alias, icono, orden) 
SELECT 'gestion_proyectos', 'gerencia', 'Gestión de proyectos tipo Gantt', 'modulos/gerencia/gestion_proyectos.php', 'proyectos-gantt', 'fas fa-tasks', 1
WHERE NOT EXISTS (SELECT 1 FROM tools_erp WHERE nombre = 'gestion_proyectos');

-- 3. REGISTRO DE ACCIONES EN acciones_tools_erp
SET @tool_id = (SELECT id FROM tools_erp WHERE nombre = 'gestion_proyectos');

INSERT INTO acciones_tools_erp (tool_erp_id, nombre_accion, descripcion) 
SELECT @tool_id, 'vista', 'Ver diagrama Gantt y historial de proyectos'
WHERE NOT EXISTS (SELECT 1 FROM acciones_tools_erp WHERE tool_erp_id = @tool_id AND nombre_accion = 'vista');

INSERT INTO acciones_tools_erp (tool_erp_id, nombre_accion, descripcion) 
SELECT @tool_id, 'crear_proyecto', 'Crear, editar y eliminar proyectos y subproyectos'
WHERE NOT EXISTS (SELECT 1 FROM acciones_tools_erp WHERE tool_erp_id = @tool_id AND nombre_accion = 'crear_proyecto');

-- 4. PERMISOS INICIALES
-- Permiso de VISTA para todos con EquipoLiderazgo = 1
INSERT INTO permisos_tools_erp (accion_tool_erp_id, CodNivelesCargos, permiso)
SELECT 
    a.id,
    nc.CodNivelesCargos,
    'allow'
FROM acciones_tools_erp a
CROSS JOIN NivelesCargos nc
WHERE a.nombre_accion = 'vista'
  AND a.tool_erp_id = @tool_id
  AND nc.EquipoLiderazgo = 1
  AND NOT EXISTS (
      SELECT 1 FROM permisos_tools_erp p 
      WHERE p.accion_tool_erp_id = a.id AND p.CodNivelesCargos = nc.CodNivelesCargos
  );

-- Permiso de CREAR_PROYECTO solo para Gerencia General (16)
INSERT INTO permisos_tools_erp (accion_tool_erp_id, CodNivelesCargos, permiso)
SELECT 
    a.id,
    16,  -- Gerencia General
    'allow'
FROM acciones_tools_erp a
WHERE a.nombre_accion = 'crear_proyecto'
  AND a.tool_erp_id = @tool_id
  AND NOT EXISTS (
      SELECT 1 FROM permisos_tools_erp p 
      WHERE p.accion_tool_erp_id = a.id AND p.CodNivelesCargos = 16
  );
