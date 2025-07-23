<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use Exception;

class NoHealthyServersException extends Exception
{
    public function __construct(string $message = 'No healthy servers available')
    {
        parent::__construct($message);
    }
}