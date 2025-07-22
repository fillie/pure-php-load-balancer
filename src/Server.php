<?php

declare(strict_types=1);

use App\Server\ServerInterface;

require_once __DIR__ . '/bootstrap.php';

$container = createContainer();
$server = $container->get(ServerInterface::class);

$server->start();
