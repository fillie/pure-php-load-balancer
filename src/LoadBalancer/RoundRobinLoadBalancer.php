<?php

declare(strict_types=1);

namespace App\LoadBalancer;

class RoundRobinLoadBalancer implements LoadBalancerInterface
{
    private array $_servers = [];
    public array $servers {
        get {
            return $this->_servers;
        }
        set {
            $this->_servers = $value;
        }
    }
    private int $currentIndex = 0;

    public function __construct(array $servers = [])
    {
        $this->servers = $servers;
    }

    public function getNextServer(): string
    {
        if (empty($this->_servers)) {
            throw new \RuntimeException('No servers available');
        }

        $server = $this->_servers[$this->currentIndex];
        $this->currentIndex = ($this->currentIndex + 1) % count($this->_servers);

        return $server;
    }

    public function addServer(string $server): void
    {
        if (!in_array($server, $this->_servers, true)) {
            $this->_servers[] = $server;
        }
    }

    public function removeServer(string $server): void
    {
        $key = array_search($server, $this->_servers, true);
        if ($key !== false) {
            unset($this->_servers[$key]);
            $this->_servers = array_values($this->_servers);
            
            if ($this->currentIndex >= count($this->_servers)) {
                $this->currentIndex = 0;
            }
        }
    }

}