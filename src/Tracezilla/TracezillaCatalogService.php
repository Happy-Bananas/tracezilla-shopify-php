<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Tracezilla;

use RuntimeException;
use Tracezilla\Shopify\Contracts\CatalogReader;
use Tracezilla\Shopify\Contracts\TracezillaSkuGateway;
use Tracezilla\Shopify\Tracezilla\Mappers\TracezillaSkuMapper;

final readonly class TracezillaCatalogService implements CatalogReader, TracezillaSkuGateway
{
    public function __construct(private TracezillaClient $client, private TracezillaSkuMapper $mapper) {}

    public function read(): array
    {
        $query = [
            'sortBy' => 'sku_code',
            'sortDirection' => 'asc',
            'perPage' => 250,
        ];
        $items = [];
        $visitedPages = [];

        do {
            $payload = $this->client->get('/skus', $query);
            if (! is_array($payload['data'] ?? null)) {
                throw new RuntimeException('tracezilla response is missing SKU data.');
            }

            foreach ($payload['data'] as $sku) {
                if (is_array($sku) && ($item = $this->mapper->map($sku)) !== null) {
                    $items[] = $item;
                }
            }

            $nextPage = $payload['links']['next_page'] ?? null;
            if (! is_string($nextPage) || $nextPage === '') {
                break;
            }
            $queryString = parse_url($nextPage, PHP_URL_QUERY);
            if (! is_string($queryString) || $queryString === '') {
                throw new RuntimeException('tracezilla returned an invalid next-page URL.');
            }
            parse_str($queryString, $nextQuery);
            $query = array_merge($query, $nextQuery);
            $fingerprint = http_build_query($query);
            if (isset($visitedPages[$fingerprint])) {
                throw new RuntimeException('tracezilla returned the same next page repeatedly.');
            }
            $visitedPages[$fingerprint] = true;
        } while (true);

        return $items;
    }

    public function existingSkuCodes(): array
    {
        return array_values(array_unique(array_map(
            static fn ($item): string => $item->sku,
            $this->read(),
        )));
    }

    public function createSku(TracezillaSkuData $sku): array
    {
        return $this->client->post('/skus', $sku->toApiPayload());
    }
}
