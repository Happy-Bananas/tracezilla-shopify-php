<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Workflows;

use DateTimeZone;
use InvalidArgumentException;
use Throwable;

final readonly class CollectedOrderReportOptions
{
    public function __construct(
        public int $days = 3,
        public string $timezone = 'UTC',
        public ?int $limit = 10,
    ) {
        if ($days < 1) {
            throw new InvalidArgumentException('Collected-order days must be a positive integer.');
        }
        if ($limit !== null && $limit < 1) {
            throw new InvalidArgumentException('Collected-order limit must be a positive integer.');
        }
        try {
            new DateTimeZone($timezone);
        } catch (Throwable) {
            throw new InvalidArgumentException("Invalid timezone [{$timezone}].");
        }
    }
}
