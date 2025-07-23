<?php

declare(strict_types=1);

namespace App\Clock;

use DateTimeImmutable;

readonly class SystemClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}