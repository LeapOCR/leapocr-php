# LeapOCR PHP SDK

[![Packagist Version](https://img.shields.io/packagist/v/leapocr/leapocr-php)](https://packagist.org/packages/leapocr/leapocr-php)
[![Packagist Downloads](https://img.shields.io/packagist/dt/leapocr/leapocr-php)](https://packagist.org/packages/leapocr/leapocr-php)
[![CI](https://github.com/LeapOCR/leapocr-php/actions/workflows/ci.yml/badge.svg)](https://github.com/LeapOCR/leapocr-php/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4.svg)](https://www.php.net/)

Official PHP SDK for [LeapOCR](https://www.leapocr.com/) - Transform documents into structured data using AI-powered OCR.

## Overview

LeapOCR provides enterprise-grade document processing with AI-powered data extraction. This SDK offers a modern PHP interface for local file uploads, remote URL processing, job polling, and result retrieval.

## Installation

```bash
composer require leapocr/leapocr-php
```

## Quick Start

### Prerequisites

- PHP 8.2 or higher
- LeapOCR API key ([sign up here](https://www.leapocr.com/signup))

### Basic Example

```php
<?php

require 'vendor/autoload.php';

use LeapOCR\Enums\Format;
use LeapOCR\Enums\Model;
use LeapOCR\LeapOCR;
use LeapOCR\Models\ProcessOptions;

$client = new LeapOCR((string) getenv('LEAPOCR_API_KEY'));

$job = $client->ocr()->processUrl(
    'https://example.com/document.pdf',
    new ProcessOptions(
        format: Format::STRUCTURED,
        model: Model::STANDARD_V2,
        schema: [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
            ],
            'required' => ['title'],
        ],
    ),
);

$result = $client->ocr()->waitUntilDone($job->jobId);

var_dump($result->pages[0]->result);
```

## Key Features

- **Idiomatic PHP API** - Enums, immutable value objects, and exception mapping for the public SDK surface
- **Generated from the live API spec** - Uses the real OpenAPI document from the running API
- **SDK-only public surface** - Generates and exposes only operations tagged for the SDK
- **Direct file upload support** - Handles presigned multipart upload flow for local files
- **Polling helpers** - Wait for completion with a single method call
- **Structured and markdown output** - Use templates or direct processing options
- **Webhook verification helper** - Verify incoming `X-R2-Signature` headers with the raw request body

## Processing Models

Use `Model::STANDARD_V2` or `Model::PRO_V2`, or pass a custom model string through `ProcessOptions`.

## Usage Examples

### Processing a Local File

```php
use LeapOCR\Enums\Format;
use LeapOCR\Enums\Model;
use LeapOCR\Models\ProcessOptions;

$job = $client->ocr()->processFile(
    __DIR__ . '/sample/test.pdf',
    new ProcessOptions(
        format: Format::STRUCTURED,
        model: Model::STANDARD_V2,
        instructions: 'Extract invoice number and total amount',
        schema: [
            'type' => 'object',
            'properties' => [
                'invoice_number' => ['type' => 'string'],
                'total_amount' => ['type' => 'number'],
            ],
            'required' => ['invoice_number', 'total_amount'],
        ],
    ),
);
```

### Waiting for Completion

```php
use LeapOCR\Models\PollOptions;

$result = $client->ocr()->waitUntilDone(
    $job->jobId,
    new PollOptions(
        pollIntervalSeconds: 2.0,
        maxWaitSeconds: 180.0,
    ),
);
```

### Using a Template

```php
use LeapOCR\Models\ProcessOptions;

$job = $client->ocr()->processFile(
    __DIR__ . '/sample/test.pdf',
    new ProcessOptions(templateSlug: 'invoice-template'),
);
```

### Getting Status and Results Separately

```php
$status = $client->ocr()->getJobStatus($job->jobId);

if ($status->status === \LeapOCR\Enums\JobStatusType::COMPLETED) {
    $result = $client->ocr()->getJobResult($job->jobId);
}
```

For more runnable samples, see [`examples/`](./examples).

## Output Formats

| Format | Description | Use Case |
| --- | --- | --- |
| `Format::STRUCTURED` | Single JSON object | Extract specific fields across the document |
| `Format::MARKDOWN` | Text per page | Convert a document into readable OCR text |

## Configuration

```php
use LeapOCR\Config\ClientConfig;
use LeapOCR\LeapOCR;

$client = new LeapOCR(
    (string) getenv('LEAPOCR_API_KEY'),
    new ClientConfig(
        baseUrl: 'https://api.leapocr.com/api/v1',
        timeoutSeconds: 30.0,
        maxRetries: 3,
        retryDelayMilliseconds: 1000,
        retryMultiplier: 2.0,
    ),
);
```

## Error Handling

The SDK throws typed exceptions for the public API:

- `AuthenticationException`
- `ValidationException`
- `FileException`
- `JobException`
- `JobFailedException`
- `JobTimeoutException`
- `RateLimitException`
- `NetworkException`
- `ApiException`

```php
use LeapOCR\Exceptions\AuthenticationException;
use LeapOCR\Exceptions\JobFailedException;
use LeapOCR\Exceptions\ValidationException;

try {
    $result = $client->ocr()->waitUntilDone($job->jobId);
} catch (AuthenticationException $exception) {
    // Invalid or missing API key
} catch (ValidationException $exception) {
    // Invalid request options
} catch (JobFailedException $exception) {
    // The job reached a failed terminal state
}
```

## Webhook Signature Verification

Use `LeapOCR::verifyWebhookSignature()` with the raw request body exactly as received:

```php
<?php

use LeapOCR\LeapOCR;

$rawBody = file_get_contents('php://input') ?: '';
$signature = $_SERVER['HTTP_X_R2_SIGNATURE'] ?? '';
$secret = (string) getenv('LEAPOCR_WEBHOOK_SECRET');

if (!LeapOCR::verifyWebhookSignature($rawBody, $signature, $secret)) {
    http_response_code(401);
    echo 'Invalid signature';
    exit;
}

$payload = json_decode($rawBody, true, flags: JSON_THROW_ON_ERROR);
```

Do not verify against re-encoded JSON. Use the original body string from the HTTP request.

## Development

### Tooling

The SDK uses [`mise`](https://mise.jdx.dev/) for local tooling:

```bash
mise install
mise exec php@8.3 ubi:composer/composer@2.8.12 -- composer install
```

### Regenerating the SDK

The generated client is derived from the live API and then filtered down to SDK-tagged operations only:

```bash
make fetch-spec
make filter-spec
make generate
```

### Running Tests

```bash
make test
LEAPOCR_API_KEY=your-api-key make test-integration
```

The integration suite reads `LEAPOCR_BASE_URL` and also accepts `OCR_BASE_URL` for parity with the other LeapOCR SDK test setups.

## Publishing

Publish the PHP SDK to [Packagist](https://packagist.org/packages/leapocr/leapocr-php). Composer users install it with:

```bash
composer require leapocr/leapocr-php
```

Versions should come from Git tags such as `v0.1.0`, not from a hard-coded
`version` field in `composer.json`.

### One-time setup

1. Create the public repository at `https://github.com/leapocr/leapocr-php`.
2. Submit that repository on Packagist as `leapocr/leapocr-php`.
3. Optionally add GitHub Actions secrets for explicit Packagist refreshes:
   - `PACKAGIST_USERNAME`
   - `PACKAGIST_TOKEN`

### Releasing

```bash
git tag -a v0.1.0 -m "Release v0.1.0"
git push origin v0.1.0
```

The release workflow will validate the package, run lint and unit tests, build
a release archive, create a GitHub release, and notify Packagist when the
Packagist secrets are configured.

For the full setup checklist, see [`.github/PACKAGIST_SETUP.md`](./.github/PACKAGIST_SETUP.md).

## Generation Notes

- The OpenAPI spec is fetched from the running API at `http://localhost:8443/api/v1/docs/openapi.json`
- `scripts/filter_sdk_endpoints.py` keeps only SDK-tagged operations and rewrites them to a single `SDK` tag
- OpenAPI Generator produces the low-level client in [`src/Generated`](./src/Generated)
- The public PHP API lives in [`src`](./src)
