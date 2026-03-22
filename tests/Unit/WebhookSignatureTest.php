<?php

declare(strict_types=1);

namespace LeapOCR\Tests\Unit;

use LeapOCR\LeapOCR;
use PHPUnit\Framework\TestCase;

final class WebhookSignatureTest extends TestCase
{
    public function testVerifyWebhookSignatureAcceptsValidSignature(): void
    {
        $payload = '{"event_type":"webhook.test","event_id":"evt_123","timestamp":"2024-01-01T00:00:00Z","message":"This is a test webhook event"}';
        $secret = 'my-secret-key';
        $timestamp = '1704067200';
        $signature = '51033b9251a1db2003caf73152dc990d20c3efd8c97d176603de1dc034aa1bc1';

        self::assertTrue(LeapOCR::verifyWebhookSignature($payload, $signature, $timestamp, $secret));
    }

    public function testVerifyWebhookSignatureRejectsInvalidSignature(): void
    {
        $payload = '{"event_type":"webhook.test","event_id":"evt_123","timestamp":"2024-01-01T00:00:00Z","message":"This is a test webhook event"}';

        self::assertFalse(LeapOCR::verifyWebhookSignature($payload, 'invalid-signature', '1704067200', 'my-secret-key'));
    }

    public function testVerifyWebhookSignatureRejectsDifferentPayload(): void
    {
        $payload = '{"event_type":"webhook.test","event_id":"evt_123","timestamp":"2024-01-01T00:00:00Z","message":"different payload"}';
        $signature = '51033b9251a1db2003caf73152dc990d20c3efd8c97d176603de1dc034aa1bc1';

        self::assertFalse(LeapOCR::verifyWebhookSignature($payload, $signature, '1704067200', 'my-secret-key'));
    }

    public function testVerifyWebhookSignatureRejectsDifferentTimestamp(): void
    {
        $payload = '{"event_type":"webhook.test","event_id":"evt_123","timestamp":"2024-01-01T00:00:00Z","message":"This is a test webhook event"}';
        $signature = '51033b9251a1db2003caf73152dc990d20c3efd8c97d176603de1dc034aa1bc1';

        self::assertFalse(LeapOCR::verifyWebhookSignature($payload, $signature, '1704067201', 'my-secret-key'));
    }

    public function testVerifyWebhookSignatureRejectsMissingInputs(): void
    {
        $payload = '{"event_type":"webhook.test","event_id":"evt_123","timestamp":"2024-01-01T00:00:00Z","message":"This is a test webhook event"}';

        self::assertFalse(LeapOCR::verifyWebhookSignature($payload, '', '1704067200', 'my-secret-key'));
        self::assertFalse(LeapOCR::verifyWebhookSignature($payload, 'abc', '', 'my-secret-key'));
        self::assertFalse(LeapOCR::verifyWebhookSignature($payload, 'abc', '1704067200', ''));
    }
}
