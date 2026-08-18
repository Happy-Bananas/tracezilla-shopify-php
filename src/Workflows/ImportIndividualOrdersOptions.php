<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Workflows;

use InvalidArgumentException;

final readonly class ImportIndividualOrdersOptions
{
    public function __construct(
        public string $customerName,
        public int $warehouseLocationNumber,
        public bool $dryRun = true,
        public int $days = 3,
        public int $limit = 10,
    ) {
        if (trim($customerName) === '') {
            throw new InvalidArgumentException('The tracezilla customer name is required.');
        }
        if ($warehouseLocationNumber < 1) {
            throw new InvalidArgumentException('The tracezilla warehouse number must be positive.');
        }
        if ($days < 1) {
            throw new InvalidArgumentException('The order-import day window must be positive.');
        }
        if ($limit < 1) {
            throw new InvalidArgumentException('The order-import limit must be positive.');
        }
    }
}
