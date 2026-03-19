<?php

declare(strict_types=1);

namespace LeapOCR;

use DateTimeImmutable;
use LeapOCR\Config\ClientConfig;
use LeapOCR\Enums\JobStatusType;
use LeapOCR\Exceptions\FileException;
use LeapOCR\Exceptions\JobException;
use LeapOCR\Exceptions\JobFailedException;
use LeapOCR\Exceptions\JobTimeoutException;
use LeapOCR\Internal\GeneratedSdkApiClient;
use LeapOCR\Internal\MultipartUploader;
use LeapOCR\Internal\Retry;
use LeapOCR\Internal\Validation\FileValidator;
use LeapOCR\Models\JobResult;
use LeapOCR\Models\JobStatus;
use LeapOCR\Models\PageResult;
use LeapOCR\Models\PaginationInfo;
use LeapOCR\Models\PollOptions;
use LeapOCR\Models\ProcessOptions;
use LeapOCR\Models\ProcessResult;
use LeapOCRGenerated\Model\CompleteDirectUploadRequest;
use LeapOCRGenerated\Model\DirectUploadRequest;
use LeapOCRGenerated\Model\ModelsOCRResultResponse;
use LeapOCRGenerated\Model\ModelsOCRStatusResponse;
use LeapOCRGenerated\Model\ModelsPageResponse;
use LeapOCRGenerated\Model\ModelsPaginationResponse;
use LeapOCRGenerated\Model\StatusResponse;
use LeapOCRGenerated\Model\UploadDirectUploadCompleteResponse;
use LeapOCRGenerated\Model\UploadMultipartPart;
use LeapOCRGenerated\Model\UploadFromRemoteURLRequest;
use LeapOCRGenerated\Model\UploadRemoteURLUploadResponse;

class OCRService
{
    public function __construct(
        private readonly ClientConfig $config,
        private readonly GeneratedSdkApiClient $client,
        private readonly MultipartUploader $multipartUploader,
    ) {
    }

    public function processFile(string $filePath, ?ProcessOptions $options = null): ProcessResult
    {
        $options ??= new ProcessOptions();
        FileValidator::validateFilePath($filePath);
        FileValidator::validateProcessOptions($options);

        $fileName = basename($filePath);
        $fileSize = filesize($filePath);
        if ($fileSize === false || $fileSize <= 0) {
            throw new FileException(sprintf('Unable to determine file size: %s', $filePath));
        }

        $directUploadResponse = Retry::run(
            fn (): mixed => $this->client->directUpload(new DirectUploadRequest(array_merge([
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'content_type' => FileValidator::contentTypeForFileName($fileName),
            ], $this->buildProcessingPayload($options, false)))),
            $this->config,
        );

        $jobId = $directUploadResponse->getJobId();
        if ($jobId === null || $jobId === '') {
            throw new JobException('Direct upload response did not include a job ID');
        }

        $completedParts = $this->multipartUploader->uploadFile(
            $filePath,
            $this->normalizeMultipartParts($directUploadResponse->getParts()),
        );

        $completionResponse = Retry::run(
            fn (): mixed => $this->client->completeDirectUpload(
                $jobId,
                new CompleteDirectUploadRequest(['parts' => $completedParts]),
            ),
            $this->config,
        );

        return $this->mapProcessFileResult($jobId, $completionResponse);
    }

    public function processUrl(string $url, ?ProcessOptions $options = null): ProcessResult
    {
        $options ??= new ProcessOptions();
        FileValidator::validateProcessOptions($options);

        $payload = array_merge(['url' => $url], $this->buildProcessingPayload($options, true));
        /** @var UploadRemoteURLUploadResponse $response */
        $response = Retry::run(
            fn (): mixed => $this->client->uploadFromRemoteUrl(new UploadFromRemoteURLRequest($payload)),
            $this->config,
        );

        return new ProcessResult(
            $response->getJobId() ?? '',
            JobStatusType::fromString($response->getStatus()),
            $this->parseDateTime($response->getCreatedAt()),
            $response->getSourceUrl(),
        );
    }

    public function getJobStatus(string $jobId): JobStatus
    {
        /** @var StatusResponse $response */
        $response = Retry::run(
            fn (): mixed => $this->client->getJobStatus($jobId),
            $this->config,
        );

        return $this->mapJobStatus($jobId, $response);
    }

    public function getJobResult(string $jobId, int $page = 1, int $limit = 100): JobResult
    {
        $response = Retry::run(
            fn (): mixed => $this->client->getJobResult($jobId, $page, $limit),
            $this->config,
        );

        if ($response instanceof ModelsOCRStatusResponse) {
            throw new JobException('Job is still processing', $jobId);
        }

        return $this->mapJobResult($response);
    }

