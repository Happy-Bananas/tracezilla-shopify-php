<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Shopify;

use DateTimeImmutable;
use Tracezilla\Shopify\Contracts\ShopifyOrderReader;
use Tracezilla\Shopify\Shopify\Queries\GetOrders;

final readonly class ShopifyOrderService implements ShopifyOrderReader
{
    public function __construct(private ShopifyClient $client) {}

    public function readCreatedSince(DateTimeImmutable $createdSince): array
    {
        return $this->readMatching("created_at:>='{$createdSince->format(DATE_ATOM)}'");
    }

    public function readByLegacyIds(array $legacyIds): array
    {
        if ($legacyIds === []) {
            return [];
        }

        // Shopify's order search matches the numeric ID with id:<number>.
        $terms = [];
        foreach ($legacyIds as $legacyId) {
            $terms[] = 'id:'.$legacyId;
        }

        return $this->readMatching(implode(' OR ', $terms));
    }

    /**
     * Every order that matches a Shopify search filter, 100 at a time.
     *
     * @return list<ShopifyOrderData>
     */
    private function readMatching(string $filter): array
    {
        $orders = [];
        $after = null;

        do {
            $response = $this->client->graphql(GetOrders::QUERY, [
                'first' => 100,
                'after' => $after,
                'query' => $filter,
            ]);
            $connection = $response['data']['orders'] ?? [];

            foreach ($connection['nodes'] ?? [] as $order) {
                $orders[] = ShopifyOrderData::fromApiResponse($order);
            }

            $after = $connection['pageInfo']['endCursor'] ?? null;
        } while ((bool) ($connection['pageInfo']['hasNextPage'] ?? false));

        return $orders;
    }
}
