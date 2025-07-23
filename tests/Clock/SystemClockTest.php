<?php

declare(strict_types=1);

namespace App\Tests\Clock;

use App\Clock\SystemClock;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class SystemClockTest extends TestCase
{
    private SystemClock $clock;

    protected function setUp(): void
    {
        $this->clock = new SystemClock();
    }

    public function testNowReturnsDateTimeImmutable(): void
    {
        $result = $this->clock->now();
        
        $this->assertInstanceOf(DateTimeImmutable::class, $result);
    }

    public function testNowReturnsCurrentTime(): void
    {
        $before = new DateTimeImmutable();
        $result = $this->clock->now();
        $after = new DateTimeImmutable();

        // Should be between before and after (allowing for microsecond differences)
        $this->assertGreaterThanOrEqual($before->getTimestamp(), $result->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $result->getTimestamp());
    }

    public function testMultipleCallsReturnDifferentInstances(): void
    {
        $time1 = $this->clock->now();
        usleep(1000); // Sleep 1ms to ensure different timestamp
        $time2 = $this->clock->now();

        $this->assertNotSame($time1, $time2);
        $this->assertGreaterThanOrEqual($time1->getTimestamp(), $time2->getTimestamp());
    }
}