<?php

declare(strict_types=1);

namespace LeapOCR\Internal;

use LeapOCR\Config\ClientConfig;
use LeapOCR\Exceptions\LeapOCRException;
use Throwable;

final class Retry
{
    /**
     * @template T
     *
     * @param callable():T $operation
     *
     * @return T
     */
    public static function run(callable $operation, ClientConfig $config): mixed
    {
        $attempt = 0;
        $delay = $config->retryDelayMilliseconds;

        while (true) {
            try {
                return $operation();
            } catch (Throwable $throwable) {
                $attempt++;
                $exception = ErrorMapper::normalize($throwable);

                if ($attempt > $config->maxRetries || !ErrorMapper::isRetriable($exception)) {
                    throw $exception;
                }

                usleep(max(0, $delay) * 1000);
                $delay = (int) max(1, round($delay * $config->retryMultiplier));
            }
        }
    }
}

