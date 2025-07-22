<?php

declare(strict_types=1);

namespace App\LoadBalancer;

interface LoadBalancerInterface
{
    public function getNextServer(): string;
    
    public function addServer(string $server): void;
    
    public function removeServer(string $server): void;

    public array $servers {
        get;
    }
}