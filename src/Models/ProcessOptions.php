<?php

declare(strict_types=1);

namespace LeapOCR\Models;

use LeapOCR\Enums\Format;
use LeapOCR\Enums\Model;

readonly class ProcessOptions
{
    public function __construct(
        public ?Format $format = null,
        public string|Model|null $model = null,
        public ?array $schema = null,
        public ?string $instructions = null,
        public ?string $templateSlug = null,
        public ?string $fileName = null,
        public bool $extractBoundingBoxes = false,
        public bool $saveAsTemplate = false,
    ) {
    }

    public function modelValue(): ?string
    {
        if ($this->model instanceof Model) {
            return $this->model->value;
        }

        return $this->model;
    }
}

