<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Shopify;

use RuntimeException;
use Tracezilla\Shopify\Contracts\CatalogReader;
use Tracezilla\Shopify\Contracts\ShopifyVariantReader;
use Tracezilla\Shopify\Shopify\Mappers\ShopifyVariantMapper;
use Tracezilla\Shopify\Shopify\Queries\GetProductVariants;

final readonly class ShopifyCatalogService implements CatalogReader, ShopifyVariantReader
{
    public function __construct(private ShopifyClient $client, private ShopifyVariantMapper $mapper) {}

    public function read(): array
    {
        $items = [];
        foreach ($this->readVariants() as $variant) {
            $item = $this->mapper->map($variant);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    public function readVariants(): array
    {
        $variants = [];
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
                $variants[] = ShopifyVariantData::fromApiResponse($variant);
            }

            $hasNextPage = ($connection['pageInfo']['hasNextPage'] ?? false) === true;
            $after = $connection['pageInfo']['endCursor'] ?? null;
        } while ($hasNextPage && is_string($after) && $after !== '');

        return $variants;
    }
}
