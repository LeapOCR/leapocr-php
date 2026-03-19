<?php

declare(strict_types=1);

namespace LeapOCR\Tests\Unit;

use LeapOCR\LeapOCR;
use PHPUnit\Framework\TestCase;

final class WebhookSignatureTest extends TestCase
{
    public function testVerifyWebhookSignatureAcceptsValidSignature(): void
    {
        $payload = '{"object_key":"test.pdf","size":1024,"timestamp":"2024-01-01T00:00:00Z"}';
        $secret = 'my-secret-key';
        $signature = '394933efe0b0ad6082ac82d97a7fac1c8810ee48b2b395f2a1a75bf5403f9c8a';

        self::assertTrue(LeapOCR::verifyWebhookSignature($payload, $signature, $secret));
    }

    public function testVerifyWebhookSignatureRejectsInvalidSignature(): void
    {
        $payload = '{"object_key":"test.pdf","size":1024,"timestamp":"2024-01-01T00:00:00Z"}';

        self::assertFalse(LeapOCR::verifyWebhookSignature($payload, 'invalid-signature', 'my-secret-key'));
    }

    public function testVerifyWebhookSignatureRejectsDifferentPayload(): void
    {
        $payload = '{"object_key":"different.pdf","size":1024,"timestamp":"2024-01-01T00:00:00Z"}';
        $signature = '394933efe0b0ad6082ac82d97a7fac1c8810ee48b2b395f2a1a75bf5403f9c8a';

        self::assertFalse(LeapOCR::verifyWebhookSignature($payload, $signature, 'my-secret-key'));
    }

    public function testVerifyWebhookSignatureRejectsMissingInputs(): void
    {
        $payload = '{"object_key":"test.pdf","size":1024,"timestamp":"2024-01-01T00:00:00Z"}';

        self::assertFalse(LeapOCR::verifyWebhookSignature($payload, '', 'my-secret-key'));
        self::assertFalse(LeapOCR::verifyWebhookSignature($payload, 'abc', ''));
    }
}
