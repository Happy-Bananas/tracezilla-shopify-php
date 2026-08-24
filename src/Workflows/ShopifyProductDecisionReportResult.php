<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Workflows;

use Tracezilla\Shopify\Shared\CatalogItem;

final readonly class ShopifyProductDecisionReportResult
{
    /** @param list<CatalogItem> $candidates */
    public function __construct(
        public array $candidates,
        public int $limit,
    ) {}

    public function toArray(): array
    {
        return [
            'status' => $this->candidates === [] ? 'no_decisions_required' : 'decisions_required',
            'candidate_count' => count($this->candidates),
            'display_limit' => $this->limit,
            'candidates' => array_map(
                static fn (CatalogItem $item): array => [
                    'sku' => $item->sku,
                    'tracezilla_id' => $item->sourceId,
                    'name' => $item->name,
                    'decision' => 'create_product_add_variant_map_sku_or_exclude',
                ],
                array_slice($this->candidates, 0, $this->limit),
            ),
        ];
    }
}
