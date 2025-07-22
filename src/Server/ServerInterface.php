<?php

declare(strict_types=1);

namespace App\Server;

interface ServerInterface
{
    public function start(): void;
    
    public function stop(): void;
}