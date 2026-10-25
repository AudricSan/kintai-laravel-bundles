<?php

declare(strict_types=1);

namespace kintai\Tests\Bundles\ShiftSwap\Security;

use PHPUnit\Framework\TestCase;

/**
 * Un formulaire GET envoie ses champs dans l'URL : un csrf_field() y place le jeton de session, qui finit dans
 * l'historique du navigateur, les journaux d'accès du serveur et les liens copiés-collés (audit n°8 de Kintai, I4).
 * Un formulaire GET ne modifie rien, il n'a pas besoin du jeton.
 */
final class NoCsrfTokenInGetFormsTest extends TestCase
{
    public function testNoGetFormCarriesTheCsrfToken(): void
    {
        $base = dirname(__DIR__, 2);
        $offenders = [];
        foreach (['Views', 'src'] as $dir) {
            if (!is_dir($base . '/' . $dir)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base . '/' . $dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $source = (string) file_get_contents($file->getPathname());
                if (!preg_match_all('/<form\b[^>]*method=["\']get["\'][^>]*>(.*?)<\/form>/is', $source, $forms, PREG_OFFSET_CAPTURE)) {
                    continue;
                }
                foreach ($forms[1] as [$body, $offset]) {
                    if (preg_match('/csrf_field\(|name=["\']_token["\']/', $body)) {
                        $offenders[] = $dir . '/' . $file->getFilename() . ':' . (substr_count(substr($source, 0, $offset), "\n") + 1);
                    }
                }
            }
        }

        $this->assertSame([], $offenders, 'jeton CSRF dans un formulaire GET : ' . implode(', ', $offenders));
    }
}
