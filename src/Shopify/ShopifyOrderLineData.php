<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Shopify;

use InvalidArgumentException;

final readonly class ShopifyOrderLineData
{
    public function __construct(
        public ?string $sku,
        public int $quantity,
        public string $unitPrice,
        public string $currency,
    ) {
        if ($quantity < 0) {
            throw new InvalidArgumentException('Shopify order-line quantity must not be negative.');
        }
    }

    public static function fromApiResponse(array $line): self
    {
        $money = $line['discountedUnitPriceAfterAllDiscountsSet']['shopMoney'] ?? [];

        return new self(
            sku: isset($line['sku']) ? trim((string) $line['sku']) : null,
            quantity: (int) ($line['currentQuantity'] ?? 0),
            unitPrice: (string) ($money['amount'] ?? ''),
            currency: (string) ($money['currencyCode'] ?? ''),
        );
    }

    public function isReportable(): bool
    {
        return $this->sku !== null
            && $this->sku !== ''
            && $this->quantity > 0
            && is_numeric($this->unitPrice)
            && $this->currency !== '';
    }
}
