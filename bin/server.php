<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/bootstrap.php';

use App\Application\Http\Server\ServerInterface;

$container = createContainer();
$server = $container->get(ServerInterface::class);
$server->start();