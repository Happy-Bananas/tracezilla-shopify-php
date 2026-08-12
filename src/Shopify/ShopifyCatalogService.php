<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Shopify;

use RuntimeException;
use Tracezilla\Shopify\Contracts\CatalogReader;
use Tracezilla\Shopify\Shopify\Mappers\ShopifyVariantMapper;
use Tracezilla\Shopify\Shopify\Queries\GetProductVariants;

final readonly class ShopifyCatalogService implements CatalogReader
{
    public function __construct(private ShopifyClient $client, private ShopifyVariantMapper $mapper) {}

    public function read(): array
    {
        $items = [];
        $after = null;

        do {
            $payload = $this->client->graphql(GetProductVariants::DOCUMENT, ['first' => 250, 'after' => $after]);
            $connection = $payload['data']['productVariants'] ?? null;
            if (! is_array($connection)) {
                throw new RuntimeException('Shopify response is missing productVariants.');
            }

            foreach ($connection['nodes'] ?? [] as $variant) {
                if (! is_array($variant)) {
                    continue;
                }
                $item = $this->mapper->map($variant);
                if ($item !== null) {
                    $items[] = $item;
                }
            }

            $hasNextPage = ($connection['pageInfo']['hasNextPage'] ?? false) === true;
            $after = $connection['pageInfo']['endCursor'] ?? null;
        } while ($hasNextPage && is_string($after) && $after !== '');

        return $items;
    }
}
