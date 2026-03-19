<?php

declare(strict_types=1);

namespace LeapOCR\Models;

use Closure;

readonly class PollOptions
{
    public readonly ?Closure $onProgress;

    /**
     * @param null|callable(JobStatus):void $onProgress
     */
    public function __construct(
        public float $pollIntervalSeconds = 2.0,
        public float $maxWaitSeconds = 300.0,
        ?callable $onProgress = null,
    ) {
        $this->onProgress = $onProgress !== null ? Closure::fromCallable($onProgress) : null;
    }
}
