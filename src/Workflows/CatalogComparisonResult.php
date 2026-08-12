<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Workflows;

final readonly class CatalogComparisonResult
{
    public function __construct(
        public array $presentInBoth,
        public array $onlyInShopify,
        public array $onlyInTracezilla,
        public int $limit,
    ) {}

    public function toArray(): array
    {
        return [
            'status' => $this->onlyInShopify === [] && $this->onlyInTracezilla === [] ? 'match' : 'differences',
            'display_limit' => $this->limit,
            'matched_count' => count($this->presentInBoth),
            'only_in_shopify_count' => count($this->onlyInShopify),
            'only_in_tracezilla_count' => count($this->onlyInTracezilla),
            'present_in_both' => $this->presentInBoth,
            'only_in_shopify' => $this->onlyInShopify,
            'only_in_tracezilla' => $this->onlyInTracezilla,
        ];
    }
}
