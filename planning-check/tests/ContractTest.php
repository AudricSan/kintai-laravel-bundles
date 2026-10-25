<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\PlanningCheck;

use kintai\Bundles\Installed\PlanningCheck\PlanningCheckBundle;
use kintai\Core\BundleContract\BundleManifest;
use kintai\Tests\Support\ContractUsageScanner;
use kintai\Tests\Support\TranslationContractChecker;
use PHPUnit\Framework\TestCase;

/**
 * Les trois contrôles que la CI impose à tout bundle (officiel ou tiers), dès la V1 :
 *  1. bundle.json respecte le schéma ;
 *  2. le code n'utilise du Core que le contrat (classes kintai\Contract\… et fonctions déclarées) ;
 *  3. les traductions respectent le contrat (préfixe, parité fr/en/ja, collisions, clés connues).
 */
final class ContractTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__);
    }

    public function testManifestFollowsTheSchema(): void
    {
        $data = json_decode((string) file_get_contents($this->root . '/bundle.json'), true);

        $this->assertSame([], BundleManifest::validationErrors($data, 'planning-check'));
    }

    public function testOnlyTheContractClassesAreUsed(): void
    {
        $this->assertSame([], ContractUsageScanner::violations($this->paths()));
    }

    public function testOnlyTheContractFunctionsAreCalled(): void
    {
        $this->assertSame([], ContractUsageScanner::functionViolations($this->paths()));
    }

    public function testTranslationsFollowTheContract(): void
    {
        $report = TranslationContractChecker::violations($this->root, 'planning-check', dirname($this->root, 2) . '/Kintai/lang');

        $this->assertSame([], array_filter($report), json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    public function testTheBundleDeclaresItsNavigationAndPermissions(): void
    {
        $bundle = (new \ReflectionClass(PlanningCheckBundle::class))->newInstanceWithoutConstructor();

        $this->assertSame('planning-check', $bundle->getName());
        $this->assertSame(['planning_check' => ['view', 'submit', 'approve', 'settings']], $bundle->permissions());
        $this->assertCount(1, $bundle->navigation());
    }

    /** @return list<string> */
    private function paths(): array
    {
        return [$this->root . '/src', $this->root . '/Views', $this->root . '/routes.php', $this->root . '/database'];
    }
}
