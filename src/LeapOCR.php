<?php

declare(strict_types=1);

namespace LeapOCR;

use GuzzleHttp\Client;
use LeapOCR\Config\ClientConfig;
use LeapOCR\Exceptions\AuthenticationException;
use LeapOCR\Internal\GeneratedSdkApiClient;
use LeapOCR\Internal\MultipartUploader;
use LeapOCRGenerated\Api\SDKApi;
use LeapOCRGenerated\Configuration;

final class LeapOCR
{
    public const VERSION = '2.0.1';

    private readonly OCRService $ocr;

    public function __construct(string $apiKey, ?ClientConfig $config = null)
    {
        if (trim($apiKey) === '') {
            throw new AuthenticationException('API key is required');
        }

        $config ??= new ClientConfig();

        $httpClient = $config->httpClient ?? new Client([
            'base_uri' => rtrim($config->baseUrl, '/') . '/',
            'timeout' => $config->timeoutSeconds,
        ]);

        $generatedConfiguration = new Configuration();
        $generatedConfiguration->setHost(rtrim($config->baseUrl, '/'));
        $generatedConfiguration->setApiKey('X-API-KEY', $apiKey);
        $generatedConfiguration->setUserAgent(sprintf('leapocr-php/%s', self::VERSION));

        $generatedClient = new GeneratedSdkApiClient(new SDKApi($httpClient, $generatedConfiguration));

        $this->ocr = new OCRService(
            $config,
            $generatedClient,
            new MultipartUploader($httpClient),
        );
    }

    public function ocr(): OCRService
    {
        return $this->ocr;
    }

    public static function verifyWebhookSignature(string $payload, string $signature, string $timestamp, string $secret): bool
    {
        if ($signature === '' || $timestamp === '' || $secret === '') {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        return hash_equals($expectedSignature, $signature);
    }
}
