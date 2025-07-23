<?php

declare(strict_types=1);

namespace App\Infrastructure\Logger;

use App\Infrastructure\Clock\ClockInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;

class ConsoleLogger extends AbstractLogger
{
    public function __construct(
        private readonly ClockInterface $clock,
        private readonly bool $enabled = true,
        private readonly string $minLevel = LogLevel::INFO
    ) {
    }

    public function log($level, string|Stringable $message, array $context = []): void
    {
        if (!$this->enabled || !$this->shouldLog($level)) {
            return;
        }

        $timestamp = $this->clock->now()->format('Y-m-d H:i:s');
        $levelUpper = strtoupper($level);
        $formattedMessage = $this->interpolate($message, $context);
        
        if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
            $exception = $context['exception'];
            $formattedMessage .= sprintf(
                ' [%s: %s in %s:%d]',
                get_class($exception),
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
            );
            
            if ($level === LogLevel::DEBUG) {
                $formattedMessage .= "\nStack trace:\n" . $exception->getTraceAsString();
            }
        }

        echo sprintf("[%s] %s: %s\n", $timestamp, $levelUpper, $formattedMessage);
    }

    private function shouldLog(string $level): bool
    {
        $levels = [
            LogLevel::EMERGENCY => 0,
            LogLevel::ALERT => 1,
            LogLevel::CRITICAL => 2,
            LogLevel::ERROR => 3,
            LogLevel::WARNING => 4,
            LogLevel::NOTICE => 5,
            LogLevel::INFO => 6,
            LogLevel::DEBUG => 7,
        ];

        return ($levels[$level] ?? 7) <= ($levels[$this->minLevel] ?? 6);
    }

    private function interpolate(string|Stringable $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            if ($key === 'exception') {
                continue;
            }
            
            if (is_null($val) || is_scalar($val) || (is_object($val) && method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            } elseif (is_array($val) || is_object($val)) {
                $replace['{' . $key . '}'] = json_encode($val);
            }
        }

        return strtr((string)$message, $replace);
    }
}