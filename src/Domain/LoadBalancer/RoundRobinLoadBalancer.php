<?php

declare(strict_types=1);

namespace App\Domain\LoadBalancer;

use App\Domain\Exception\NoHealthyServersException;
use OpenSwoole\Atomic\Long as AtomicLong;
use OpenSwoole\Atomic;
use SplFixedArray;

final class RoundRobinLoadBalancer implements LoadBalancerInterface
{
    private const string ERROR_NO_HEALTHY_SERVERS = 'No healthy servers available';

    private SplFixedArray $serversArray;
    private AtomicLong $counter;
    private Atomic $version;

    /** @var array<int, string> */
    public array $servers {
        get {
            return $this->serversArray->toArray();
        }
    }

    /**
     * @param array<int, string> $servers
     */
    public function __construct(array $servers = [], ?AtomicLong $counter = null, ?Atomic $version = null)
    {
        $uniqueServers = array_values(array_unique($servers));
        $this->serversArray = SplFixedArray::fromArray($uniqueServers);
        $this->counter = $counter ?? new AtomicLong(0);
        $this->version = $version ?? new Atomic(0);
    }

    public function getNextServer(): string
    {
        $servers = $this->serversArray;
        $count = $servers->getSize();

        if ($count === 0) {
            throw new NoHealthyServersException(self::ERROR_NO_HEALTHY_SERVERS);
        }

        $index = $this->counter->add(1) - 1;
        $index = $index % $count;

        return $servers[$index];
    }

    public function addServer(string $server): void
    {
        do {
            $currentVersion = $this->version->get();
            $current = $this->serversArray;
            $currentArray = $current->toArray();
            
            if (in_array($server, $currentArray, true)) {
                return;
            }
            
            $newArray = $currentArray;
            $newArray[] = $server;
            $newServers = SplFixedArray::fromArray($newArray);
            
        } while (!$this->compareAndSwapServers($currentVersion, $newServers));
    }

    public function removeServer(string $server): void
    {
        do {
            $currentVersion = $this->version->get();
            $current = $this->serversArray;
            $currentArray = $current->toArray();
            
            $key = array_search($server, $currentArray, true);
            if ($key === false) {
                return;
            }
            
            unset($currentArray[$key]);
            $newArray = array_values($currentArray);
            $newServers = SplFixedArray::fromArray($newArray);
            
        } while (!$this->compareAndSwapServers($currentVersion, $newServers));
    }

    private function compareAndSwapServers(int $expectedVersion, SplFixedArray $newServers): bool
    {
        $newVersion = $expectedVersion + 1;
        
        if ($this->version->cmpset($expectedVersion, $newVersion)) {
            $this->serversArray = $newServers;
            return true;
        }
        
        return false;
    }

}
