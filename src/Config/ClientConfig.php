<?php

declare(strict_types=1);

namespace LeapOCR\Config;

use GuzzleHttp\ClientInterface;

readonly class ClientConfig
{
    public function __construct(
        public string $baseUrl = 'https://api.leapocr.com/api/v1',
        public float $timeoutSeconds = 30.0,
        public int $maxRetries = 3,
        public int $retryDelayMilliseconds = 1000,
        public float $retryMultiplier = 2.0,
        public bool $debug = false,
        public ?ClientInterface $httpClient = null,
    ) {
    }
}

