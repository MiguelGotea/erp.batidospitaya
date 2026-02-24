<?php
/**
 * unidades_homologacion.php
 * Librería de equivalencias entre unidades del sistema antiguo y el nuevo ERP.
 */

/**
 * Retorna un array de nombres de unidades del nuevo ERP que son equivalentes
 * a la unidad del sistema antiguo proporcionada.
 * 
 * @param string $unidadAntigua
 * @return array
 */
function obtenerUnidadesERPSimilares($unidadAntigua)
{
    if (!$unidadAntigua)
        return [];

    $u = strtolower(trim($unidadAntigua));

    // Mapeo inverso: unidad antigua (lower) => [Nombres exactos en el nuevo ERP]
    $map = [
        'oz' => ['Onzas Liquidas', 'Onzas Peso'],
        'fl oz' => ['Onzas Liquidas'],
        'wt oz' => ['Onzas Peso'],
        'onzas liquidas' => ['Onzas Liquidas'],
        'onzas peso' => ['Onzas Peso'],
        'onza' => ['Onzas Liquidas', 'Onzas Peso'],
        'ml' => ['Mililitros'],
        'ml.' => ['Mililitros'],
        'mls' => ['Mililitros'],
        'mililitros' => ['Mililitros'],
        'lt' => ['Litros'],
        'l' => ['Litros'],
        'l.' => ['Litros'],
        'litros' => ['Litros'],
        'gr' => ['Gramos'],
        'g' => ['Gramos'],
        'g.' => ['Gramos'],
        'grs' => ['Gramos'],
        'gramos' => ['Gramos'],
        'kg' => ['Kilogramos'],
        'kilos' => ['Kilogramos'],
        'kilogramos' => ['Kilogramos'],
        'lb' => ['Libras'],
        'lbs' => ['Libras'],
        'libra' => ['Libras'],
        'libras' => ['Libras'],
        'tza' => ['Tazas'],
        'taza' => ['Tazas'],
        'tazas' => ['Tazas'],
        'cda' => ['Cucharadas'],
        'cucharada' => ['Cucharadas'],
        'cucharadas' => ['Cucharadas'],
        'tbsp' => ['Cucharadas'],
        'u' => ['Unidades'],
        'unidad' => ['Unidades'],
        'unidades' => ['Unidades'],
        'pieza' => ['Unidades'],
        'piezas' => ['Unidades'],
        'pz' => ['Unidades'],
        'rama' => ['Rama'],
        'ramas' => ['Rama'],
        'moño' => ['Moño'],
        'moños' => ['Moño'],
        'cajilla' => ['Cajilla'],
        'cajillas' => ['Cajilla']
    ];

    return $map[$u] ?? [$unidadAntigua]; // Si no hay mapeo, devuelve la misma (case-insensitive fallback)
}
?>