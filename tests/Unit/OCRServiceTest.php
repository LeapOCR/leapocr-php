<?php

declare(strict_types=1);

namespace LeapOCR\Tests\Unit;

use LeapOCR\Config\ClientConfig;
use LeapOCR\Enums\Format;
use LeapOCR\Enums\Model;
use LeapOCR\Enums\JobStatusType;
use LeapOCR\Exceptions\JobException;
use LeapOCR\Internal\GeneratedSdkApiClient;
use LeapOCR\Internal\MultipartUploader;
use LeapOCR\Models\PollOptions;
use LeapOCR\Models\ProcessOptions;
use LeapOCR\OCRService;
use LeapOCRGenerated\Model\ModelsOCRResultResponse;
use LeapOCRGenerated\Model\ModelsOCRStatusResponse;
use LeapOCRGenerated\Model\ModelsPageResponse;
use LeapOCRGenerated\Model\StatusResponse;
use LeapOCRGenerated\Model\UploadCompletedPart;
use LeapOCRGenerated\Model\UploadDirectUploadCompleteResponse;
use LeapOCRGenerated\Model\UploadDirectUploadResponse;
use LeapOCRGenerated\Model\UploadMultipartPart;
use PHPUnit\Framework\TestCase;

final class OCRServiceTest extends TestCase
{
    public function testProcessFileCompletesMultipartFlow(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'leapocr-php-');
        self::assertNotFalse($file);
        $pdfPath = $file . '.pdf';
        rename($file, $pdfPath);
        file_put_contents($pdfPath, "%PDF-1.4\nhello");

        $apiClient = $this->createMock(GeneratedSdkApiClient::class);
        $uploader = $this->createMock(MultipartUploader::class);
        $service = new OCRService(new ClientConfig(), $apiClient, $uploader);

        $apiClient
            ->expects(self::once())
            ->method('directUpload')
            ->willReturn(new UploadDirectUploadResponse([
                'job_id' => 'job-123',
                'parts' => [
                    new UploadMultipartPart([
                        'part_number' => 1,
                        'start_byte' => 0,
                        'end_byte' => 11,
                        'upload_url' => 'https://example.test/upload-part',
                    ]),
                ],
            ]));

        $uploader
            ->expects(self::once())
            ->method('uploadFile')
            ->willReturn([
                new UploadCompletedPart([
                    'part_number' => 1,
                    'etag' => 'etag-1',
                ]),
            ]);

        $apiClient
            ->expects(self::once())
            ->method('completeDirectUpload')
            ->willReturn(new UploadDirectUploadCompleteResponse([
                'job_id' => 'job-123',
                'status' => 'pending',
                'created_at' => '2026-03-19T00:00:00Z',
            ]));

        $result = $service->processFile(
            $pdfPath,
            new ProcessOptions(
                format: Format::STRUCTURED,
                model: Model::STANDARD_V2,
                schema: ['type' => 'object'],
            ),
        );

        unlink($pdfPath);

        self::assertSame('job-123', $result->jobId);
        self::assertSame(JobStatusType::PENDING, $result->status);
    }

    public function testGetJobResultThrowsWhenStillProcessing(): void
    {
        $apiClient = $this->createMock(GeneratedSdkApiClient::class);
        $uploader = $this->createMock(MultipartUploader::class);
        $service = new OCRService(new ClientConfig(), $apiClient, $uploader);

        $apiClient
            ->expects(self::once())
            ->method('getJobResult')
            ->willReturn(new ModelsOCRStatusResponse([
                'job_id' => 'job-123',
                'status' => 'processing',
            ]));

        $this->expectException(JobException::class);
        $service->getJobResult('job-123');
    }

    public function testWaitUntilDoneReturnsResultOnceCompleted(): void
    {
        $apiClient = $this->createMock(GeneratedSdkApiClient::class);
        $uploader = $this->createMock(MultipartUploader::class);
        $service = new OCRService(new ClientConfig(), $apiClient, $uploader);

        $apiClient
            ->expects(self::exactly(2))
            ->method('getJobStatus')
            ->willReturnOnConsecutiveCalls(
                new StatusResponse([
                    'job_id' => 'job-123',
                    'status' => 'processing',
                    'processed_pages' => 0,
                    'total_pages' => 1,
                ]),
                new StatusResponse([
                    'job_id' => 'job-123',
                    'status' => 'completed',
                    'processed_pages' => 1,
                    'total_pages' => 1,
                ]),
            );

        $apiClient
            ->expects(self::once())
            ->method('getJobResult')
            ->willReturn(new ModelsOCRResultResponse([
                'job_id' => 'job-123',
                'status' => 'completed',
                'file_name' => 'test.pdf',
                'pages' => [
                    new ModelsPageResponse([
                        'page_number' => 1,
                        'result' => 'Hello world',
                    ]),
                ],
                'total_pages' => 1,
                'processed_pages' => 1,
                'credits_used' => 1,
                'model' => 'standard-v2',
                'result_format' => 'markdown',
                'completed_at' => '2026-03-19T00:00:10Z',
            ]));

        $result = $service->waitUntilDone('job-123', new PollOptions(
            pollIntervalSeconds: 0.001,
            maxWaitSeconds: 1.0,
        ));

        self::assertSame('job-123', $result->jobId);
        self::assertSame(JobStatusType::COMPLETED, $result->status);
        self::assertCount(1, $result->pages);
    }
}
