/**
 * APC Logistics - JavaScript helpers
 *
 * Contient :
 *   - Calcul dynamique totaux lignes tableaux (PU x Qte = Total)
 *   - Calcul ecart quantite / somme totaux generaux
 *   - Confirmations de suppression / validation
 *   - Helpers communs AJAX
 */

var APCLogistics = (function () {

    /**
     * Formate un nombre en devise avec separateurs de milliers et 2 decimales
     */
    function formatMoney(n, currency) {
        if (n === null || n === undefined || isNaN(n)) n = 0;
        var num = parseFloat(n).toFixed(2);
        var parts = num.split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        var sign = parts[0].startsWith('-') ? '-' : '';
        if (sign) parts[0] = parts[0].substring(1);
        return sign + parts.join(',') + (currency ? ' ' + currency : '');
    }

    /**
     * Calcule automatiquement PU * Qte = Total sur une ligne
     * Usage : appeler sur onChange de champs input.pu, input.qte dans un tableau lignes
     */
    function bindTableLineCalc(rowSelector, qtySelector, puSelector, totalSelector, cbRowTotalChanged) {
        $(document).on('input', rowSelector + ' ' + qtySelector + ', ' + rowSelector + ' ' + puSelector, function () {
            var row = $(this).closest(rowSelector);
            var q = parseFloat(row.find(qtySelector).val().replace(',', '.')) || 0;
            var pu = parseFloat(row.find(puSelector).val().replace(',', '.')) || 0;
            var t = (q * pu);
            row.find(totalSelector).text(formatMoney(t));
            row.find(totalSelector + '_raw').val(t.toFixed(2));
            if (cbRowTotalChanged) cbRowTotalChanged(row, t);
        });
    }

    /**
     * Additionne tous les totaux de lignes pour obtenir le general
     */
    function refreshGrandTotal(grandTotalSelector, cellTotalRawSelector, currency) {
        var sum = 0;
        $(cellTotalRawSelector).each(function () {
            sum += parseFloat($(this).val() || '0');
        });
        $(grandTotalSelector).text(formatMoney(sum, currency));
        $(grandTotalSelector + '_raw').val(sum.toFixed(2));
        return sum;
    }

    /**
     * Confirm avant action destructive / validation
     */
    function confirmAction(action, msg) {
        var translated = msg || 'Etes-vous sur de vouloir ' + action + ' ?';
        return window.confirm(translated);
    }

    /**
     * Active/desactive un bouton selon que tous les champs obligatoires d'un formulaire
     * sont remplis.
     */
    function bindSubmitEnable(formSelector, btnSelector, requiredSelector) {
        function check() {
            var allFilled = true;
            $(formSelector + ' ' + requiredSelector).each(function () {
                if ($.trim($(this).val()) === '') { allFilled = false; return false; }
            });
            $(btnSelector).prop('disabled', !allFilled);
        }
        $(document).on('input change', formSelector + ' ' + requiredSelector, check);
        check();
    }

    /**
     * Effectue un POST JSON minimal vers un endpoint Dolibarr (csrf-friendly)
     */
    function post(endpoint, data, token, okCb, errCb) {
        if (token) data.token = token;
        $.ajax({
            url: endpoint,
            type: 'POST',
            data: data,
            success: function (res) { if (okCb) okCb(res); },
            error: function (xhr, st, err) { if (errCb) errCb(xhr, st, err); }
        });
    }

    return {
        formatMoney: formatMoney,
        bindTableLineCalc: bindTableLineCalc,
        refreshGrandTotal: refreshGrandTotal,
        confirmAction: confirmAction,
        bindSubmitEnable: bindSubmitEnable,
        post: post
    };
})();
