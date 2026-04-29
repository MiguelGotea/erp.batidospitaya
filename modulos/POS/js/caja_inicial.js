/* ============================================================
   caja_inicial.js — Lógica del módulo Caja Inicial POS
   ============================================================ */

$(function () {

    /* ------------------------------------------------------------------
       CONFIGURACIÓN DE DENOMINACIONES
    ------------------------------------------------------------------ */
    const DENOMS_NIO = [0.5, 1, 5, 10, 20, 50, 100, 200, 500];
    const DENOMS_USD = [1, 5, 10, 20, 50, 100];

    // tipo de cambio inyectado desde PHP (window.TIPO_CAMBIO)
    const TC = parseFloat(window.TIPO_CAMBIO || 36.6);

    /* ------------------------------------------------------------------
       HELPERS
    ------------------------------------------------------------------ */
    const fmtNIO = (n) => 'C$ ' + parseFloat(n || 0).toLocaleString('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const fmtUSD = (n) => '$ '  + parseFloat(n || 0).toLocaleString('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    /* ------------------------------------------------------------------
       RENDERIZADO DE TABLAS
    ------------------------------------------------------------------ */

    function buildRow(denom, moneda) {
        const tag    = moneda === 'NIO' ? 'NIO' : 'USD';
        const cls    = moneda === 'NIO' ? ''     : 'usd';
        const labelF = moneda === 'NIO'
            ? (denom < 1 ? denom.toFixed(2) : denom.toFixed(2).replace('.00',''))
            : '$' + denom;

        return `
        <tr data-moneda="${moneda}" data-denom="${denom}">
            <td>
                <span class="denom-label ${cls}">
                    <span class="denom-tag">${labelF}</span>
                </span>
            </td>
            <td class="text-center">
                <input type="number"
                       class="ci-input-qty qty-input"
                       min="0" step="1" value="0"
                       data-moneda="${moneda}"
                       data-denom="${denom}"
                       id="qty_${moneda}_${String(denom).replace('.','_')}"
                       autocomplete="off">
            </td>
            <td class="text-end ci-row-total" id="total_${moneda}_${String(denom).replace('.','_')}">
                ${moneda === 'NIO' ? fmtNIO(0) : fmtUSD(0)}
            </td>
        </tr>`;
    }

    // Córdobas
    const bodyNIO = $('#tablaNIOBody');
    DENOMS_NIO.forEach(d => bodyNIO.append(buildRow(d, 'NIO')));

    // Dólares
    const bodyUSD = $('#tablaUSDBody');
    DENOMS_USD.forEach(d => bodyUSD.append(buildRow(d, 'USD')));

    /* ------------------------------------------------------------------
       CÁLCULO DE TOTALES
    ------------------------------------------------------------------ */
    function recalcular() {
        let totalNIO = 0;
        let totalUSD = 0;

        // Sumar NIO
        $('[data-moneda="NIO"].qty-input').each(function () {
            const denom = parseFloat($(this).data('denom'));
            const qty   = parseInt($(this).val()) || 0;
            const sub   = denom * qty;
            totalNIO += sub;
            const key = String(denom).replace('.', '_');
            $(`#total_NIO_${key}`).text(fmtNIO(sub));
        });

        // Sumar USD
        $('[data-moneda="USD"].qty-input').each(function () {
            const denom = parseFloat($(this).data('denom'));
            const qty   = parseInt($(this).val()) || 0;
            const sub   = denom * qty;
            totalUSD += sub;
            const key = String(denom).replace('.', '_');
            $(`#total_USD_${key}`).text(fmtUSD(sub));
        });

        const totalUSDenNIO = totalUSD * TC;
        const totalGlobal   = totalNIO + totalUSDenNIO;

        // Actualizar subtotales córdobas
        $('#subtotalNIO').text(fmtNIO(totalNIO));

        // Actualizar subtotales dólares
        $('#subtotalUSD').text(fmtUSD(totalUSD));
        $('#subtotalUSDenNIO').text(fmtNIO(totalUSDenNIO));

        // Total global
        $('#totalEfectivoGlobal').text(fmtNIO(totalGlobal));

        // Guardar valores en inputs ocultos
        $('#hidTotalNIO').val(totalNIO.toFixed(2));
        $('#hidTotalUSD').val(totalUSD.toFixed(2));
        $('#hidTotalUSDenNIO').val(totalUSDenNIO.toFixed(2));
        $('#hidTotalGlobal').val(totalGlobal.toFixed(2));
    }

    // Recalcular al cambiar cualquier cantidad
    $(document).on('input change', '.qty-input', function () {
        // Sólo enteros no negativos
        let v = parseInt($(this).val());
        if (isNaN(v) || v < 0) { $(this).val(0); }
        recalcular();
    });

    // Mejorar UX: Limpiar 0 al enfocar, restaurar si queda vacío al desenfocar
    $(document).on('focus', '.qty-input', function() {
        if ($(this).val() == '0') {
            $(this).val('');
        }
    });

    $(document).on('blur', '.qty-input', function() {
        if ($(this).val() === '') {
            $(this).val(0);
            recalcular(); // Corregido: recalcular() en lugar de recalculate()
        }
    });

    // Carga inicial
    recalcular();

    /* ------------------------------------------------------------------
       LIMPIAR FORMULARIO
    ------------------------------------------------------------------ */
    $('#btnLimpiar').on('click', function () {
        Swal.fire({
            title: '¿Limpiar conteo?',
            text: 'Se restablecerán todas las cantidades a cero.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#51B8AC',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Sí, limpiar',
            cancelButtonText: 'Cancelar'
        }).then(result => {
            if (result.isConfirmed) {
                $('.qty-input').val(0);
                recalcular();
            }
        });
    });

    /* ------------------------------------------------------------------
       GUARDAR CAJA INICIAL
    ------------------------------------------------------------------ */
    $('#formCajaInicial').on('submit', function (e) {
        e.preventDefault();

        const fecha      = $('#inputFecha').val();
        const sucursalId = $('#inputSucursalId').val();

        if (!fecha) {
            Swal.fire('Campo requerido', 'Por favor seleccioná una fecha.', 'warning');
            return;
        }

        if (!sucursalId) {
            Swal.fire('Error', 'No se detectó sucursal para este usuario.', 'error');
            return;
        }

        // Calcular total global para mostrar en la confirmación
        const totalGlobal = parseFloat($('#hidTotalGlobal').val());
        const totalGlobalFmt = fmtNIO(totalGlobal);

        Swal.fire({
            title: '¿Confirmar Guardado?',
            html: `¿Estás seguro de registrar el conteo de esta caja?<br><br><strong>Total Global: ${totalGlobalFmt}</strong>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#51B8AC',
            cancelButtonColor: '#94a3b8',
            confirmButtonText: 'Sí, guardar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                ejecutarGuardado();
            }
        });

        function ejecutarGuardado() {
            // Construir arreglo de denominaciones
            const detalles = [];
            $('.qty-input').each(function () {
                const qty = parseInt($(this).val()) || 0;
                detalles.push({
                    moneda:      $(this).data('moneda'),
                    denominacion: parseFloat($(this).data('denom')),
                    cantidad:    qty,
                    total:       parseFloat(($(this).data('denom') * qty).toFixed(2))
                });
            });

            const payload = {
                fecha:                   fecha,
                sucursal_id:             sucursalId,
                tipo_cambio_usado:       TC,
                total_cordobas:          parseFloat($('#hidTotalNIO').val()),
                total_dolares:           parseFloat($('#hidTotalUSD').val()),
                total_dolares_en_cordobas: parseFloat($('#hidTotalUSDenNIO').val()),
                total_efectivo_global:   parseFloat($('#hidTotalGlobal').val()),
                detalles:                detalles
            };

            const btn = $('#btnGuardar');
            btn.prop('disabled', true).addClass('saving').html('<i class="bi bi-hourglass-split"></i> Guardando…');

            $.ajax({
                url:         'ajax/guardar_caja_inicial.php',
                method:      'POST',
                contentType: 'application/json',
                data:        JSON.stringify(payload),
                dataType:    'json'
            })
            .done(function (res) {
                if (res.ok) {
                    Swal.fire({
                        title: '¡Guardado!',
                        html:  `Caja inicial registrada correctamente.<br>
                                <strong>Total Efectivo: ${fmtNIO(payload.total_efectivo_global)}</strong>`,
                        icon: 'success',
                        confirmButtonColor: '#51B8AC'
                    }).then(() => {
                        // Limpiar tras guardar
                        $('.qty-input').val(0);
                        recalcular();
                    });
                } else {
                    Swal.fire('Error', res.mensaje || 'Ocurrió un error al guardar.', 'error');
                }
            })
            .fail(function () {
                Swal.fire('Error de red', 'No se pudo conectar con el servidor.', 'error');
            })
            .always(function () {
                btn.prop('disabled', false).removeClass('saving').html('<i class="bi bi-save2-fill"></i> Guardar Caja Inicial');
            });
        }
    });

});
