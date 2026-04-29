<?php
/**
 * ajax/promo_aplicar_prueba.php — Motor de evaluación de promociones (Simulación), de prueba
 */

// Desactivar salida de errores al navegador para evitar corromper el JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    // 1. Cargar dependencias
    require_once '../../../core/auth/auth.php';
    require_once '../../../core/database/conexion.php';

    // 2. Leer Input
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!$data || !isset($data['context']) || !isset($data['cart'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Datos de entrada incompletos o inválidos']);
        exit;
    }

    $context      = $data['context'];
    $cart         = $data['cart'];
    $approved_ids = isset($data['approved_ids']) ? (array)$data['approved_ids'] : [];

    // 3. Obtener todas las promociones activas
    $sql = "SELECT p.*, 
            (SELECT COUNT(*) FROM promo_condiciones WHERE promo_id = p.id) as num_condiciones
            FROM promo_promociones p 
            WHERE p.estado = 'activa' 
            AND (p.fecha_inicio IS NULL OR p.fecha_inicio <= CURDATE())
            AND (p.fecha_fin IS NULL OR p.fecha_fin >= CURDATE())
            ORDER BY p.prioridad ASC, p.id DESC";
    $stmt = $conn->query($sql);
    $promociones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Preparar el carrito para evaluación (campos de seguimiento)
    foreach ($cart as &$item) {
        $item['descuento_total'] = 0;
        $item['promos'] = [];
        $item['precio_original'] = (float)($item['precio'] ?? 0);
        $item['cantidad_libre'] = (int)($item['cantidad'] ?? 0);
    }
    unset($item);

    $promos_aplicadas = [];
    $promos_califican = []; 
    $promos_potenciales = []; // NUEVO: Promociones que cumplen al menos una condición
    $promos_descartadas = [];
    $total_descuento_global_extra = 0;

    // 5. Evaluar cada promoción
    foreach ($promociones as $p) {
        $promo_id = $p['id'];
        
        // Obtener condiciones
        $stmt_c = $conn->prepare("SELECT * FROM promo_condiciones WHERE promo_id = :pid ORDER BY orden");
        $stmt_c->execute([':pid' => $promo_id]);
        $condiciones = $stmt_c->fetchAll(PDO::FETCH_ASSOC);

        $total_conds = count($condiciones);
        $conds_met = 0;
        $pasa_contexto = true;
        // qualifying_indices guardará los índices del carrito que califican para esta promo
        $qualifying_indices = array_keys($cart); 
        $has_product_condition = false;
        $motivo_descarte = '';

        // EVALUAR CONDICIONES
        foreach ($condiciones as $c) {
            $valor = json_decode($c['valor_json'], true);
            $nombre = $c['nombre_cond'];
            $met_esta_condicion = true;

            if ($c['tipo_cond'] === 'A') {
                // TIPO A: CONTEXTO (Aplica a toda la evaluación)
                switch ($nombre) {
                    case 'dia_semana':
                        if (!isset($valor['dias']) || !in_array($context['dia'], $valor['dias'])) {
                            $met_esta_condicion = false;
                            $pasa_contexto = false;
                            $motivo_descarte = "Día no permitido: " . $context['dia'];
                        }
                        break;
                    case 'sucursal':
                        if (!isset($valor['ids']) || !in_array($context['sucursal'], $valor['ids'])) {
                            $met_esta_condicion = false;
                            $pasa_contexto = false;
                            $motivo_descarte = "Sucursal no permitida.";
                        }
                        break;
                    case 'horario':
                        $hora_actual = $context['hora'];
                        if ($hora_actual < ($valor['desde'] ?? '00:00') || $hora_actual > ($valor['hasta'] ?? '23:59')) {
                            $met_esta_condicion = false;
                            $pasa_contexto = false;
                            $motivo_descarte = "Fuera de horario";
                        }
                        break;
                    case 'canal_venta':
                        if (!isset($valor['canales']) || !in_array($context['canal'], $valor['canales'])) {
                            $met_esta_condicion = false;
                            $pasa_contexto = false;
                            $motivo_descarte = "Canal no válido.";
                        }
                        break;
                    case 'tipo_cliente':
                        if (!isset($valor['tipos']) || !in_array($context['tipo_cliente'], $valor['tipos'])) {
                            $met_esta_condicion = false;
                            $pasa_contexto = false;
                            $motivo_descarte = "Tipo de cliente no aplica.";
                        }
                        break;
                }
            } else {
                // TIPO B: CARRITO (Filtra ítems o valida totales)
                switch ($nombre) {
                    case 'producto':
                        $has_product_condition = true;
                        $match_found = false;
                        $new_qualifying = [];
                        foreach ($qualifying_indices as $idx) {
                            $it = $cart[$idx];
                            if ($it['id'] == $valor['id_producto'] || ($it['id_producto_maestro'] ?? 0) == $valor['id_producto']) {
                                if ($it['cantidad_libre'] >= ($valor['cantidad_min'] ?? 1)) {
                                    $new_qualifying[] = $idx;
                                    $match_found = true;
                                }
                            }
                        }
                        if (!$match_found) {
                            $met_esta_condicion = false;
                            $qualifying_indices = [];
                            $motivo_descarte = "Falta producto: " . ($valor['nombre_producto'] ?? 'ID '.$valor['id_producto']);
                        } else {
                            $qualifying_indices = $new_qualifying;
                        }
                        break;

                    case 'grupo_producto':
                        $has_product_condition = true;
                        $match_found = false;
                        $new_qualifying = [];
                        foreach ($qualifying_indices as $idx) {
                            $it = $cart[$idx];
                            if (($it['id_grupo'] ?? 0) == $valor['id_grupo'] && $it['cantidad_libre'] > 0) {
                                $new_qualifying[] = $idx;
                                $match_found = true;
                            }
                        }
                        if (!$match_found) {
                            $met_esta_condicion = false;
                            $qualifying_indices = [];
                            $motivo_descarte = "No hay productos del grupo: " . ($valor['nombre_grupo'] ?? 'ID '.$valor['id_grupo']);
                        } else {
                            $qualifying_indices = $new_qualifying;
                        }
                        break;

                    case 'monto_min':
                        $total_monto = 0;
                        foreach ($cart as $it) $total_monto += ($it['precio_original'] * $it['cantidad']);
                        if ($total_monto < ($valor['monto'] ?? 0)) {
                            $met_esta_condicion = false;
                            $qualifying_indices = [];
                            $motivo_descarte = "Monto mínimo insuficiente.";
                        }
                        break;

                    case 'cantidad_min':
                        $total_items = 0;
                        foreach ($cart as $it) $total_items += $it['cantidad'];
                        if ($total_items < ($valor['cantidad'] ?? 1)) {
                            $met_esta_condicion = false;
                            $qualifying_indices = [];
                            $motivo_descarte = "Cantidad mínima no alcanzada.";
                        }
                        break;
                }
            }

            if ($met_esta_condicion) $conds_met++;
        }

        // Si se cumplen TODAS las condiciones
        if ($conds_met === $total_conds && !empty($qualifying_indices)) {
            $aplicado = false;
            $monto_descuento_promo = 0;
            
            // 1. DETERMINAR ÍTEMS A DESCONTAR
            $candidatos = []; 
            foreach ($qualifying_indices as $idx) {
                for ($i = 0; $i < $cart[$idx]['cantidad_libre']; $i++) {
                    $candidatos[] = $idx;
                }
            }

            // 2. APLICAR SEGÚN OBJETIVO
            $items_finales = []; 
            switch ($p['objetivo_descuento']) {
                case 'todos':
                    foreach ($qualifying_indices as $idx) $items_finales[$idx] = $cart[$idx]['cantidad'];
                    break;
                case 'mas_barato':
                    $min_idx = -1; $min_p = PHP_FLOAT_MAX;
                    foreach ($qualifying_indices as $idx) {
                        if ($cart[$idx]['precio_original'] < $min_p) {
                            $min_p = $cart[$idx]['precio_original'];
                            $min_idx = $idx;
                        }
                    }
                    if ($min_idx !== -1) $items_finales[$min_idx] = 1;
                    break;
                case 'nth_item':
                    $n = (int)($p['objetivo_nth_numero'] ?: 2);
                    if (count($candidatos) >= $n) {
                        $idx = $candidatos[$n - 1];
                        $items_finales[$idx] = ($items_finales[$idx] ?? 0) + 1;
                    }
                    break;
                case 'get_y':
                    $y_prod_id = $p['objetivo_get_y_prod'];
                    $y_cant    = (int)($p['objetivo_get_y_cant'] ?: 1);
                    foreach ($cart as $idx => $it) {
                        if ($it['id'] == $y_prod_id || ($it['id_producto_maestro'] ?? 0) == $y_prod_id) {
                            $items_finales[$idx] = min($it['cantidad'], $y_cant);
                            break;
                        }
                    }
                    break;
                case 'factura': break;
            }

            // 3. EJECUTAR DESCUENTO
            if ($p['objetivo_descuento'] === 'factura') {
                if ($p['resultado_tipo'] === 'pct_factura') {
                    $subtotal = 0;
                    foreach ($cart as $it) $subtotal += ($it['precio_original'] * $it['cantidad']);
                    $monto_descuento_promo = $subtotal * ($p['resultado_valor'] / 100);
                } elseif ($p['resultado_tipo'] === 'monto_factura') {
                    $monto_descuento_promo = (float)$p['resultado_valor'];
                }
                $aplicado = ($monto_descuento_promo > 0);
            } else {
                foreach ($items_finales as $idx => $cant_desc) {
                    $item = &$cart[$idx];
                    $desc_unitario = ($p['resultado_tipo'] === 'pct_producto') 
                        ? ($item['precio_original'] * ($p['resultado_valor'] / 100))
                        : (float)$p['resultado_valor'];

                    $total_desc_item = $desc_unitario * $cant_desc;
                    if ($total_desc_item > 0) {
                        if (in_array($promo_id, $approved_ids)) {
                            $item['descuento_total'] += $total_desc_item;
                            $item['promos'][] = ['id' => $p['id'], 'nombre' => $p['nombre']];
                            if ($p['combinable'] == 0) $item['cantidad_libre'] -= $cant_desc;
                        }
                        $monto_descuento_promo += $total_desc_item;
                        $aplicado = true;
                    }
                }
                unset($item);
            }

            if ($p['descuento_maximo_cs'] > 0 && $monto_descuento_promo > $p['descuento_maximo_cs']) {
                $monto_descuento_promo = (float)$p['descuento_maximo_cs'];
            }

            if ($aplicado) {
                if (in_array($promo_id, $approved_ids)) {
                    $promos_aplicadas[] = ['id' => $p['id'], 'nombre' => $p['nombre'], 'resumen' => "Ahorro: C$" . number_format($monto_descuento_promo, 2)];
                    if ($p['objetivo_descuento'] === 'factura') $total_descuento_global_extra += $monto_descuento_promo;
                } else {
                    $promos_califican[] = ['id' => $p['id'], 'nombre' => $p['nombre'], 'resumen' => "Podrías ahorrar C$" . number_format($monto_descuento_promo, 2)];
                }
            } else {
                $promos_descartadas[] = ['id' => $p['id'], 'nombre' => $p['nombre'], 'motivo' => "No se encontraron ítems válidos."];
            }
        } elseif ($conds_met > 0) {
            // NUEVO: Promoción POTENCIAL (cumple algunas condiciones pero no todas)
            $promos_potenciales[] = [
                'id' => $p['id'],
                'nombre' => $p['nombre'],
                'condiciones_met' => $conds_met,
                'total_condiciones' => $total_conds,
                'faltante' => $motivo_descarte ?: "Faltan condiciones por cumplir."
            ];
        } else {
            $promos_descartadas[] = [
                'id' => $p['id'],
                'nombre' => $p['nombre'],
                'motivo' => $motivo_descarte ?: "No cumple las condiciones requeridas."
            ];
        }
    }

    // 6. Totales Finales
    $total_original = 0;
    $total_descuento = 0;
    foreach ($cart as &$item) {
        $total_original += ($item['precio_original'] * $item['cantidad']);
        $total_descuento += $item['descuento_total'];
        $item['subtotal_final'] = ($item['precio_original'] * $item['cantidad']) - $item['descuento_total'];
    }
    unset($item);

    $total_descuento += $total_descuento_global_extra;

    // 7. Respuesta JSON
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'items' => $cart,
        'total_original' => $total_original,
        'total_descuento' => $total_descuento,
        'total_final' => $total_original - $total_descuento,
        'promos_aplicadas' => $promos_aplicadas,
        'promos_califican' => $promos_califican,
        'promos_potenciales' => $promos_potenciales,
        'promos_descartadas' => $promos_descartadas
    ]);

} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
