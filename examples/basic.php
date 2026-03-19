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

$client = new LeapOCR($apiKey);

$job = $client->ocr()->processUrl(
    'https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf',
    new ProcessOptions(
        format: Format::MARKDOWN,
        model: Model::STANDARD_V2,
    ),
);

$result = $client->ocr()->waitUntilDone($job->jobId);

foreach ($result->pages as $page) {
    echo sprintf("Page %d\n", $page->pageNumber);
    echo str_repeat('-', 20) . PHP_EOL;
    echo is_string($page->result) ? $page->result : json_encode($page->result, JSON_PRETTY_PRINT) . PHP_EOL;
    echo PHP_EOL;
}
