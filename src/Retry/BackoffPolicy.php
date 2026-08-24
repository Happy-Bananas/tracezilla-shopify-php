<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Retry;

use InvalidArgumentException;

final readonly class BackoffPolicy
{
    /** @param list<int> $delaysInSeconds */
    public function __construct(
        private array $delaysInSeconds = [0, 300, 900, 3600, 21600],
        public int $maximumAutomaticAttempts = 5,
    ) {
        if ($maximumAutomaticAttempts < 1 || count($delaysInSeconds) < $maximumAutomaticAttempts) {
            throw new InvalidArgumentException('Backoff delays must cover every automatic attempt.');
        }
        foreach ($delaysInSeconds as $delay) {
            if ($delay < 0) {
                throw new InvalidArgumentException('Backoff delays must not be negative.');
            }
        }
    }

    public function delayForAttempt(int $attempt): int
    {
        if ($attempt < 1 || $attempt > $this->maximumAutomaticAttempts) {
            throw new InvalidArgumentException('Attempt is outside the automatic retry range.');
        }

        return $this->delaysInSeconds[$attempt - 1];
    }
}

