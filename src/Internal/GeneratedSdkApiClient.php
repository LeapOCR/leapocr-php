<?php

declare(strict_types=1);

namespace LeapOCR\Internal;

use LeapOCRGenerated\Api\SDKApi;
use LeapOCRGenerated\Model\CompleteDirectUploadRequest;
use LeapOCRGenerated\Model\DirectUploadRequest;
use LeapOCRGenerated\Model\ModelsOCRResultResponse;
use LeapOCRGenerated\Model\ModelsOCRStatusResponse;
use LeapOCRGenerated\Model\StatusResponse;
use LeapOCRGenerated\Model\UploadDirectUploadCompleteResponse;
use LeapOCRGenerated\Model\UploadDirectUploadResponse;
use LeapOCRGenerated\Model\UploadFromRemoteURLRequest;
use LeapOCRGenerated\Model\UploadRemoteURLUploadResponse;

class GeneratedSdkApiClient
{
    public function __construct(private readonly SDKApi $sdkApi)
    {
    }

    public function directUpload(DirectUploadRequest $request): UploadDirectUploadResponse
    {
        /** @var UploadDirectUploadResponse $response */
        $response = $this->sdkApi->directUpload($request);
        return $response;
    }

    public function completeDirectUpload(string $jobId, CompleteDirectUploadRequest $request): UploadDirectUploadCompleteResponse
    {
        /** @var UploadDirectUploadCompleteResponse $response */
        $response = $this->sdkApi->completeDirectUpload($jobId, $request);
        return $response;
    }

    public function uploadFromRemoteUrl(UploadFromRemoteURLRequest $request): UploadRemoteURLUploadResponse
    {
        /** @var UploadRemoteURLUploadResponse $response */
        $response = $this->sdkApi->uploadFromRemoteURL($request);
        return $response;
    }

    public function getJobStatus(string $jobId): StatusResponse
    {
        /** @var StatusResponse $response */
        $response = $this->sdkApi->getJobStatus($jobId);
        return $response;
    }

    public function getJobResult(string $jobId, int $page = 1, int $limit = 100): ModelsOCRResultResponse|ModelsOCRStatusResponse
    {
        $response = $this->sdkApi->getJobResult($jobId, $page, $limit);

        if ($response instanceof ModelsOCRResultResponse || $response instanceof ModelsOCRStatusResponse) {
            return $response;
        }

        throw new \RuntimeException('Unexpected job result response type');
    }
}

