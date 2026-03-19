<?php

declare(strict_types=1);

namespace LeapOCR\Models;

readonly class PageResult
{
    /**
     * @param array<int, array<string, mixed>> $boundingBoxes
     * @param null|array<string, mixed> $dimensions
     */
    public function __construct(
        public int $pageNumber,
        public mixed $result,
        public ?string $id = null,
        public ?float $confidence = null,
        public bool $hasBoundingBoxes = false,
        public array $boundingBoxes = [],
        public ?array $dimensions = null,
    ) {
    }
}

