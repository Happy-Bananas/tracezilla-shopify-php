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
        $orders = [];
        $after = null;
        $filter = "created_at:>='{$createdSince->format(DATE_ATOM)}'";

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
