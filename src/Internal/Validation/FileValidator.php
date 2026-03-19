<?php

declare(strict_types=1);

namespace LeapOCR\Internal\Validation;

use LeapOCR\Enums\Format;
use LeapOCR\Exceptions\FileException;
use LeapOCR\Exceptions\ValidationException;
use LeapOCR\Models\ProcessOptions;

final class FileValidator
{
    public const MAX_FILE_SIZE_BYTES = 1_073_741_824;
    public const MAX_INSTRUCTIONS_LENGTH = 100;
    public const MULTIPART_THRESHOLD_BYTES = 50 * 1024 * 1024;

    /**
     * @var list<string>
     */
    public const SUPPORTED_EXTENSIONS = ['pdf', 'png', 'jpg', 'jpeg', 'tiff', 'tif', 'webp'];

    public static function validateFilePath(string $filePath): void
    {
        if (!file_exists($filePath)) {
            throw new FileException(sprintf('File not found: %s', $filePath));
        }

        if (!is_file($filePath)) {
            throw new FileException(sprintf('Path is not a file: %s', $filePath));
        }

        $fileName = basename($filePath);
        self::validateExtension($fileName);

        $size = filesize($filePath);
        if ($size === false || $size <= 0) {
            throw new FileException(sprintf('File is empty: %s', $filePath));
        }

        if ($size > self::MAX_FILE_SIZE_BYTES) {
            throw new FileException(sprintf(
                'File size %d bytes exceeds the maximum allowed size of %d bytes',
                $size,
                self::MAX_FILE_SIZE_BYTES,
            ));
        }
    }

    public static function validateProcessOptions(ProcessOptions $options): void
    {
        if ($options->fileName !== null && trim($options->fileName) === '') {
            throw new ValidationException('fileName cannot be empty');
        }

        if ($options->fileName !== null && mb_strlen($options->fileName) > 255) {
            throw new ValidationException('fileName cannot be longer than 255 characters');
        }

        if ($options->instructions !== null && mb_strlen($options->instructions) > self::MAX_INSTRUCTIONS_LENGTH) {
            throw new ValidationException(sprintf(
                'instructions cannot be longer than %d characters',
                self::MAX_INSTRUCTIONS_LENGTH,
            ));
        }

        if ($options->templateSlug !== null) {
            if ($options->format !== null || $options->model !== null || $options->schema !== null || $options->instructions !== null || $options->saveAsTemplate) {
                throw new ValidationException(
                    'templateSlug cannot be combined with format, model, schema, instructions, or saveAsTemplate',
                );
            }

            return;
        }

        if ($options->format === null) {
            throw new ValidationException('format is required when templateSlug is not provided');
        }

        if ($options->format === Format::MARKDOWN && $options->modelValue() === null) {
            throw new ValidationException('model is required for markdown processing');
        }

        if ($options->format === Format::MARKDOWN && $options->schema !== null) {
            throw new ValidationException('schema is only supported for structured processing');
        }

        if ($options->format === Format::STRUCTURED && $options->schema === null) {
            throw new ValidationException('schema is required for structured processing');
        }

        if ($options->instructions !== null && $options->format !== Format::STRUCTURED) {
            throw new ValidationException('instructions are only supported for structured processing');
        }
    }

    public static function contentTypeForFileName(string $fileName): string
    {
        $extension = self::extension($fileName);

        return match ($extension) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'tif', 'tiff' => 'image/tiff',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };
    }

    private static function validateExtension(string $fileName): void
    {
        $extension = self::extension($fileName);
        if (in_array($extension, self::SUPPORTED_EXTENSIONS, true)) {
            return;
        }

        throw new FileException(sprintf(
            'Unsupported file type .%s. Supported types: %s',
            $extension === '' ? '(none)' : $extension,
            implode(', ', array_map(static fn (string $item): string => sprintf('.%s', $item), self::SUPPORTED_EXTENSIONS)),
        ));
    }

    private static function extension(string $fileName): string
    {
        return strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    }
}

