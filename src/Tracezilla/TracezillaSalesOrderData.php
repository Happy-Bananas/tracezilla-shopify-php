<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Tracezilla;

final readonly class TracezillaSalesOrderData
{
    /** @param list<array{sku_code:string,quantity:int,unit_price:float,price_per:string}> $lines */
    public function __construct(
        public string $externalReference,
        public string $shopifyOrderName,
        public array $orderHeader,
        public array $lines,
    ) {}

    public function toApiPayload(): array
    {
        return [
            'order_header' => $this->orderHeader,
            'outbound_skus' => $this->lines,
            'price_logic' => 'none',
            'action_on_missing_lot_selection' => 'none',
            'action_on_missing_inventory' => 'none',
            'post_save_action' => 'none',
            'ignore_order_state' => false,
            'ignore_missing_skus' => false,
        ];
    }
}
