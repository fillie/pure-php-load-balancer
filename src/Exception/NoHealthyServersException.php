<?php

declare(strict_types=1);

namespace App\Exception;

class NoHealthyServersException extends LoadBalancerException
{
    public function __construct(string $message = 'No healthy servers available', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}