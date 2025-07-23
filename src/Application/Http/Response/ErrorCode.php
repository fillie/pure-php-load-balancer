<?php

declare(strict_types=1);

namespace App\Application\Http\Response;

enum ErrorCode: string
{
    case SERVICE_UNAVAILABLE = 'SERVICE_UNAVAILABLE';
    case INTERNAL_ERROR = 'INTERNAL_ERROR';
}