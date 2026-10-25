<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\PlanningCheck\Views;

use kintai\Core\Container;
use kintai\Core\Router;
use kintai\UI\ViewRenderer;
use PHPUnit\Framework\TestCase;

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 4) . '/Kintai');
}

/** Les trois fenêtres et le PDF se rendent, avec les bons formulaires et sans échapper les données. */
final class OverlayViewsTest extends TestCase
{
    private ViewRenderer $view;

    protected function setUp(): void
    {
        $this->view = new ViewRenderer(dirname(__DIR__, 2) . '/Views');
        $this->view->addNamespace('planning-check', dirname(__DIR__, 2) . '/Views');
        $router = new Router();
        foreach ([
            'admin.planning_check.submit' => '/s/{id}/submit', 'admin.planning_check.edit' => '/s/{id}/edit', 'admin.planning_check.resume' => '/s/{id}/resume',
            'admin.planning_check.approve' => '/s/{id}/{cid}/approve', 'admin.planning_check.reject' => '/s/{id}/{cid}/reject',
        ] as $name => $path) {
            $router->post($path, [\stdClass::class, 'x'], name: $name);
        }
        Container::getInstance()->instance(Router::class, $router);
    }

    /** @return array<string, mixed> */
    private function data(?array $rows = null): array
    {
        $rows ??= [['employee' => 'Dupont <b>Léa</b>', 'type' => 'Journée', 'color' => null, 'start' => '09:00', 'end' => '17:00', 'net_min' => 420, 'open' => false]];
        return [
            'store' => ['id' => 3, 'name' => 'Shibuya'], 'check' => ['id' => 11, 'status' => 'pending', 'target_date' => '2026-10-22', 'reject_reason' => null],
            'date' => '2026-10-22', 'late' => false, 'count' => 2, 'overlay_title_id' => 'page-overlay-x-title', 'locale' => 'fr', 'context' => [],
            'day' => ['rows' => $rows, 'total_minutes' => array_sum(array_column($rows, 'net_min')), 'assigned' => count($rows), 'open' => 0],
        ];
    }

    public function testSubmitOverlayOffersYesNoAndLater(): void
    {
        $html = $this->view->renderPartial('planning-check::overlay-submit', $this->data());

        $this->assertStringContainsString('id="page-overlay-x-title"', $html);
        $this->assertStringContainsString('/s/3/submit"', $html);
        $this->assertStringContainsString('/s/3/edit"', $html);
        $this->assertStringContainsString('name="date" value="2026-10-22"', $html);
        $this->assertStringContainsString('data-overlay-close', $html);
        $this->assertStringContainsString('Dupont &lt;b&gt;Léa&lt;/b&gt;', $html, 'les noms sont échappés');
        $this->assertStringContainsString('7h00', $html);
        $this->assertStringNotContainsString('confirm_empty', $html);
    }

    public function testWithoutShiftTheOverlayAsksForAnExplicitConfirmation(): void
    {
        $html = $this->view->renderPartial('planning-check::overlay-submit', $this->data([]) + []);

        $this->assertStringContainsString('name="confirm_empty"', $html);
        $this->assertStringContainsString('planning_check_no_shifts', $html);
    }

    public function testApproveOverlayHasApproveAndRejectWithAReason(): void
    {
        $html = $this->view->renderPartial('planning-check::overlay-approve', $this->data());

        $this->assertStringContainsString('/s/3/11/approve"', $html);
        $this->assertStringContainsString('/s/3/11/reject"', $html);
        $this->assertStringContainsString('name="reason"', $html);
        $this->assertStringContainsString('planning_check_more_to_validate', $html);
    }

    public function testEditingBarOffersToGoBackToTheValidation(): void
    {
        $html = $this->view->renderPartial('planning-check::overlay-editing', $this->data());

        $this->assertStringContainsString('/s/3/resume"', $html);
        $this->assertStringContainsString('planning_check_back_to_validation', $html);
    }

    public function testThePdfListsEachShiftAndMarksAnUpdatedVersion(): void
    {
        $data = $this->data();
        $html = $this->view->renderPartial('planning-check::pdf', array_replace($data, ['context' => ['validated_by' => 'Boss', 'updated' => true, 'generated_at' => '10:05']]));

        $this->assertStringContainsString('<html lang="fr">', $html);
        $this->assertStringContainsString('Dupont &lt;b&gt;Léa&lt;/b&gt;', $html);
        $this->assertStringContainsString('planning_check_pdf_updated', $html);
        $this->assertStringContainsString('planning_check_pdf_validated', $html);

        $empty = $this->view->renderPartial('planning-check::pdf', $this->data([]));
        $this->assertStringContainsString('planning_check_pdf_no_shift', $empty);
    }
}
