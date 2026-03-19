<?php

declare(strict_types=1);

namespace LeapOCR\Internal;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use LeapOCR\Exceptions\NetworkException;
use LeapOCRGenerated\Model\UploadCompletedPart;
use LeapOCRGenerated\Model\UploadMultipartPart;

class MultipartUploader
{
    public function __construct(private readonly ClientInterface $httpClient)
    {
    }

    /**
     * @param array<int, UploadMultipartPart> $parts
     *
     * @return array<int, UploadCompletedPart>
     */
    public function uploadFile(string $filePath, array $parts): array
    {
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new NetworkException(sprintf('Unable to open file for upload: %s', $filePath));
        }

        $completedParts = [];

        try {
            foreach ($parts as $part) {
                $uploadUrl = $part->getUploadUrl();
                $startByte = $part->getStartByte();
                $endByte = $part->getEndByte();
                $partNumber = $part->getPartNumber();

                if ($uploadUrl === null || $startByte === null || $endByte === null || $partNumber === null) {
                    throw new NetworkException('Upload part response is missing required fields');
                }

                $chunk = $this->readChunk($handle, $startByte, $endByte, $partNumber);

                try {
                    $response = $this->httpClient->request('PUT', $uploadUrl, [
                        'body' => $chunk,
                        'headers' => ['Content-Type' => 'application/octet-stream'],
                    ]);
                } catch (GuzzleException $exception) {
                    throw new NetworkException(
                        sprintf('Failed to upload part %d', $partNumber),
                        null,
                        null,
                        $exception,
                    );
                }

                $statusCode = $response->getStatusCode();
                if ($statusCode < 200 || $statusCode >= 300) {
                    throw new NetworkException(sprintf(
                        'Failed to upload part %d: HTTP %d',
                        $partNumber,
                        $statusCode,
                    ));
                }

                $etag = trim($response->getHeaderLine('ETag'), '"');
                if ($etag === '') {
                    throw new NetworkException(sprintf('Missing ETag for uploaded part %d', $partNumber));
                }

                $completedParts[] = new UploadCompletedPart([
                    'part_number' => $partNumber,
                    'etag' => $etag,
                ]);
            }
        } finally {
            fclose($handle);
        }

        return $completedParts;
    }

    /**
     * @param resource $handle
     */
    private function readChunk(mixed $handle, int $startByte, int $endByte, int $partNumber): string
    {
        $length = ($endByte - $startByte) + 1;
        if ($length <= 0) {
            throw new NetworkException(sprintf('Invalid byte range for upload part %d', $partNumber));
        }

        if (fseek($handle, $startByte) !== 0) {
            throw new NetworkException(sprintf('Failed to seek to upload part %d', $partNumber));
        }

        $chunk = stream_get_contents($handle, $length);
        if (!is_string($chunk) || strlen($chunk) !== $length) {
            throw new NetworkException(sprintf('Failed to read upload chunk for part %d', $partNumber));
        }

        return $chunk;
    }
}
