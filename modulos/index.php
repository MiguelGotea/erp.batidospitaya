<?php
// /public_html/modulos/index.php

require_once '../core/auth/auth.php';

// Verificar que el usuario esté autenticado

// Registro de permiso de vista para el sistema de "tools" si no existe (opcional si se hace manual)
// Esto asegura que al entrar al index del módulo se registre en el sistema de permisos de herramientas

// Obtener todos los cargos del usuario de la sesión (no solo uno)
$cargosUsuario = obtenerCargosUsuario($_SESSION['usuario_id']);

// Mapeo de cargos a rutas de módulos (debe coincidir con lo definido en funciones.php)
$modulosPorCargo = [
    2 => 'operarios',               // Operario
    5 => 'lideres',                 // Líder de Sucursal
    8 => 'contabilidad',            // Jefe de Contabilidad
    9 => 'compras',                 // Jefe de Compras
    10 => 'logistica',              // Jefe de Logística
    11 => 'operaciones',            // Jefe de Operaciones
    12 => 'produccion',             // Jefe de Producción
    13 => 'rh',                     // Jefe de Recursos Humanos
    14 => 'mantenimiento',          // Jefe de Mantenimiento
    15 => 'sistemas',               // Jefe de Sistemas
    16 => 'gerencia',               // Gerencia
    17 => 'almacen',                // Jefe de Almacén
    19 => 'cds',                    // Jefe de CDS
    20 => 'chofer',                 // Chofer
    21 => 'supervision',            // Supervisor de Sucursales
    22 => 'atencioncliente',        // Atención al Cliente
    23 => 'almacen',                // Auxiliar de Almacén
    24 => 'motorizado',             // Motorizado
    25 => 'diseno',                 // Diseñador
    26 => 'marketing',              // Jefe de Marketing
    27 => 'sucursales',             // Sucursales
    35 => 'infraestructura',        // Líder de Infraestructura y Expansión Comercial
    28 => 'rh',                     // Técnico de Desarrollo Humano
    33 => 'desarrollo',             // Líder de Desarrollo
    38 => 'auxiliaradministrativo', // Auxiliar Administrativo
    39 => 'rh',                     // Responsable de Reclutamiento y Selección
    30 => 'rh',                     // Coordinadora de Clima y Cultura
    37 => 'rh',                     // Pasante de RRHH
    42 => 'marketing',              // Gerente de Marketing y Ventas
    43 => 'lideres',                // Líder Interino
    44 => 'operarios',              // Vendedor Training
    45 => 'operarios',              // Vendedor Junior
    46 => 'operarios',              // Vendedor Asistente de Líder
    47 => 'operarios',              // Vendedor Experto
    36 => 'operaciones',            // Líder General de Tiendas Managua
    49 => 'gerencia',               //Gerencia General 2
    50 => 'experienciadigital',      // Especialista en experiencia Digital del cliente
    53 => 'marketing',               // Coordinación de Mercadeo
    54 => 'rh',                     // Analista de clima y cultura
    55 => 'operaciones',            // Líder General de Tiendas Managua
    52 => 'auditor',            // Auditor de Tiendas
];

// Si es admin o no tiene cargos definidos, redirigir al inicio
if (empty($cargosUsuario)) {
    header("Location: /index.php");
    exit();
}

// Ordenar los cargos para priorizar los que no son 2 (Operario)
usort($cargosUsuario, function ($a, $b) {
    // Si ambos son 2 o ambos no son 2, mantener el orden original
    if (($a == 2 && $b == 2) || ($a != 2 && $b != 2)) {
        return 0;
    }
    // Priorizar el que no es 2
    return ($a == 2) ? 1 : -1;
});

// Buscar el primer cargo que tenga un módulo asignado
foreach ($cargosUsuario as $cargoCod) {
    if (array_key_exists($cargoCod, $modulosPorCargo)) {
        $modulo = $modulosPorCargo[$cargoCod];
        $rutaModulo = "/modulos/{$modulo}/index.php";

        // Verificar que el archivo del módulo exista
        if (file_exists("../modulos/{$modulo}/index.php")) {
            header("Location: $rutaModulo");
            exit();
        }
    }
}

// Si no se encontró un módulo válido, redirigir al inicio
header("Location: ../logout.php");
exit();
