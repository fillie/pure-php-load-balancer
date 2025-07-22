<?php

declare(strict_types=1);

namespace App\LoadBalancer;

use App\Exception\NoHealthyServersException;

interface LoadBalancerInterface
{
    /**
     * Get the next available server using the load balancing algorithm.
     * 
     * @return string The URL/address of the next server
     * @throws NoHealthyServersException When no servers are available
     */
    public function getNextServer(): string;
    
    public function addServer(string $server): void;
    
    public function removeServer(string $server): void;

    public array $servers {
        get;
    }
}