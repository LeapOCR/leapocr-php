<?php

declare(strict_types=1);

namespace LeapOCR\Models;

readonly class PaginationInfo
{
    public function __construct(
        public int $page,
        public int $limit,
        public int $total,
        public int $totalPages,
    ) {
    }
}

