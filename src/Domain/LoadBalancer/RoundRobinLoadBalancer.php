<?php

declare(strict_types=1);

namespace App\Domain\LoadBalancer;

use App\Domain\Exception\NoHealthyServersException;

class RoundRobinLoadBalancer implements LoadBalancerInterface
{
    private array $servers;
    private int $currentIndex = 0;

    public function __construct(array $servers = [])
    {
        $this->servers = array_values($servers);
    }

    public function getNextServer(): string
    {
        if (empty($this->servers)) {
            throw new NoHealthyServersException('No healthy servers available');
        }

        $server = $this->servers[$this->currentIndex];
        $this->currentIndex = ($this->currentIndex + 1) % count($this->servers);

        return $server;
    }

    public function addServer(string $server): void
    {
        if (!in_array($server, $this->servers, true)) {
            $this->servers[] = $server;
        }
    }

    public function removeServer(string $server): void
    {
        $key = array_search($server, $this->servers, true);
        if ($key !== false) {
            array_splice($this->servers, $key, 1);
            
            // Reset index if it's beyond the new array bounds
            if ($this->currentIndex >= count($this->servers)) {
                $this->currentIndex = 0;
            }
        }
    }

    public function getServers(): array
    {
        return $this->servers;
    }
}