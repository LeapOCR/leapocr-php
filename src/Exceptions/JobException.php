<?php

declare(strict_types=1);

namespace LeapOCR\Exceptions;

class JobException extends LeapOCRException
{
    public function __construct(
        string $message,
        public readonly ?string $jobId = null,
        ?int $statusCode = null,
        mixed $details = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $details, $previous);
    }
}

