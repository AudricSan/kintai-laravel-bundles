<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\SalaryReport\Views;

use kintai\UI\ViewRenderer;
use PHPUnit\Framework\TestCase;

if (!defined('BASE_PATH')) {
    // Voir SalaryReportShowViewTest — BASE_PATH doit pointer vers le vrai
    // dépôt Kintai Core (sibling), pas vers ce bundle, pour rester cohérent
    // avec la production (des vues de ce bundle en dépendent).
    define('BASE_PATH', dirname(__DIR__, 4) . '/Kintai');
}

final class SalaryReportFormViewTest extends TestCase
{
    private function renderForm(array $store, array $report = []): string
    {
        $viewsDir = dirname(__DIR__, 2) . '/Views';
        $view = new ViewRenderer(dirname($viewsDir));
        $view->addNamespace('salary-report', $viewsDir);

        return $view->renderPartial('salary-report::reports-salary-form', [
            'store'    => $store,
            'mode'     => 'edit',
            'report'   => $report,
            'BASE_URL' => '',
            'managers' => [],
        ]);
    }

    public function testMoneyFieldsRenderAsFormattableTextInputs(): void
    {
        // Régression : un <input type="number"> ne peut pas afficher de séparateur de
        // milliers, ce qui rendait les gros montants (ex. 158269.64) illisibles. Ces champs
        // sont donc des <input type="text"> (classe js-money-input) formatés en direct par
        // salary-report-form.js, avec la valeur brute côté serveur pour rester soumise
        // correctement comme un nombre.
        $html = $this->renderForm(
            ['id' => 1, 'name' => 'Store A', 'currency' => 'JPY', 'currency_symbol_style' => 'kanji'],
            ['id' => 30, 'store_id' => 1, 'net_payment' => 158269.64, 'other_deductions' => 27057.8]
        );

        $this->assertStringContainsString('type="text" inputmode="decimal" name="net_payment" class="form-control td-mono js-money-input"', $html);
        $this->assertStringContainsString('value="158269.64"', $html);
        $this->assertStringContainsString('value="27057.8"', $html);
        $this->assertStringNotContainsString('type="number" name="net_payment"', $html);
    }

    public function testHourlyWageFieldIsAlsoFormattable(): void
    {
        $html = $this->renderForm(
            ['id' => 1, 'name' => 'Store A', 'currency' => 'JPY', 'currency_symbol_style' => 'kanji'],
            ['id' => 30, 'store_id' => 1, 'staff_avg_hourly_wage' => 1234.0]
        );

        $this->assertStringContainsString('type="text" inputmode="decimal" name="staff_avg_hourly_wage" class="form-control td-mono js-money-input"', $html);
        $this->assertStringContainsString('value="1234"', $html);
    }
}
