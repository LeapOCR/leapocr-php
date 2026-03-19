<?php

declare(strict_types=1);

namespace LeapOCR\Tests\Unit;

use LeapOCR\Enums\Format;
use LeapOCR\Enums\Model;
use LeapOCR\Exceptions\FileException;
use LeapOCR\Exceptions\ValidationException;
use LeapOCR\Internal\Validation\FileValidator;
use LeapOCR\Models\ProcessOptions;
use PHPUnit\Framework\TestCase;

final class FileValidatorTest extends TestCase
{
    public function testValidateFilePathAcceptsSupportedPdf(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'leapocr-php-');
        self::assertNotFalse($file);
        $pdfPath = $file . '.pdf';
        rename($file, $pdfPath);
        file_put_contents($pdfPath, "%PDF-1.4\nhello");

        FileValidator::validateFilePath($pdfPath);

        unlink($pdfPath);
        self::assertTrue(true);
    }

    public function testValidateFilePathRejectsUnsupportedExtension(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'leapocr-php-');
        self::assertNotFalse($file);
        $txtPath = $file . '.txt';
        rename($file, $txtPath);
        file_put_contents($txtPath, 'hello');

        $this->expectException(FileException::class);
        FileValidator::validateFilePath($txtPath);
    }

    public function testMarkdownProcessingRequiresModel(): void
    {
        $this->expectException(ValidationException::class);

        FileValidator::validateProcessOptions(
            new ProcessOptions(format: Format::MARKDOWN),
        );
    }

    public function testStructuredProcessingRequiresSchema(): void
    {
        $this->expectException(ValidationException::class);

        FileValidator::validateProcessOptions(
            new ProcessOptions(format: Format::STRUCTURED, model: Model::STANDARD_V2),
        );
    }

    public function testTemplateSlugCannotBeCombinedWithDirectProcessingOptions(): void
    {
        $this->expectException(ValidationException::class);

        FileValidator::validateProcessOptions(
            new ProcessOptions(
                templateSlug: 'invoice-template',
                format: Format::STRUCTURED,
            ),
        );
    }
}

