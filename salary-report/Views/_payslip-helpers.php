<?php

/**
 * Helpers de formatage partagés entre la fiche de paie écran
 * (reports-salary-show.php) et sa déclinaison PDF (reports-salary-pdf.php).
 * Les deux vues gardent des markups distincts (tables pour mPDF, divs pour
 * l'écran) mais partagent ce formatage.
 */

if (!function_exists('payslip_hours')) {
    function payslip_hours(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return $h . 'h' . str_pad((string) $m, 2, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('payslip_date')) {
    function payslip_date(string $date): string
    {
        $dt = \DateTime::createFromFormat('Y-m-d', $date);
        return $dt ? $dt->format('d/m/Y') : $date;
    }
}

if (!function_exists('payslip_dow')) {
    function payslip_dow(string $date): string
    {
        $days = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
        $dt = \DateTime::createFromFormat('Y-m-d', $date);
        return $dt ? $days[(int) $dt->format('w')] : '';
    }
}
