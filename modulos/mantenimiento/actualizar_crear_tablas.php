<?php
// Habilitar reporte de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<h1>Creación de Tablas del Sistema de Mantenimiento</h1>";
echo "<p><strong>Versión actualizada para usar tablas existentes: sucursales y Operarios</strong></p>";
echo "<hr>";

try {
    // Configuración de base de datos
    $host = '145.223.105.42';
    $port = '3306';
    $db_name = 'u839374897_erp';
    $username = 'u839374897_erp';
    $password = 'ERpPitHay2025$';
    
    $dsn = "mysql:host={$host};port={$port};dbname={$db_name};charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    echo "✅ <strong style='color: green;'>Conexión a BD exitosa</strong><br><br>";
    
    // Verificar tablas existentes
    echo "<h3>Verificando Tablas Existentes:</h3>";
    $existing_tables = ['sucursales', 'Operarios'];
    
    foreach ($existing_tables as $table) {
        try {
            $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            echo "✅ <strong style='color: green;'>$table</strong> - $count registros encontrados<br>";
        } catch (Exception $e) {
            echo "❌ <strong style='color: red;'>$table</strong> - NO EXISTE: " . $e->getMessage() . "<br>";
        }
    }
    
    echo "<br>";
    
    // Array con todas las queries de creación para las nuevas tablas
    $queries = [
        'mtto_tipos_casos' => "
            CREATE TABLE IF NOT EXISTS mtto_tipos_casos (
                id INT PRIMARY KEY AUTO_INCREMENT,
                nombre VARCHAR(100) NOT NULL,
                descripcion TEXT,
                activo TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        'mtto_equipos' => "
            CREATE TABLE IF NOT EXISTS mtto_equipos (
                id INT PRIMARY KEY AUTO_INCREMENT,
                nombre VARCHAR(100) NOT NULL,
                descripcion TEXT,
                activo TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        'mtto_tickets' => "
            CREATE TABLE IF NOT EXISTS mtto_tickets (
                id INT PRIMARY KEY AUTO_INCREMENT,
                codigo VARCHAR(20) UNIQUE NOT NULL,
                titulo VARCHAR(255) NOT NULL,
                descripcion TEXT NOT NULL,
                tipo_formulario ENUM('mantenimiento_general', 'cambio_equipos') NOT NULL,
                cod_operario VARCHAR(20) NOT NULL,
                cod_sucursal VARCHAR(20) NOT NULL,
                area_equipo VARCHAR(255) NOT NULL,
                foto VARCHAR(255),
                nivel_urgencia INT DEFAULT NULL,
                fecha_inicio DATE DEFAULT NULL,
                fecha_final DATE DEFAULT NULL,
                status ENUM('solicitado', 'clasificado', 'agendado', 'finalizado') DEFAULT 'solicitado',
                tipo_caso_id INT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_cod_operario (cod_operario),
                INDEX idx_cod_sucursal (cod_sucursal),
                INDEX idx_status (status),
                INDEX idx_fecha_inicio (fecha_inicio),
                FOREIGN KEY (tipo_caso_id) REFERENCES mtto_tipos_casos(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        'mtto_chat_mensajes' => "
            CREATE TABLE IF NOT EXISTS mtto_chat_mensajes (
                id INT PRIMARY KEY AUTO_INCREMENT,
                ticket_id INT NOT NULL,
                emisor_tipo ENUM('mantenimiento', 'solicitante') NOT NULL,
                emisor_nombre VARCHAR(100) NOT NULL,
                mensaje TEXT,
                foto VARCHAR(255),
                is_pinned TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ticket_id (ticket_id),
                INDEX idx_created_at (created_at),
                FOREIGN KEY (ticket_id) REFERENCES mtto_tickets(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        "
    ];
    
    // Crear tablas
    echo "<h3>Creando Tablas Nuevas:</h3>";
    foreach ($queries as $table_name => $query) {
        try {
            $pdo->exec($query);
            echo "✅ <strong style='color: green;'>$table_name</strong> - Creada exitosamente<br>";
        } catch (Exception $e) {
            echo "❌ <strong style='color: red;'>$table_name</strong> - Error: " . $e->getMessage() . "<br>";
        }
    }
    
    echo "<br>";
    
    // Insertar datos iniciales
    echo "<h3>Insertando Datos Iniciales:</h3>";
    
    // Datos para tipos de casos
    $tipos_casos = [
        ['Mantenimiento Eléctrico', 'Reparaciones y mantenimiento del sistema eléctrico'],
        ['Mantenimiento Fontanería', 'Reparaciones de tuberías, llaves y sistema hidráulico'],
        ['Reparación de Infraestructura', 'Reparaciones en paredes, techos, pisos'],
        ['Mantenimiento de Equipos', 'Reparación y mantenimiento de equipos tecnológicos'],
        ['Limpieza y Aseo', 'Servicios de limpieza y mantenimiento general'],
        ['Aire Acondicionado', 'Mantenimiento y reparación de sistemas de climatización'],
        ['Jardinería', 'Mantenimiento de áreas verdes y jardines'],
        ['Seguridad', 'Sistemas de seguridad, cámaras, alarmas']
    ];
    
    try {
        // Verificar si ya existen datos
        $count = $pdo->query("SELECT COUNT(*) FROM mtto_tipos_casos")->fetchColumn();
        
        if ($count == 0) {
            $stmt = $pdo->prepare("INSERT INTO mtto_tipos_casos (nombre, descripcion) VALUES (?, ?)");
            foreach ($tipos_casos as $tipo) {
                $stmt->execute($tipo);
            }
            echo "✅ <strong style='color: green;'>Tipos de casos</strong> - " . count($tipos_casos) . " registros insertados<br>";
        } else {
            echo "ℹ️ <strong style='color: blue;'>Tipos de casos</strong> - Ya existen $count registros<br>";
        }
    } catch (Exception $e) {
        echo "❌ <strong style='color: red;'>Tipos de casos</strong> - Error: " . $e->getMessage() . "<br>";
    }
    
    // Datos para equipos
    $equipos = [
        ['Computadora de Escritorio', 'PC para uso administrativo'],
        ['Laptop', 'Computadora portátil'],
        ['Impresora', 'Equipo de impresión'],
        ['Proyector', 'Equipo de proyección'],
        ['Aires Acondicionados', 'Sistema de climatización'],
        ['Teléfono IP', 'Teléfono de red'],
        ['Scanner', 'Equipo de digitalización'],
        ['Monitor', 'Pantalla de computadora'],
        ['UPS', 'Sistema de alimentación ininterrumpida'],
        ['Router/Switch', 'Equipos de red'],
        ['Tablet', 'Dispositivo móvil'],
        ['Cámara de Seguridad', 'Sistema de videovigilancia']
    ];
    
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM mtto_equipos")->fetchColumn();
        
        if ($count == 0) {
            $stmt = $pdo->prepare("INSERT INTO mtto_equipos (nombre, descripcion) VALUES (?, ?)");
            foreach ($equipos as $equipo) {
                $stmt->execute($equipo);
            }
            echo "✅ <strong style='color: green;'>Equipos</strong> - " . count($equipos) . " registros insertados<br>";
        } else {
            echo "ℹ️ <strong style='color: blue;'>Equipos</strong> - Ya existen $count registros<br>";
        }
    } catch (Exception $e) {
        echo "❌ <strong style='color: red;'>Equipos</strong> - Error: " . $e->getMessage() . "<br>";
    }
    
    echo "<br>";
    
    // Verificar estructura final
    echo "<h3>Verificación Final - Todas las Tablas:</h3>";
    $all_tables = ['sucursales', 'Operarios', 'mtto_tipos_casos', 'mtto_equipos', 'mtto_tickets', 'mtto_chat_mensajes'];
    
    foreach ($all_tables as $table) {
        try {
            $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            $icon = (strpos($table, 'mtto_') === 0) ? '🆕' : '📋';
            echo "$icon <strong style='color: green;'>$table</strong> - $count registros<br>";
        } catch (Exception $e) {
            echo "❌ <strong style='color: red;'>$table</strong> - Error: " . $e->getMessage() . "<br>";
        }
    }
    
    echo "<br>";
    
    // Mostrar estructura de las tablas existentes
    echo "<h3>Estructura de Tablas Existentes:</h3>";
    
    // Mostrar estructura de sucursales
    try {
        $columns = $pdo->query("DESCRIBE sucursales")->fetchAll();
        echo "<strong>📋 Tabla: sucursales</strong><br>";
        echo "<ul>";
        foreach ($columns as $col) {
            echo "<li><code>{$col['Field']}</code> - {$col['Type']}</li>";
        }
        echo "</ul>";
    } catch (Exception $e) {
        echo "❌ Error al obtener estructura de sucursales<br>";
    }
    
    // Mostrar estructura de Operarios
    try {
        $columns = $pdo->query("DESCRIBE Operarios")->fetchAll();
        echo "<strong>📋 Tabla: Operarios</strong><br>";
        echo "<ul>";
        foreach ($columns as $col) {
            echo "<li><code>{$col['Field']}</code> - {$col['Type']}</li>";
        }
        echo "</ul>";
    } catch (Exception $e) {
        echo "❌ Error al obtener estructura de Operarios<br>";
    }
    
    echo "<br><hr>";
    echo "<h3 style='color: green;'>🎉 ¡Instalación completada exitosamente!</h3>";
    echo "<p>Las nuevas tablas han sido creadas y los datos iniciales insertados.</p>";
    echo "<p>El sistema utilizará las tablas existentes <strong>sucursales</strong> y <strong>Operarios</strong>.</p>";
    echo "<p><strong>Próximos pasos:</strong></p>";
    echo "<ol>";
    echo "<li>✅ Crear los directorios de uploads si no existen</li>";
    echo "<li>✅ Verificar permisos de escritura en uploads/</li>";
    echo "<li>🚀 Probar los formularios de mantenimiento</li>";
    echo "</ol>";
    
} catch (Exception $e) {
    echo "❌ <strong style='color: red;'>Error fatal:</strong><br>";
    echo $e->getMessage() . "<br>";
    echo "<br><strong>Detalles del error:</strong><br>";
    echo "Archivo: " . $e->getFile() . "<br>";
    echo "Línea: " . $e->getLine() . "<br>";
}

echo "<br><em>Fecha de instalación: " . date('Y-m-d H:i:s') . "</em>";
?>