<?php

declare(strict_types=1);

namespace App\Domain\LoadBalancer;

use App\Domain\Exception\NoHealthyServersException;

interface LoadBalancerInterface
{
    /**
     * @throws NoHealthyServersException
     */
    public function getNextServer(): string;

    public function addServer(string $server): void;

    public function removeServer(string $server): void;
}