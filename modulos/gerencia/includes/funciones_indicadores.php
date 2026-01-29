<?php
/**
 * Calcula resultado de indicador con multiplicador especial
 */
function calcularResultadoConMultiplicador($indicador, $resultadoBD)
{
    if (!$resultadoBD)
        return null;

    // Indicador ID 1 (Rotación de Personal) tiene multiplicador 4.2 en numerador
    $multiplicador = ($indicador['id'] == 1) ? 4.2 : 1;

    if ($indicador['divide'] == 1) {
        if (
            $resultadoBD['numerador_dato'] !== null &&
            $resultadoBD['denominador_dato'] !== null &&
            $resultadoBD['denominador_dato'] != 0
        ) {
            // Aplicar multiplicador al numerador antes de dividir
            return ($resultadoBD['numerador_dato'] * $multiplicador) / $resultadoBD['denominador_dato'];
        }
        return null;
    } else {
        // Si no divide, mostrar numerador directamente
        if ($resultadoBD['numerador_dato'] !== null) {
            return $resultadoBD['numerador_dato'];
        }
        return null;
    }
}

/**
 * Formatea un valor según tipo y decimales, considerando si el indicador está en uso
 * MODIFICADA: Si EnUso = 1 y valor es null, retorna 0 formateado
 */
function formatearValor($valor, $tipo, $decimales, $enUso = 0)
{
    // Si EnUso = 1 y valor es null/vacío, usar 0
    if ($enUso == 1 && ($valor === null || $valor === '')) {
        $valor = 0;
    }

    if ($valor === null || $valor === '') {
        return '-';
    }

    // Asegurar que decimales sea un número válido
    $decimales = is_numeric($decimales) ? intval($decimales) : 2;

    // Normalizar tipo
    $tipo = strtolower(trim($tipo));

    if ($tipo === 'porcentaje') {
        // Para porcentaje, mostrar con decimales y símbolo %
        return number_format($valor * 100, $decimales, '.', ',') . '%';
    } else {
        // Para entero, mostrar con decimales especificados
        return number_format($valor, $decimales, '.', ',');
    }
}

/**
 * Formatea un valor de resultado considerando si el indicador está en uso
 * MODIFICADA: Si EnUso = 1 y valor es null, retorna 0 formateado
 */
function formatearResultado($valor, $indicador)
{
    // Si EnUso = 1 y valor es null/vacío, usar 0
    $enUso = $indicador['EnUso'] ?? 0;
    if ($enUso == 1 && ($valor === null || $valor === '')) {
        $valor = 0;
    }

    if ($valor === null)
        return '-';

    $decimales = is_numeric($indicador['decimales']) ? intval($indicador['decimales']) : 2;
    $tipo = strtolower(trim($indicador['tipo']));

    // Si divide=1, el resultado es porcentaje
    if ($indicador['divide'] == 1) {
        return number_format($valor * 100, $decimales, '.', ',') . ' %';
    } else {
        // Si divide=0, usar el tipo definido
        if ($tipo === 'porcentaje') {
            return number_format($valor, $decimales, '.', ',') . '%';
        } else {
            return number_format($valor, $decimales, '.', ',');
        }
    }
}

/**
 * Formatea valor para indicadores semanales considerando EnUso
 */
function formatearValorIndicador($valor, $tipo, $decimales, $enUso = 0)
{
    // Si EnUso = 1 y valor es null/vacío, usar 0
    if ($enUso == 1 && ($valor === null || $valor === '')) {
        $valor = 0;
    }

    if ($valor === null || $valor === '') {
        return '-';
    }

    // Asegurar que decimales sea un número válido
    $decimales = is_numeric($decimales) ? intval($decimales) : 2;

    // Normalizar tipo
    $tipo = strtolower(trim($tipo));

    if ($tipo === 'porcentaje') {
        // Para porcentaje, mostrar con decimales y símbolo %
        return number_format($valor * 100, $decimales, '.', ',') . '%';
    } else {
        // Para entero, mostrar con decimales especificados
        return number_format($valor, $decimales, '.', ',');
    }
}