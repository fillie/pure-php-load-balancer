<?php

declare(strict_types=1);

namespace App\Infrastructure\Clock;

use DateTimeImmutable;

interface ClockInterface
{
    public function now(): DateTimeImmutable;
}