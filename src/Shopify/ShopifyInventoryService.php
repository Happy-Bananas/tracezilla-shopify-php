<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Shopify;

use RuntimeException;
use Tracezilla\Shopify\Contracts\ShopifyInventoryGateway;
use Tracezilla\Shopify\Shopify\Mappers\ShopifyInventoryItemMapper;
use Tracezilla\Shopify\Shopify\Mutations\SetInventoryQuantity;
use Tracezilla\Shopify\Shopify\Queries\GetInventoryItems;

final readonly class ShopifyInventoryService implements ShopifyInventoryGateway
{
    public function __construct(private ShopifyClient $client, private ShopifyInventoryItemMapper $mapper) {}

    /** @return array<string, ShopifyInventoryItem> */
    public function readAtLocation(string $locationId): array
    {
        $items = []; $after = null; $seen = [];
        do {
            $payload = $this->client->graphql(GetInventoryItems::DOCUMENT, ['first' => 250, 'after' => $after, 'locationId' => $locationId]);
            $connection = $payload['data']['productVariants'] ?? null;
            if (! is_array($connection) || ! is_array($connection['nodes'] ?? null) || ! is_array($connection['pageInfo'] ?? null)) {
                throw new RuntimeException('Shopify response is missing productVariants inventory.');
            }
            foreach ($connection['nodes'] as $variant) {
                if (! is_array($variant)) throw new RuntimeException('Shopify returned an invalid inventory item.');
                $item = $this->mapper->map($variant);
                if ($item !== null) $items[$item->sku] = $item;
            }
            $page = $connection['pageInfo'];
            if (($page['hasNextPage'] ?? false) !== true) break;
            $after = $page['endCursor'] ?? null;
            if (! is_string($after) || $after === '' || isset($seen[$after])) throw new RuntimeException('Shopify returned an invalid or repeated inventory cursor.');
            $seen[$after] = true;
        } while (true);
        return $items;
    }

    public function setAvailable(ShopifyInventoryItem $item, int $quantity, string $locationId): void
    {
        $response = $this->client->graphql(SetInventoryQuantity::DOCUMENT, ['input' => [
            'name' => 'available', 'reason' => 'correction',
            'referenceDocumentUri' => 'tracezilla://inventory-sync/'.$item->sku,
            'quantities' => [['inventoryItemId' => $item->inventoryItemId, 'locationId' => $locationId, 'quantity' => $quantity, 'compareQuantity' => $item->available]],
        ]]);
        $errors = $response['data']['inventorySetQuantities']['userErrors'] ?? [];
        if ($errors !== []) throw new RuntimeException((string) ($errors[0]['message'] ?? 'Shopify rejected the inventory update.'));
    }
}
