<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\Shared;

use kintai\UI\ViewRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Les vues des bundles sont des gabarits Blade, pas du PHP valide tel quel (la CI ne les passe pas à php -l) : on
 * vérifie la syntaxe de ce que Blade en compile, et que chaque vue insérée par @includeInScope existe, dans le bundle
 * (« slug::vue ») ou dans le Core (« _partials.x »), comme les résout Kintai.
 */
final class BladeViewsTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function bundles(): iterable
    {
        foreach (glob(dirname(__DIR__, 2) . '/*/bundle.json') ?: [] as $manifest) {
            yield basename(dirname($manifest)) => [dirname($manifest)];
        }
    }

    #[DataProvider('bundles')]
    public function testEveryViewCompilesToValidPhpAndItsInclusionsExist(string $bundle): void
    {
        $slug = basename($bundle);
        $views = $bundle . '/Views';
        if (!is_dir($views)) {
            $this->markTestSkipped("{$slug} n'a pas de vues.");
        }

        $renderer = new ViewRenderer(BASE_PATH . '/app/UI/View');
        $renderer->addNamespace($slug, $views);
        $factory = $renderer->factory();

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($views, \FilesystemIterator::SKIP_DOTS));
        $count = 0;
        foreach ($files as $file) {
            $relative = substr($file->getPathname(), strlen($views) + 1);
            $this->assertStringEndsNotWith('.php', preg_replace('/\.blade\.php$/', '', $relative), "{$slug}/Views/{$relative} : vue PHP, à écrire en Blade (.blade.php).");
            if (!str_ends_with($relative, '.blade.php')) {
                continue;
            }
            $name = $slug . '::' . str_replace('/', '.', substr($relative, 0, -strlen('.blade.php')));
            try {
                token_get_all((string) file_get_contents(ViewRenderer::pathInScope($name, $factory)), TOKEN_PARSE);
            } catch (\ParseError $e) {
                $this->fail("{$slug}/Views/{$relative} : {$e->getMessage()} (ligne {$e->getLine()} du PHP compilé)");
            }

            preg_match_all("/@includeInScope\\('([^']+)'\\)/", (string) file_get_contents($file->getPathname()), $m);
            foreach ($m[1] as $included) {
                $this->assertTrue($factory->exists($included), "{$slug}/Views/{$relative} insère « {$included} », introuvable.");
                $this->assertTrue(str_starts_with($included, $slug . '::') || str_starts_with($included, '_partials.'), "{$slug}/Views/{$relative} : « {$included} » doit être une vue du bundle ({$slug}::…) ou un partiel du Core (_partials.…).");
            }
            $count++;
        }
        $this->assertGreaterThan(0, $count);
    }
}
