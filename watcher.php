<?php

declare(strict_types=1);

return [
    'watch' => [__DIR__ . '/src'],
    'ext' => ['php'],
    'command' => 'php src/Server.php',
    'signal' => SIGUSR1,
    'debounce' => 300
];
