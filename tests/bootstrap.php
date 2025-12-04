<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Ensure the test database is clean before the suite runs.
// We purge once here (truncate) and rely on DamaDoctrineTestBundle
// to provide transactional rollback for each individual test.
if (php_sapi_name() !== 'cli' || !isset($_SERVER['APP_ENV']) || $_SERVER['APP_ENV'] !== 'test') {
    return;
}

use App\Kernel;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) ($_SERVER['APP_DEBUG'] ?? false));
$kernel->boot();
try {
    $container = $kernel->getContainer();
    if ($container->has('doctrine')) {
        $em = $container->get('doctrine')->getManager();
        $purger = new ORMPurger($em);
        // Use DELETE purge mode to avoid MySQL errors when FK constraints are present.
        $purger->setPurgeMode(ORMPurger::PURGE_MODE_DELETE);
        $purger->purge();
    }
} finally {
    $kernel->shutdown();
}
