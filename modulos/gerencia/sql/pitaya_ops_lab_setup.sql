-- ============================================================
-- PITAYA OPS LAB — Setup de Tabla de Configuración
-- modulos/gerencia/sql/pitaya_ops_lab_setup.sql
-- ============================================================

CREATE TABLE IF NOT EXISTS `ops_config_estaciones` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `tipo_estacion`   VARCHAR(30)    NOT NULL COMMENT 'Batido, Waffle, Bowl, General',
    `parametro`       VARCHAR(60)    NOT NULL COMMENT 'Nombre del parámetro',
    `valor`           DECIMAL(10,2)  NOT NULL,
    `descripcion`     VARCHAR(255)   DEFAULT NULL,
    `actualizado_por` INT            DEFAULT NULL COMMENT 'FK a Operarios',
    `updated_at`      TIMESTAMP      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_ops_param` (`tipo_estacion`, `parametro`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Parámetros operativos configurables para Pitaya OPS Lab';

-- ============================================================
-- Insertar valores iniciales (INSERT IGNORE para no duplicar)
-- ============================================================

INSERT IGNORE INTO `ops_config_estaciones` (`tipo_estacion`, `parametro`, `valor`, `descripcion`) VALUES
-- ── ESTACIÓN BATIDOS ───────────────────────────────────────
('Batido',   'tiempo_insumos_min',      0.50, 'Tiempo sacar insumos de refri/congelador (min)'),
('Batido',   'tiempo_licuado_min',      2.00, 'Tiempo de licuado — programa fijo de licuadora (min)'),
('Batido',   'tiempo_servido_min',      0.50, 'Tiempo servido + sellado + endulzante (min)'),
('Batido',   'tiempo_limpieza_min',     1.00, 'Limpieza post-uso por equipo (min)'),
('Batido',   'num_maquinas',            2.00, 'Licuadoras disponibles'),
('Batido',   'max_batch_size',          3.00, 'Máx. batidos del mismo nombre en 1 lote'),

-- ── ESTACIÓN WAFFLES ───────────────────────────────────────
('Waffle',   'tiempo_mezcla_min',       2.00, 'Tiempo de mezcla de masa con batidora manual (min)'),
('Waffle',   'tiempo_coccion_min',      5.00, 'Tiempo de cocción en wafflera — promedio fijo (min)'),
('Waffle',   'tiempo_emplato_min',      1.00, 'Tiempo emplato + decorado + empaque (min)'),
('Waffle',   'tiempo_limpieza_min',     1.00, 'Limpieza post-uso por equipo (min)'),
('Waffle',   'num_maquinas',            2.00, 'Waffleras disponibles'),
('Waffle',   'num_operarios_min',       1.00, 'Mínimo de operarios asignados a la estación'),
('Waffle',   'num_operarios_max_pool',  3.00, 'Máx. operarios simultáneos (mezcla/cocción/decorado en paralelo)'),

-- ── ESTACIÓN BOWLS ─────────────────────────────────────────
('Bowl',     'tiempo_insumos_min',      0.50, 'Tiempo sacar insumos de refri/congelador (min)'),
('Bowl',     'tiempo_licuado_min',      3.00, 'Tiempo de licuado (proceso pesado) (min)'),
('Bowl',     'tiempo_servido_min',      2.00, 'Tiempo servido + decorado (incluye toppings) (min)'),
('Bowl',     'tiempo_limpieza_min',     1.00, 'Limpieza post-uso por equipo (min)'),
('Bowl',     'num_maquinas',            1.00, 'Motores disponibles'),
('Bowl',     'max_batch_size',          2.00, 'Máx. bowls por lote en un motor'),

-- ── PARÁMETROS GENERALES ───────────────────────────────────
('General',  'limpieza_gral_frecuencia_dia',  6.00, 'Sesiones de limpieza general por día'),
('General',  'limpieza_gral_duracion_min',    15.00, 'Duración de cada sesión de limpieza general (min)'),
('General',  'setup_apertura_min',            30.00, 'Tiempo de preparación del local antes de abrir (min)'),
('General',  'turno_duracion_min',            480.00, 'Duración de un turno en minutos (8 horas)'),
('General',  'num_turnos_dia',                2.00, 'Turnos por día'),
('General',  'solapamiento_turnos_min',       180.00, 'Solapamiento entre turnos (3 horas)'),
('General',  'tiempo_cajero_por_pedido_min',  5.00, 'Tiempo promedio de facturación por pedido en caja (min)'),
('General',  'personas_turno_bajo',           2.00, 'Personas en turno de baja demanda'),
('General',  'personas_turno_normal',         3.00, 'Personas en turno normal'),
('General',  'personas_turno_pico',           4.00, 'Personas en turno pico (vie-sab-dom)'),
('General',  'meta_tiempo_entrega_min',       5.00, 'Meta de tiempo de entrega al cliente (min)'),
('General',  'variabilidad_proceso_pct',      10.00, 'Variabilidad ±% aplicada a cycle times en simulación');