    public function waitUntilDone(string $jobId, ?PollOptions $options = null): JobResult
    {
        $options ??= new PollOptions();
        $deadline = microtime(true) + $options->maxWaitSeconds;

        while (microtime(true) <= $deadline) {
            $status = $this->getJobStatus($jobId);
            if ($options->onProgress !== null) {
                ($options->onProgress)($status);
            }

            if ($status->status === JobStatusType::FAILED) {
                throw new JobFailedException($status->errorMessage ?? 'Job failed', $jobId);
            }

            if ($status->status === JobStatusType::COMPLETED || $status->status === JobStatusType::PARTIALLY_DONE) {
                try {
                    return $this->getJobResult($jobId);
                } catch (JobException) {
                    // The status endpoint can become consistent slightly ahead of the result endpoint.
                }
            }

            usleep((int) round(max(0.0, $options->pollIntervalSeconds) * 1_000_000));
        }

        throw new JobTimeoutException('Timed out waiting for job completion', $jobId);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProcessingPayload(ProcessOptions $options, bool $allowFileName): array
    {
        $payload = [
            'extract_bounding_boxes' => $options->extractBoundingBoxes,
            'save_as_template' => $options->saveAsTemplate,
        ];

        if ($allowFileName && $options->fileName !== null) {
            $payload['file_name'] = $options->fileName;
        }

        if ($options->templateSlug !== null) {
            $payload['template_slug'] = $options->templateSlug;
            return $payload;
        }

        if ($options->format !== null) {
            $payload['format'] = $options->format->value;
        }

        if ($options->modelValue() !== null) {
            $payload['model'] = $options->modelValue();
        }

        if ($options->instructions !== null) {
            $payload['instructions'] = $options->instructions;
        }

        if ($options->schema !== null) {
            $payload['schema'] = $options->schema;
        }

        return $payload;
    }

    /**
     * @param array<int, UploadMultipartPart>|null $parts
     *
     * @return array<int, UploadMultipartPart>
     */
    private function normalizeMultipartParts(?array $parts): array
    {
        $normalized = array_values(array_filter(
            $parts ?? [],
            static fn (mixed $part): bool => $part instanceof UploadMultipartPart,
        ));

        if ($normalized === []) {
            throw new JobException('Direct upload response did not include any upload parts');
        }

        return $normalized;
    }

    private function mapProcessFileResult(string $jobId, UploadDirectUploadCompleteResponse $response): ProcessResult
    {
        return new ProcessResult(
            $response->getJobId() ?? $jobId,
            JobStatusType::fromString($response->getStatus()),
            $this->parseDateTime($response->getCreatedAt()),
        );
    }

    private function mapJobStatus(string $jobId, StatusResponse $response): JobStatus
    {
        $processedPages = $response->getProcessedPages() ?? 0;
        $totalPages = $response->getTotalPages() ?? 0;
        $progress = $totalPages > 0 ? ($processedPages / $totalPages) * 100 : 0.0;

        return new JobStatus(
            $response->getId() ?? $jobId,
            JobStatusType::fromString($response->getStatus()),
            $processedPages,
            $totalPages,
            $progress,
            $this->parseDateTime($response->getCreatedAt()),
            $response->getResultFormat(),
            $response->getErrorMessage(),
        );
    }

    private function mapJobResult(ModelsOCRResultResponse $response): JobResult
    {
        $pages = array_map(
            fn (ModelsPageResponse $page): PageResult => new PageResult(
                $page->getPageNumber() ?? 0,
                $page->getResult(),
                $page->getId(),
                $page->getConfidence(),
                $page->getHasBoundingBoxes() ?? false,
                $this->mapBoundingBoxes($page->getBoundingBoxes()),
                $this->mapDimensions($page->getDimensions()?->getWidth(), $page->getDimensions()?->getHeight()),
            ),
            array_values(array_filter(
                $response->getPages() ?? [],
                static fn (mixed $page): bool => $page instanceof ModelsPageResponse,
            )),
        );

        return new JobResult(
            $response->getJobId() ?? '',
            JobStatusType::fromString($response->getStatus()),
            $pages,
            $response->getFileName(),
            $response->getTotalPages() ?? 0,
            $response->getProcessedPages() ?? 0,
            $response->getCreditsUsed() ?? 0,
            $response->getModel(),
            $response->getResultFormat(),
            $this->parseDateTime($response->getCompletedAt()),
            $this->mapPagination($response->getPagination()),
        );
    }

    /**
     * @param mixed $boundingBoxes
     *
     * @return array<int, array<string, mixed>>
     */
    private function mapBoundingBoxes(mixed $boundingBoxes): array
    {
        if (!is_array($boundingBoxes)) {
            return [];
        }

        return array_map(
            static fn (mixed $item): array => is_object($item) && method_exists($item, 'jsonSerialize')
                ? $item->jsonSerialize()
                : (array) $item,
            $boundingBoxes,
        );
    }

    /**
     * @return null|array<string, mixed>
     */
    private function mapDimensions(?int $width, ?int $height): ?array
    {
        if ($width === null && $height === null) {
            return null;
        }

        return [
            'width' => $width,
            'height' => $height,
        ];
    }

    private function mapPagination(?ModelsPaginationResponse $pagination): ?PaginationInfo
    {
        if ($pagination === null) {
            return null;
        }

        return new PaginationInfo(
            $pagination->getPage() ?? 1,
            $pagination->getLimit() ?? 100,
            $pagination->getTotal() ?? 0,
            $pagination->getTotalPages() ?? 0,
        );
    }

    private function parseDateTime(?string $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return new DateTimeImmutable($value);
    }
}
