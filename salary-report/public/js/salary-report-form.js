/**
 * Formulaire de rapport de salaire : sélecteur de période (mois simple ou
 * plage personnalisée) qui recalcule automatiquement le résumé financier via
 * AdminSalaryReportController::calculateSalaryReport(), au lieu de figer les
 * montants au moment du chargement de la page. Voir aussi le bouton "détail
 * des calculs", qui affiche la même donnée dans une modale sans toucher au
 * formulaire.
 */
(function () {
    'use strict';

    var container = document.getElementById('sr-period');
    if (!container) return;

    var form = container.closest('form');
    var calcUrl = container.dataset.calculateUrl;
    var isEdit = container.dataset.mode === 'edit';
    var userId = parseInt(container.dataset.userId, 10) || 0;
    var currency = container.dataset.currency || 'JPY';
    var currencyStyle = container.dataset.currencyStyle || 'kanji';

    var i18nEl = document.getElementById('sr-i18n');
    var i18n = i18nEl ? JSON.parse(i18nEl.textContent) : {};

    var modeButtons = container.querySelectorAll('[data-period-mode]');
    var thumb = container.querySelector('.btn-group__thumb');
    var monthPanel = container.querySelector('[data-period-panel="month"]');
    var customPanel = container.querySelector('[data-period-panel="custom"]');
    var monthInput = document.getElementById('sr-period-month');
    var fromInput = document.getElementById('sr-period-from');
    var toInput = document.getElementById('sr-period-to');
    var hiddenTargetMonth = document.getElementById('sr-target-month');
    var recalcBtn = document.getElementById('sr-recalculate');
    var detailBtn = document.getElementById('sr-calc-detail');
    var statusEl = document.getElementById('sr-period-status');
    var detailBody = document.getElementById('sr-calc-detail-body');

    var activeMode = 'month';
    var confirmedOverwrite = !isEdit;
    var requestId = 0;
    var debounceTimer = null;
    var DEBOUNCE_MS = 500;

    var AUTOFILL_FIELDS = [
        'total_payment', 'net_payment', 'income_tax_base', 'total_deductions',
        'withholding_tax', 'residence_tax', 'other_deductions', 'bank_transfer_salary',
        'staff_man_hours', 'staff_total_payment', 'staff_avg_hourly_wage',
        'active_employees', 'employee_work_hours',
    ];

    /* ── Champs montant : un <input type="number"> ne peut pas afficher de
       séparateur de milliers, ce qui rendait les gros montants illisibles
       (ex. "158269.64"). Ces champs sont donc des <input type="text"> formatés
       en direct ("158,269.64"), avec la valeur brute affichée pendant la
       saisie (focus) pour rester éditables normalement, et reconverties en
       nombre simple juste avant l'envoi du formulaire. ── */
    var moneyInputs = form ? form.querySelectorAll('.js-money-input') : [];

    function parseMoneyValue(raw) {
        var n = parseFloat(String(raw || '').replace(/,/g, ''));
        return isNaN(n) ? 0 : n;
    }

    function formatMoneyInput(input) {
        input.value = parseMoneyValue(input.value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatAllMoneyInputs() {
        moneyInputs.forEach(formatMoneyInput);
    }

    moneyInputs.forEach(function (input) {
        formatMoneyInput(input);
        input.addEventListener('focus', function () {
            input.value = String(parseMoneyValue(input.value));
        });
        input.addEventListener('blur', function () {
            formatMoneyInput(input);
        });
    });

    if (form) {
        form.addEventListener('submit', function () {
            moneyInputs.forEach(function (input) {
                input.value = String(parseMoneyValue(input.value));
            });
        });
    }

    /* ── Devise : mirroir minimal de src/helpers.php format_currency()/currency_symbol(),
       nécessaire côté client pour afficher la modale de détail sans aller-retour serveur. ── */
    function currencySymbol(cur, style) {
        if (cur === 'JPY') return style === 'international' ? '¥' : '円';
        switch (cur) {
            case 'KRW': return '₩';
            case 'EUR': return '€';
            case 'USD': return '$';
            case 'GBP': return '£';
            case 'CHF': return 'CHF';
            default: return cur;
        }
    }

    function formatCurrency(amount, cur, style) {
        amount = Number(amount) || 0;
        cur = (cur || '').toUpperCase();
        var symbol = currencySymbol(cur, style);
        switch (cur) {
            case 'JPY':
            case 'KRW':
                return Math.round(amount).toLocaleString('en-US') + symbol;
            case 'EUR':
                return amount.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + symbol;
            case 'USD':
            case 'GBP':
                return symbol + amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            case 'CHF':
                return symbol + ' ' + amount.toLocaleString('de-CH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            default:
                return amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + symbol;
        }
    }

    function escapeHtml(s) {
        var div = document.createElement('div');
        div.textContent = s == null ? '' : String(s);
        return div.innerHTML;
    }

    function pad2(n) { return n < 10 ? '0' + n : String(n); }

    /* ── Formatage partagé avec _payslip-helpers.php (payslip_hours/date/dow) ── */
    function hoursFmt(minutes) {
        minutes = Number(minutes) || 0;
        var h = Math.floor(minutes / 60);
        var m = minutes % 60;
        return h + 'h' + pad2(m);
    }

    function dateFmt(dateStr) {
        var parts = String(dateStr || '').split('-');
        return parts.length === 3 ? parts[2] + '/' + parts[1] + '/' + parts[0] : (dateStr || '');
    }

    var DOW_LABELS = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
    function dowFmt(dateStr) {
        var parts = String(dateStr || '').split('-').map(Number);
        if (parts.length !== 3) return '';
        return DOW_LABELS[new Date(parts[0], parts[1] - 1, parts[2]).getDay()];
    }

    /* ── Mode mois / plage personnalisée ─────────────────────────── */
    function monthBounds(monthValue) {
        if (!/^\d{4}-\d{2}$/.test(monthValue)) return null;
        var parts = monthValue.split('-');
        var year = parseInt(parts[0], 10);
        var month = parseInt(parts[1], 10);
        var lastDay = new Date(year, month, 0).getDate();
        return { from: monthValue + '-01', to: monthValue + '-' + pad2(lastDay) };
    }

    function setMode(newMode) {
        activeMode = newMode;
        modeButtons.forEach(function (btn) {
            btn.classList.toggle('btn--active', btn.dataset.periodMode === newMode);
        });
        if (thumb) thumb.style.setProperty('--pos', newMode === 'month' ? 0 : 1);
        if (monthPanel) monthPanel.hidden = newMode !== 'month';
        if (customPanel) customPanel.hidden = newMode !== 'custom';

        if (newMode === 'custom' && fromInput && toInput && !fromInput.value && !toInput.value) {
            var bounds = monthBounds(monthInput ? monthInput.value : '');
            if (bounds) {
                fromInput.value = bounds.from;
                toInput.value = bounds.to;
            }
        }
    }

    function getCurrentRange() {
        if (activeMode === 'month') {
            return monthInput ? monthBounds(monthInput.value) : null;
        }
        if (!fromInput || !toInput || !fromInput.value || !toInput.value) return null;
        if (fromInput.value > toInput.value) return null;
        return { from: fromInput.value, to: toInput.value };
    }

    /* ── Autofill des champs du formulaire ───────────────────────── */
    function applyPreset(preset) {
        AUTOFILL_FIELDS.forEach(function (field) {
            var input = form ? form.querySelector('[name="' + field + '"]') : null;
            if (!input) return;
            var hasValue = Object.prototype.hasOwnProperty.call(preset, field);
            input.value = hasValue ? preset[field] : (field === 'employee_work_hours' ? '' : 0);
        });
    }

    function setStatus(text, isError) {
        if (!statusEl) return;
        statusEl.textContent = text || '';
        statusEl.classList.toggle('sr-period__status--error', !!isError);
    }

    function fetchCalculate(range) {
        var url = calcUrl + '?from=' + range.from + '&to=' + range.to + '&user_id=' + userId;
        return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) {
                if (!r.ok) throw new Error('http_' + r.status);
                return r.json();
            });
    }

    function triggerRecalc() {
        var range = getCurrentRange();
        if (!range || !hiddenTargetMonth) return;
        hiddenTargetMonth.value = range.from.slice(0, 7);

        if (!confirmedOverwrite) {
            if (!window.confirm(i18n.confirm_recalculate || '')) return;
            confirmedOverwrite = true;
        }

        setStatus(i18n.recalculating, false);
        var thisRequestId = ++requestId;
        fetchCalculate(range)
            .then(function (data) {
                if (thisRequestId !== requestId) return;
                applyPreset(data.preset || {});
                formatAllMoneyInputs();
                setStatus('', false);
            })
            .catch(function () {
                if (thisRequestId !== requestId) return;
                setStatus(i18n.calc_error, true);
            });
    }

    function scheduleRecalc() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(triggerRecalc, DEBOUNCE_MS);
    }

    /* ── Modale "détail des calculs" (lecture seule, pas de confirmation) ── */
    function dedRateRow(label, rate) {
        rate = Number(rate) || 0;
        if (rate <= 0) return '';
        return '<tr><th>' + escapeHtml(label) + ' (' + rate.toFixed(2) + '%)</th></tr>';
    }

    /* Répartition des heures/du coût par type d'horaire (un shift peut être
       facturé sur plusieurs types à la fois, voir ShiftWageCalculator::costOf()). */
    function renderTypeBreakdown(typeBreakdown) {
        if (!typeBreakdown || !typeBreakdown.length) return '';
        var html = '<h4 class="detail-subtitle">' + escapeHtml(i18n.sr_type_breakdown) + '</h4>';
        html += '<table class="detail-table sr-calc-detail__table"><thead><tr><th>'
            + escapeHtml(i18n.shift_type) + '</th><th>' + escapeHtml(i18n.hours) + '</th><th>'
            + escapeHtml(i18n.amount) + '</th></tr></thead><tbody>';
        typeBreakdown.forEach(function (t) {
            html += '<tr><td>' + escapeHtml(t.name) + '</td><td class="td-mono">' + hoursFmt(t.minutes) + '</td><td class="td-mono">'
                + formatCurrency(t.amount, currency, currencyStyle) + '</td></tr>';
        });
        html += '</tbody></table>';
        return html;
    }

    function renderDetail(detail) {
        if (!detail) return '';

        if (detail.mode === 'store') {
            var html = '';
            if (!detail.employees || !detail.employees.length) {
                html += '<p class="form-hint">' + escapeHtml(i18n.payslip_no_shift) + '</p>';
            } else {
                html += '<table class="detail-table sr-calc-detail__table"><thead><tr><th>'
                    + escapeHtml(i18n.employee) + '</th><th>' + escapeHtml(i18n.hours) + '</th><th>'
                    + escapeHtml(i18n.amount) + '</th></tr></thead><tbody>';
                detail.employees.forEach(function (e) {
                    html += '<tr><td>' + escapeHtml(e.name) + '</td><td class="td-mono">' + e.hours + ' h</td><td class="td-mono">'
                        + formatCurrency(e.cost, currency, currencyStyle) + '</td></tr>';
                });
                html += '</tbody></table>';
            }

            html += renderTypeBreakdown(detail.type_breakdown);

            var ds = detail.deduction_settings || {};
            if (ds.enabled) {
                var rateRows = ''
                    + dedRateRow(i18n.ded_health_insurance, ds.health_insurance_rate)
                    + dedRateRow(i18n.ded_pension, ds.pension_rate)
                    + dedRateRow(i18n.ded_employment_insurance, ds.employment_insurance_rate)
                    + dedRateRow(i18n.ded_income_tax, ds.income_tax_rate);
                if (Number(ds.resident_tax_monthly) > 0) {
                    rateRows += '<tr><th>' + escapeHtml(i18n.ded_resident_tax) + ' (' + escapeHtml(i18n.monthly_fixed) + ')</th><td class="td-mono">'
                        + formatCurrency(ds.resident_tax_monthly, currency, currencyStyle) + '</td></tr>';
                }
                if (rateRows !== '') {
                    html += '<h4 class="detail-subtitle">' + escapeHtml(i18n.total_deductions) + '</h4>'
                        + '<table class="detail-table sr-calc-detail__table">' + rateRows + '</table>';
                }
            }
            return html;
        }

        // Mode employé : liste des shifts (gross/pause/net/taux/montant), puis
        // gross/retenues/net, comme buildPayslipData() / reports-salary-show.php.
        if (!detail.shiftRows || !detail.shiftRows.length) {
            return '<p class="form-hint">' + escapeHtml(i18n.payslip_no_shift) + '</p>';
        }
        var anyRate = !!detail.anyRate;
        var out = '<table class="detail-table sr-calc-detail__table"><thead><tr>';
        out += '<th>' + escapeHtml(i18n.col_day) + '</th>';
        out += '<th>' + escapeHtml(i18n.date) + '</th>';
        out += '<th>' + escapeHtml(i18n.shift_type) + '</th>';
        out += '<th>' + escapeHtml(i18n.schedule) + '</th>';
        out += '<th>' + escapeHtml(i18n.gross_h_col) + '</th>';
        out += '<th>' + escapeHtml(i18n.pause) + '</th>';
        out += '<th>' + escapeHtml(i18n.net_h_col) + '</th>';
        if (anyRate) {
            out += '<th>' + escapeHtml(i18n.col_rate_h) + '</th>';
            out += '<th>' + escapeHtml(i18n.amount) + '</th>';
        }
        out += '</tr></thead><tbody>';
        detail.shiftRows.forEach(function (row) {
            out += '<tr>';
            out += '<td>' + escapeHtml(dowFmt(row.date)) + '</td>';
            out += '<td>' + escapeHtml(dateFmt(row.date)) + '</td>';
            out += '<td>' + escapeHtml(row.type) + '</td>';
            out += '<td>' + escapeHtml(row.start) + '–' + escapeHtml(row.end) + '</td>';
            out += '<td class="td-mono">' + hoursFmt(row.gross_min) + '</td>';
            out += '<td class="td-mono">' + (row.pause_min > 0 ? row.pause_min + ' min' : '—') + '</td>';
            out += '<td class="td-mono">' + hoursFmt(row.net_min) + '</td>';
            if (anyRate) {
                out += '<td class="td-mono">' + (row.has_rate ? Number(row.rate).toFixed(2) : '—') + '</td>';
                out += '<td class="td-mono">' + (row.has_rate ? formatCurrency(row.cost, currency, currencyStyle) : '—') + '</td>';
            }
            out += '</tr>';
        });
        out += '</tbody><tfoot><tr>';
        out += '<th colspan="4">' + escapeHtml(i18n.total_row) + '</th>';
        out += '<th class="td-mono">' + hoursFmt(detail.totalGrossMin) + '</th>';
        out += '<th></th>';
        out += '<th class="td-mono">' + hoursFmt(detail.totalNetMin) + '</th>';
        if (anyRate) {
            out += '<th></th><th class="td-mono">' + formatCurrency(detail.totalCost, currency, currencyStyle) + '</th>';
        }
        out += '</tr></tfoot></table>';

        out += renderTypeBreakdown(detail.type_breakdown);

        out += '<table class="detail-table sr-calc-detail__table">';
        out += '<tr><th>' + escapeHtml(i18n.gross_pay) + '</th><td class="td-mono td-highlight">' + formatCurrency(detail.totalCost, currency, currencyStyle) + '</td></tr>';
        var deductions = detail.deductions || {};
        Object.keys(deductions).forEach(function (key) {
            var d = deductions[key];
            var suffix = d.is_flat ? ' (' + escapeHtml(i18n.monthly_fixed) + ')' : ' (' + Number(d.rate).toFixed(2) + '%)';
            out += '<tr><th>' + escapeHtml(i18n[d.label_key] || d.label_key) + suffix + '</th><td class="td-mono">−' + formatCurrency(d.amount, currency, currencyStyle) + '</td></tr>';
        });
        out += '<tr><th>' + escapeHtml(i18n.total_deductions) + '</th><td class="td-mono">−' + formatCurrency(detail.totalDeductions, currency, currencyStyle) + '</td></tr>';
        out += '<tr><th>' + escapeHtml(i18n.net_pay) + '</th><td class="td-mono td-highlight">' + formatCurrency(detail.netPay, currency, currencyStyle) + '</td></tr>';
        out += '</table>';
        return out;
    }

    function openDetailModal() {
        var range = getCurrentRange();
        if (!range || !detailBody) return;
        detailBody.innerHTML = '<p class="form-hint">' + escapeHtml(i18n.recalculating) + '</p>';
        if (window.openModal) window.openModal('sr-calc-detail-modal');

        fetchCalculate(range)
            .then(function (data) {
                detailBody.innerHTML = renderDetail(data.detail);
            })
            .catch(function () {
                detailBody.innerHTML = '<p class="form-hint">' + escapeHtml(i18n.calc_error) + '</p>';
            });
    }

    /* ── Câblage des événements ───────────────────────────────────── */
    modeButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            setMode(btn.dataset.periodMode);
            scheduleRecalc();
        });
    });
    if (monthInput) monthInput.addEventListener('change', scheduleRecalc);
    if (fromInput) fromInput.addEventListener('change', scheduleRecalc);
    if (toInput) toInput.addEventListener('change', scheduleRecalc);
    if (recalcBtn) recalcBtn.addEventListener('click', function () {
        clearTimeout(debounceTimer);
        triggerRecalc();
    });
    if (detailBtn) detailBtn.addEventListener('click', openDetailModal);

    setMode('month');
})();
