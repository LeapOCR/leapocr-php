<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use LeapOCR\Enums\Format;
use LeapOCR\Enums\Model;
use LeapOCR\LeapOCR;
use LeapOCR\Models\ProcessOptions;

$apiKey = getenv('LEAPOCR_API_KEY') ?: '';
if ($apiKey === '') {
    fwrite(STDERR, "LEAPOCR_API_KEY is required\n");
    exit(1);
}

$filePath = $argv[1] ?? dirname(__DIR__) . '/sample/test.pdf';
if (!is_file($filePath)) {
    fwrite(STDERR, sprintf("File not found: %s\n", $filePath));
    exit(1);
}

$client = new LeapOCR($apiKey);

$job = $client->ocr()->processFile(
    $filePath,
    new ProcessOptions(
        format: Format::STRUCTURED,
        model: Model::STANDARD_V2,
        schema: [
            'type' => 'object',
            'properties' => [
                'text' => ['type' => 'string'],
            ],
            'required' => ['text'],
        ],
    ),
);

$result = $client->ocr()->waitUntilDone($job->jobId);

foreach ($result->pages as $page) {
    echo sprintf("Page %d\n", $page->pageNumber);
    echo str_repeat('-', 20) . PHP_EOL;
    echo json_encode($page->result, JSON_PRETTY_PRINT) . PHP_EOL;
    echo PHP_EOL;
}
