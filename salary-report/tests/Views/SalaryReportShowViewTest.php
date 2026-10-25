<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\SalaryReport\Views;

use kintai\UI\ViewRenderer;
use PHPUnit\Framework\TestCase;

if (!defined('BASE_PATH')) {
    // reports-salary-show.php inclut _payslip-helpers.php via BASE_PATH — en
    // production BASE_PATH pointe toujours vers la racine réelle de Kintai
    // (jamais vers ce bundle, où qu'il soit installé). Ici, on la fait
    // pointer vers le vrai dépôt Kintai Core, sibling de ce dépôt bundle.
    define('BASE_PATH', dirname(__DIR__, 4) . '/Kintai');
}

final class SalaryReportShowViewTest extends TestCase
{
    private function renderShow(array $store, array $extra = []): string
    {
        $viewsDir = dirname(__DIR__, 2) . '/Views';
        $view = new ViewRenderer(dirname($viewsDir));
        $view->addNamespace('salary-report', $viewsDir);

        return $view->renderPartial('salary-report::reports-salary-show', array_merge([
            'store'  => $store,
            'report' => ['id' => 30, 'store_id' => 1, 'user_id' => 7, 'target_month' => '2026-08'],
            'BASE_URL' => '',
            'shiftRows' => [
                ['date' => '2026-08-05', 'type' => 'Matin', 'start' => '09:00', 'end' => '17:00', 'gross_min' => 480, 'pause_min' => 60, 'net_min' => 420, 'rate' => 1200.0, 'cost' => 8400.0, 'has_rate' => true],
            ],
            'totalGrossMin'     => 480,
            'totalNetMin'       => 420,
            'totalCost'         => 8400.0,
            'anyRate'           => true,
            'deductions'        => [],
            'totalDeductions'   => 0.0,
            'netPay'            => 8400.0,
            'deductionsEnabled' => false,
        ], $extra));
    }

    public function testHourlyRateColumnShowsKanjiSymbolNotIsoCode(): void
    {
        $html = $this->renderShow(['id' => 1, 'name' => 'Store A', 'currency' => 'JPY', 'currency_symbol_style' => 'kanji']);

        $this->assertStringContainsString('1,200.00 円', $html);
        $this->assertStringNotContainsString('1,200.00 JPY', $html);
    }

    public function testHourlyRateColumnRespectsInternationalYenStyle(): void
    {
        $html = $this->renderShow(['id' => 1, 'name' => 'Store A', 'currency' => 'JPY', 'currency_symbol_style' => 'international']);

        $this->assertStringContainsString('1,200.00 ¥', $html);
        $this->assertStringNotContainsString('1,200.00 円', $html);
    }

    public function testTotalPaymentRowHiddenForEmployeeScopedReport(): void
    {
        // Fixture par défaut : report['user_id'] = 7 (rapport par employé) — le total
        // des ventes du magasin n'a pas de sens pour le salaire d'un seul employé.
        $html = $this->renderShow(['id' => 1, 'name' => 'Store A', 'currency' => 'JPY']);

        $this->assertStringNotContainsString('sr_total_payment', $html);
    }

    public function testTotalPaymentRowShownForStoreWideReport(): void
    {
        $html = $this->renderShow(['id' => 1, 'name' => 'Store A', 'currency' => 'JPY'], [
            'report' => ['id' => 30, 'store_id' => 1, 'target_month' => '2026-08', 'total_payment' => 12345],
            'shiftRows' => null,
        ]);

        $this->assertStringContainsString('sr_total_payment', $html);
    }
}
