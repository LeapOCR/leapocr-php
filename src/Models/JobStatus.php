<?php

declare(strict_types=1);

namespace LeapOCR\Models;

use DateTimeImmutable;
use LeapOCR\Enums\JobStatusType;

readonly class JobStatus
{
    public function __construct(
        public string $jobId,
        public JobStatusType $status,
        public int $processedPages,
        public int $totalPages,
        public float $progress,
        public ?DateTimeImmutable $createdAt = null,
        public ?string $resultFormat = null,
        public ?string $errorMessage = null,
    ) {
    }
}
