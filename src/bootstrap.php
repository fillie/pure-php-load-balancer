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

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Dotenv\Dotenv;

// Load environment variables
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

function createContainer(): ContainerBuilder
{
    $container = new ContainerBuilder();
    $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../config'));
    $loader->load('services.yaml');
    
    $container->compile();
    
    return $container;
}