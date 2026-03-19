<?php

declare(strict_types=1);

namespace LeapOCR\Models;

use DateTimeImmutable;
use LeapOCR\Enums\JobStatusType;

readonly class JobResult
{
    /**
     * @param array<int, PageResult> $pages
     */
    public function __construct(
        public string $jobId,
        public JobStatusType $status,
        public array $pages,
        public ?string $fileName,
        public int $totalPages,
        public int $processedPages,
        public int $creditsUsed,
        public ?string $model,
        public ?string $resultFormat,
        public ?DateTimeImmutable $completedAt = null,
        public ?PaginationInfo $pagination = null,
    ) {
    }
}

