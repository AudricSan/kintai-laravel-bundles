<?php

declare(strict_types=1);

// Les tests des bundles tournent contre le vrai Kintai (Laravel), cloné à côté de ce dépôt (../kintai-laravel, installé
// avec composer install) ou désigné par KINTAI_CORE : son autoloader apporte les classes du Core, ses helpers et
// Laravel, dans le même ordre que l'application. Celui de ce dépôt (PHPUnit et les classes des bundles) repasse
// ensuite en tête, pour que PHPUnit ne vienne que d'ici.
$coreDir = getenv('KINTAI_CORE') ?: dirname(__DIR__, 2) . '/kintai-laravel';
$coreDir = rtrim($coreDir, '/\\');
$core    = $coreDir . '/vendor/autoload.php';
if (!is_file($core)) {
    fwrite(STDERR, "Kintai introuvable : clonez kintai-laravel dans ../kintai-laravel (ou indiquez KINTAI_CORE) et lancez-y composer install.\n");
    exit(1);
}
require $core;

// Racine du Core, comme dans l'application : des vues des bundles en lisent des fichiers (CSS des PDF…).
define('BASE_PATH', realpath($coreDir));

$bundles = require dirname(__DIR__) . '/vendor/autoload.php';
$bundles->unregister();
$bundles->register(true);
