'use strict';

/**
 * Formatage visuel des champs numériques entiers.
 * Utilise Intl.NumberFormat avec la locale de la page (<html lang>).
 *
 * Marquer les champs avec data-numeric-input.
 * Les séparateurs sont retirés avant la soumission du formulaire.
 */
document.addEventListener('DOMContentLoaded', () => {
    const locale = document.documentElement.lang || 'en';
    const fmt    = new Intl.NumberFormat(locale, { maximumFractionDigits: 0 });

    function digitsOnly(str) {
        return str.replace(/\D/g, '');
    }

    function formatNum(str) {
        const digits = digitsOnly(str);
        return digits ? fmt.format(parseInt(digits, 10)) : '';
    }

    // Nombre de chiffres avant la position `pos` dans `str`
    function digitsBeforePos(str, pos) {
        return (str.slice(0, pos).match(/\d/g) || []).length;
    }

    // Position dans `str` juste après le n-ième chiffre
    function posAfterNthDigit(str, n) {
        if (n <= 0) return 0;
        let seen = 0;
        for (let i = 0; i < str.length; i++) {
            if (/\d/.test(str[i]) && ++seen === n) return i + 1;
        }
        return str.length;
    }

    document.querySelectorAll('[data-numeric-input]').forEach(input => {
        // Formatage de la valeur initiale (0 affiché vide pour ne pas gêner la saisie)
        const init = input.value;
        input.value = (init && init !== '0') ? formatNum(init) : '';
        if (!input.placeholder) input.placeholder = '0';

        input.addEventListener('input', function () {
            const raw = this.value;
            const pos = this.selectionStart ?? raw.length;
            const digitsBefore = digitsBeforePos(raw, pos);

            const formatted = formatNum(raw);
            this.value = formatted;

            const newPos = posAfterNthDigit(formatted, digitsBefore);
            this.setSelectionRange(newPos, newPos);
        });
    });

    // Avant soumission : remplacer la valeur formatée par le nombre brut
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', () => {
            form.querySelectorAll('[data-numeric-input]').forEach(input => {
                const raw = digitsOnly(input.value);
                input.value = raw || '0';
            });
        });
    });
});
