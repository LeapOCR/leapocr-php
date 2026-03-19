<?php

declare(strict_types=1);

namespace LeapOCR\Exceptions;

use RuntimeException;
use Throwable;

class LeapOCRException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        public readonly mixed $details = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode ?? 0, $previous);
    }
}

