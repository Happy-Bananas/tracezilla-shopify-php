<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Shopify;

use RuntimeException;
use Tracezilla\Shopify\Shopify\Mappers\ShopifyLocationMapper;
use Tracezilla\Shopify\Shopify\Queries\GetLocations;

final readonly class ShopifyLocationService
{
    public function __construct(private ShopifyClient $client, private ShopifyLocationMapper $mapper) {}

    /** @return list<ShopifyLocation> */
    public function read(): array
    {
        $locations = [];
        $after = null;
        $seenCursors = [];

        do {
            $payload = $this->client->graphql(GetLocations::DOCUMENT, ['first' => 250, 'after' => $after]);
            $connection = $payload['data']['locations'] ?? null;
            if (! is_array($connection) || ! is_array($connection['nodes'] ?? null) || ! is_array($connection['pageInfo'] ?? null)) {
                throw new RuntimeException('Shopify response is missing locations.');
            }
            foreach ($connection['nodes'] as $location) {
                if (! is_array($location)) {
                    throw new RuntimeException('Shopify returned an invalid location.');
                }
                $locations[] = $this->mapper->map($location);
            }

            $hasNextPage = $connection['pageInfo']['hasNextPage'] ?? null;
            $endCursor = $connection['pageInfo']['endCursor'] ?? null;
            if (! is_bool($hasNextPage)) {
                throw new RuntimeException('Shopify returned invalid location pagination data.');
            }
            if (! $hasNextPage) {
                break;
            }
            if (! is_string($endCursor) || $endCursor === '' || isset($seenCursors[$endCursor])) {
                throw new RuntimeException('Shopify returned an invalid or repeated location cursor.');
            }
            $seenCursors[$endCursor] = true;
            $after = $endCursor;
        } while (true);

        return $locations;
    }
}
