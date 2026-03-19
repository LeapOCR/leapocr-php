<?php

declare(strict_types=1);

namespace LeapOCR\Enums;

enum JobStatusType: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case PARTIALLY_DONE = 'partially_done';
    case FAILED = 'failed';

    public static function fromString(?string $value): self
    {
        return match ($value) {
            self::PROCESSING->value => self::PROCESSING,
            self::COMPLETED->value => self::COMPLETED,
            self::PARTIALLY_DONE->value => self::PARTIALLY_DONE,
            self::FAILED->value => self::FAILED,
            default => self::PENDING,
        };
    }
}

