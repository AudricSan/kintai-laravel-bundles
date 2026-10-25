<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\Notebook;

use kintai\Tests\Support\TranslationContractChecker;
use PHPUnit\Framework\TestCase;

/**
 * Les traductions du bundle respectent le contrat (voir « Creating a Bundle » du wiki) : mêmes clés et mêmes
 * paramètres en fr/en/ja, clés préfixées par le slug, aucune clé du Core redéfinie sans être déclarée, aucune clé
 * utilisée que ni le bundle ni le contrat ne fournissent.
 */
final class TranslationContractTest extends TestCase
{
    public function testTranslationsFollowTheContract(): void
    {
        $root = dirname(__DIR__);

        $report = TranslationContractChecker::violations($root, 'notebook', dirname($root, 2) . '/Kintai/lang');

        $this->assertSame([], array_filter($report), json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}
