<?php
// public_html/modulos/mantenimiento/equipos_registro.php
require_once '../../includes/auth.php';
require_once '../../includes/funciones.php';
require_once 'config/database.php';

$usuario = obtenerUsuarioActual();
$cargoOperario = $usuario['CodNivelesCargos'];

// Solo líder de infraestructura puede registrar equipos
if ($cargoOperario != 35) {
    die("Acceso denegado");
}

// Obtener tipos de equipos
$tipos = $db->fetchAll("SELECT id, nombre FROM mtto_equipos_tipos WHERE activo = 1 ORDER BY nombre");

// Obtener sucursales
$sucursales = $db->fetchAll("SELECT id, codigo, nombre FROM sucursales WHERE activa = 1 ORDER BY nombre");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Equipo - Sistema de Mantenimiento</title>
    <link rel="stylesheet" href="css/equipos_estilos.css">
</head>
<body>
    <div class="contenedor-principal">
        <div class="tarjeta">
            <div class="encabezado-pagina">
                <h1 class="titulo-pagina">Registro de Nuevo Equipo</h1>
                <a href="equipos_lista.php" class="btn btn-secundario">← Volver</a>
            </div>

            <form id="formRegistro" class="formulario">
                <input type="hidden" name="registrado_por" value="<?php echo $_SESSION['usuario_id']; ?>">
                
                <h3 style="color: #0E544C; margin-bottom: 20px;">Información Básica</h3>
                
                <div class="fila-formulario">
                    <div class="grupo-formulario">
                        <label class="campo-requerido">Código del Equipo</label>
                        <input type="text" name="codigo" required 
                               placeholder="Ej: EQ-001">
                    </div>
                    
                    <div class="grupo-formulario">
                        <label class="campo-requerido">Nombre del Equipo</label>
                        <input type="text" name="nombre" required 
                               placeholder="Ej: Computadora Principal Caja 1">
                    </div>
                </div>

                <div class="fila-formulario">
                    <div class="grupo-formulario">
                        <label class="campo-requerido">Tipo de Equipo</label>
                        <select name="tipo_id" required>
                            <option value="">Seleccione un tipo</option>
                            <?php foreach ($tipos as $tipo): ?>
                                <option value="<?php echo $tipo['id']; ?>">
                                    <?php echo htmlspecialchars($tipo['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="grupo-formulario">
                        <label class="campo-requerido">Frecuencia Mantenimiento (meses)</label>
                        <input type="number" name="frecuencia_mantenimiento_meses" 
                               required min="1" value="3">
                    </div>
                </div>

                <h3 style="color: #0E544C; margin: 30px 0 20px;">Especificaciones Técnicas</h3>

                <div class="fila-formulario">
                    <div class="grupo-formulario">
                        <label>Marca</label>
                        <input type="text" name="marca" placeholder="Ej: Dell">
                    </div>
                    
                    <div class="grupo-formulario">
                        <label>Modelo</label>
                        <input type="text" name="modelo" placeholder="Ej: Optiplex 7080">
                    </div>
                </div>

                <div class="fila-formulario">
                    <div class="grupo-formulario">
                        <label>Número de Serie</label>
                        <input type="text" name="serial" placeholder="Número de serie del fabricante">
                    </div>
                    
                    <div class="grupo-formulario">
                        <label>Fecha de Compra</label>
                        <input type="date" name="fecha_compra">
                    </div>
                </div>

                <div class="grupo-formulario">
                    <label>Características / Especificaciones</label>
                    <textarea name="caracteristicas" 
                              placeholder="Describa las características técnicas del equipo (procesador, RAM, disco duro, etc.)"></textarea>
                </div>

                <h3 style="color: #0E544C; margin: 30px 0 20px;">Información de Compra y Garantía</h3>

                <div class="fila-formulario">
                    <div class="grupo-formulario">
                        <label>Proveedor de Compra</label>
                        <input type="text" name="proveedor_compra" 
                               placeholder="Nombre del proveedor">
                    </div>
                    
                    <div class="grupo-formulario">
                        <label>Costo de Compra</label>
                        <input type="number" name="costo_compra" step="0.01" min="0" 
                               placeholder="0.00">
                    </div>
                </div>

                <div class="fila-formulario">
                    <div class="grupo-formulario">
                        <label>Garantía (meses)</label>
                        <input type="number" name="garantia_meses" min="0" 
                               placeholder="Meses de garantía">
                    </div>
                    
                    <div class="grupo-formulario">
                        <label>Fecha Vencimiento Garantía</label>
                        <input type="date" name="fecha_vencimiento_garantia">
                    </div>
                </div>

                <h3 style="color: #0E544C; margin: 30px 0 20px;">Ubicación Inicial</h3>

                <div class="fila-formulario">
                    <div class="grupo-formulario">
                        <label class="campo-requerido">Ubicación Inicial</label>
                        <select name="ubicacion_inicial" id="ubicacion_inicial" required>
                            <option value="Central">Almacén Central</option>
                            <option value="Sucursal">Sucursal</option>
                        </select>
                    </div>
                    
                    <div class="grupo-formulario" id="grupoSucursal" style="display:none;">
                        <label class="campo-requerido">Sucursal</label>
                        <select name="sucursal_inicial_id" id="sucursal_inicial_id">
                            <option value="">Seleccione una sucursal</option>
                            <?php foreach ($sucursales as $sucursal): ?>
                                <option value="<?php echo $sucursal['id']; ?>">
                                    <?php echo htmlspecialchars($sucursal['codigo'] . ' - ' . $sucursal['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grupo-formulario">
                    <label>Observaciones</label>
                    <textarea name="observaciones" 
                              placeholder="Cualquier información adicional relevante"></textarea>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 30px;">
                    <button type="submit" class="btn btn-primario" id="btnGuardar">
                        Guardar Equipo
                    </button>
                    <a href="equipos_lista.php" class="btn btn-secundario">Cancelar</a>
                </div>
            </form>
        </div>
    </div>

    <script src="js/equipos_registro.js"></script>
</body>
</html>