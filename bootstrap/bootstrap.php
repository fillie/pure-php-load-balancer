<?php

declare(strict_types=1);

$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    '/app/vendor/autoload.php',
];

$autoloadFound = false;
foreach ($autoloadPaths as $autoloadPath) {
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
        $autoloadFound = true;
        break;
    }
}

if (!$autoloadFound) {
    throw new RuntimeException('Could not find vendor/autoload.php. Run composer install.');
}

use DI\ContainerBuilder;
use Dotenv\Dotenv;
use Psr\Container\ContainerInterface;

// Load environment variables
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

function createContainer(): ContainerInterface
{
    $builder = new ContainerBuilder();
    
    // Load container definitions
    $definitions = require __DIR__ . '/../config/container.php';
    $builder->addDefinitions($definitions());
    
    return $builder->build();
}