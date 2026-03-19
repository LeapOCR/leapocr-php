<?php

declare(strict_types=1);

namespace LeapOCR\Models;

use DateTimeImmutable;
use LeapOCR\Enums\JobStatusType;

readonly class ProcessResult
{
    public function __construct(
        public string $jobId,
        public JobStatusType $status,
        public ?DateTimeImmutable $createdAt = null,
        public ?string $sourceUrl = null,
    ) {
    }
}

