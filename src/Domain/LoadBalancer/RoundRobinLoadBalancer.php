<?php

declare(strict_types=1);

namespace App\Domain\LoadBalancer;

use App\Domain\Exception\NoHealthyServersException;
use OpenSwoole\Atomic\Long as AtomicLong;
use OpenSwoole\Lock;

final class RoundRobinLoadBalancer implements LoadBalancerInterface
{
    private const string ERROR_NO_HEALTHY_SERVERS = 'No healthy servers available';

    /** @var array<int, string> */
    public array $servers {
        get {
            return $this->servers;
        }
    }

    private AtomicLong $counter;
    private Lock $lock;

    /**
     * @param array<int, string> $servers
     */
    public function __construct(array $servers = [], ?AtomicLong $counter = null, ?Lock $lock = null)
    {
        $this->servers = array_values(array_unique($servers));
        $this->counter = $counter ?? new AtomicLong(0);
        $this->lock = $lock ?? new Lock();
    }

    public function getNextServer(): string
    {
        $servers = $this->servers;
        $count = count($servers);

        if ($count === 0) {
            throw new NoHealthyServersException(self::ERROR_NO_HEALTHY_SERVERS);
        }

        $index = $this->counter->add(1) - 1;
        $index = $index % $count;

        return $servers[$index];
    }

    public function addServer(string $server): void
    {
        $this->lock->lock();
        try {
            if (!in_array($server, $this->servers, true)) {
                $current = $this->servers;
                $current[] = $server;
                $this->servers = $current;
            }
        } finally {
            $this->lock->unlock();
        }
    }

    public function removeServer(string $server): void
    {
        $this->lock->lock();
        try {
            $current = $this->servers;
            $key = array_search($server, $current, true);
            if ($key === false) {
                return;
            }

            unset($current[$key]);
            $this->servers = array_values($current);
        } finally {
            $this->lock->unlock();
        }
    }

}
