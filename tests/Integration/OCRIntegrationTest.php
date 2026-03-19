<?php

declare(strict_types=1);

namespace LeapOCR\Tests\Integration;

use LeapOCR\Config\ClientConfig;
use LeapOCR\Enums\Format;
use LeapOCR\Enums\JobStatusType;
use LeapOCR\Enums\Model;
use LeapOCR\LeapOCR;
use LeapOCR\Models\PollOptions;
use LeapOCR\Models\ProcessOptions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class OCRIntegrationTest extends TestCase
{
    private const STRUCTURED_SCHEMA = [
        'type' => 'object',
        'properties' => [
            'text' => ['type' => 'string'],
        ],
        'required' => ['text'],
    ];

    private function createClient(): LeapOCR
    {
        $apiKey = getenv('LEAPOCR_API_KEY') ?: '';
        if ($apiKey === '') {
            $this->markTestSkipped('LEAPOCR_API_KEY environment variable is required');
        }

        $baseUrl = getenv('LEAPOCR_BASE_URL')
            ?: getenv('OCR_BASE_URL')
            ?: 'http://localhost:8443/api/v1';

        return new LeapOCR($apiKey, new ClientConfig(
            baseUrl: $baseUrl,
            timeoutSeconds: 120.0,
        ));
    }

    private function samplePdfPath(): string
    {
        $path = dirname(__DIR__, 2) . '/sample/test.pdf';
        if (!is_file($path)) {
            $this->markTestSkipped('sample/test.pdf is required for integration tests');
        }

        return $path;
    }

    public function testProcessFileAndFetchStatus(): void
    {
        $client = $this->createClient();

        $job = $client->ocr()->processFile(
            $this->samplePdfPath(),
            new ProcessOptions(
                format: Format::STRUCTURED,
                model: Model::STANDARD_V2,
                instructions: 'Extract all text and key information',
                schema: self::STRUCTURED_SCHEMA,
            ),
        );

        self::assertNotSame('', $job->jobId);

        $status = $client->ocr()->getJobStatus($job->jobId);
        self::assertSame($job->jobId, $status->jobId);
        self::assertContains($status->status, [
            JobStatusType::PENDING,
            JobStatusType::PROCESSING,
            JobStatusType::COMPLETED,
            JobStatusType::PARTIALLY_DONE,
            JobStatusType::FAILED,
        ]);
    }

    public function testWaitUntilDone(): void
    {
        $client = $this->createClient();

        $job = $client->ocr()->processFile(
            $this->samplePdfPath(),
            new ProcessOptions(
                format: Format::MARKDOWN,
                model: Model::STANDARD_V2,
            ),
        );

        $result = $client->ocr()->waitUntilDone($job->jobId, new PollOptions(
            pollIntervalSeconds: 2.0,
            maxWaitSeconds: 180.0,
        ));

        self::assertSame(JobStatusType::COMPLETED, $result->status);
        self::assertNotEmpty($result->pages);
    }

    public function testProcessUrlWhenEnabled(): void
    {
        $enabled = strtolower((string) getenv('LEAPOCR_URL_UPLOAD_ENABLED'));
        if (!in_array($enabled, ['1', 'true', 'yes'], true)) {
            self::markTestSkipped('LEAPOCR_URL_UPLOAD_ENABLED not set');
        }

        $client = $this->createClient();

        $job = $client->ocr()->processUrl(
            getenv('TEST_DOCUMENT_URL') ?: 'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf',
            new ProcessOptions(
                format: Format::MARKDOWN,
                model: Model::STANDARD_V2,
            ),
        );

        $result = $client->ocr()->waitUntilDone($job->jobId, new PollOptions(
            pollIntervalSeconds: 2.0,
            maxWaitSeconds: 180.0,
        ));

        self::assertNotEmpty($result->pages);
    }
}
