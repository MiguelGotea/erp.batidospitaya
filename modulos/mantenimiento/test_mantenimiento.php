<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test - Sistema de Mantenimiento</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .test-container {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            max-width: 600px;
            width: 100%;
            text-align: center;
        }
        
        .btn-custom {
            padding: 15px 30px;
            font-size: 1.1rem;
            border-radius: 10px;
            margin: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }
        
        .btn-maintenance {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
        }
        
        .btn-equipment {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border: none;
            color: white;
        }
        
        .btn-dashboard-sucursal {
            background: linear-gradient(135deg, #20c997 0%, #0d6efd 100%);
            border: none;
            color: white;
        }
        
        .btn-dashboard-mant {
            background: linear-gradient(135deg, #fd7e14 0%, #ff6b6b 100%);
            border: none;
            color: white;
        }
        
        .btn-calendar {
            background: linear-gradient(135deg, #48cae4 0%, #023047 100%);
            border: none;
            color: white;
        }
        
        .user-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .section-divider {
            border-top: 2px solid #e9ecef;
            margin: 30px 0;
            padding-top: 20px;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h2 class="mb-4">
            <i class="fas fa-tools me-2"></i>
            Sistema de Mantenimiento - Pruebas
        </h2>
        
        <!-- Información del usuario de prueba -->
        <div class="user-info">
            <h5>
                <i class="fas fa-user me-2"></i>
                Usuario de Prueba
            </h5>
            <p class="mb-2">
                <strong>Código Operario:</strong> 75
            </p>
            <p class="mb-0">
                <strong>Código Sucursal:</strong> 4
            </p>
        </div>
        
        <!-- Sección: Formularios de Solicitud -->
        <h6 class="text-start mb-3">
            <i class="fas fa-file-alt me-2"></i>
            Formularios de Solicitud
        </h6>
        
        <div class="d-grid gap-2 d-md-block">
            <button type="button" class="btn btn-custom btn-maintenance" onclick="openMaintenanceForm()">
                <i class="fas fa-tools me-2"></i>
                Mantenimiento General
            </button>
            
            <button type="button" class="btn btn-custom btn-equipment" onclick="openEquipmentForm()">
                <i class="fas fa-laptop me-2"></i>
                Cambio de Equipos
            </button>
        </div>
        
        <!-- Sección: Dashboards -->
        <div class="section-divider">
            <h6 class="text-start mb-3">
                <i class="fas fa-tachometer-alt me-2"></i>
                Paneles de Control
            </h6>
            
            <div class="d-grid gap-2 d-md-block">
                <button type="button" class="btn btn-custom btn-dashboard-sucursal" onclick="openSucursalDashboard()">
                    <i class="fas fa-building me-2"></i>
                    Panel Sucursal
                </button>
                
                <button type="button" class="btn btn-custom btn-dashboard-mant" onclick="openMaintenanceDashboard()">
                    <i class="fas fa-cogs me-2"></i>
                    Panel Mantenimiento
                </button>
            </div>
        </div>
        
        <!-- Sección: Herramientas Adicionales -->
        <div class="section-divider">
            <h6 class="text-start mb-3">
                <i class="fas fa-calendar-alt me-2"></i>
                Herramientas
            </h6>
            
            <div class="d-grid gap-2 d-md-block">
                <button type="button" class="btn btn-custom btn-calendar" onclick="openCalendar()">
                    <i class="fas fa-calendar-check me-2"></i>
                    Calendario de Programación
                </button>
            </div>
        </div>
        
        <!-- Información adicional -->
        <div class="mt-4">
            <small class="text-muted">
                <i class="fas fa-info-circle me-1"></i>
                Los formularios se abrirán en ventanas nuevas. Asegúrate de haber creado las tablas en la base de datos.
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Parámetros del usuario de prueba
        const codOperario = '75';
        const codSucursal = '4';
        
        // Función para abrir formulario de mantenimiento general
        function openMaintenanceForm() {
            const url = `formulario_mantenimiento.php?cod_operario=${codOperario}&cod_sucursal=${codSucursal}`;
            console.log('Abriendo URL:', url);
            
            const newWindow = window.open(url, 'mantenimiento_form', 'width=900,height=700,scrollbars=yes,resizable=yes,location=yes,menubar=no,toolbar=no');
            
            if (!newWindow) {
                alert('Por favor, permite las ventanas emergentes para este sitio y vuelve a intentar.');
            }
        }
        
        // Función para abrir formulario de cambio de equipos
        function openEquipmentForm() {
            const url = `formulario_equipos.php?cod_operario=${codOperario}&cod_sucursal=${codSucursal}`;
            console.log('Abriendo URL:', url);
            
            const newWindow = window.open(url, 'equipos_form', 'width=900,height=700,scrollbars=yes,resizable=yes,location=yes,menubar=no,toolbar=no');
            
            if (!newWindow) {
                alert('Por favor, permite las ventanas emergentes para este sitio y vuelve a intentar.');
            }
        }
        
        // Función para abrir dashboard de sucursal
        function openSucursalDashboard() {
            const url = `dashboard_sucursales.php?cod_operario=${codOperario}&cod_sucursal=${codSucursal}`;
            console.log('Abriendo URL:', url);
            
            const newWindow = window.open(url, 'dashboard_sucursal', 'width=1200,height=800,scrollbars=yes,resizable=yes,location=yes,menubar=no,toolbar=no');
            
            if (!newWindow) {
                alert('Por favor, permite las ventanas emergentes para este sitio y vuelve a intentar.');
            }
        }
        
        // Función para abrir dashboard de mantenimiento
        function openMaintenanceDashboard() {
            const url = `dashboard_mantenimiento.php`;
            console.log('Abriendo URL:', url);
            
            const newWindow = window.open(url, 'dashboard_mantenimiento', 'width=1400,height=900,scrollbars=yes,resizable=yes,location=yes,menubar=no,toolbar=no');
            
            if (!newWindow) {
                alert('Por favor, permite las ventanas emergentes para este sitio y vuelve a intentar.');
            }
        }
        
        // Función para abrir calendario
        function openCalendar() {
            const url = `calendario.php`;
            console.log('Abriendo URL:', url);
            
            const newWindow = window.open(url, 'calendario', 'width=1400,height=900,scrollbars=yes,resizable=yes,location=yes,menubar=no,toolbar=no');
            
            if (!newWindow) {
                alert('Por favor, permite las ventanas emergentes para este sitio y vuelve a intentar.');
            }
        }
        
        // Mostrar información de depuración
        console.log('Parámetros configurados:');
        console.log('- Código Operario:', codOperario);
        console.log('- Código Sucursal:', codSucursal);
        console.log('');
        console.log('URLs que se generarán:');
        console.log('- Mantenimiento General:', `formulario_mantenimiento.php?cod_operario=${codOperario}&cod_sucursal=${codSucursal}`);
        console.log('- Cambio de Equipos:', `formulario_equipos.php?cod_operario=${codOperario}&cod_sucursal=${codSucursal}`);
        console.log('- Dashboard Sucursal:', `dashboard_sucursales.php?cod_operario=${codOperario}&cod_sucursal=${codSucursal}`);
        console.log('- Dashboard Mantenimiento:', 'dashboard_mantenimiento.php');
        console.log('- Calendario:', 'calendario.php');
    </script>
</body>
</html>