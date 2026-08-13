<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Tracezilla;

use RuntimeException;
use Tracezilla\Shopify\Contracts\TracezillaInventoryReader;
use Tracezilla\Shopify\Tracezilla\Mappers\TracezillaInventoryMapper;

final readonly class TracezillaInventoryService implements TracezillaInventoryReader
{
    public function __construct(private TracezillaClient $client, private TracezillaInventoryMapper $mapper) {}

    /** @return list<TracezillaInventory> */
    public function readWarehouse(int $locationNumber): array
    {
        $location = $this->client->get('/location-by-number/'.$locationNumber)['data'] ?? null;
        if (! is_array($location) || empty($location['id'])) throw new RuntimeException('Tracezilla warehouse response is missing an ID.');
        $records = []; $query = ['partner_location' => ['eq' => $location['id']], 'include' => 'sku', 'perPage' => 250]; $seen = [];
        do {
            $response = $this->client->get('/inventory', $query);
            if (! is_array($response['data'] ?? null)) throw new RuntimeException('Tracezilla inventory response is missing data.');
            $records = array_merge($records, $response['data']);
            $next = $response['links']['next_page'] ?? null;
            if (! is_string($next) || $next === '') break;
            $queryString = parse_url($next, PHP_URL_QUERY); $page = [];
            if (! is_string($queryString) || $queryString === '') throw new RuntimeException('Tracezilla inventory pagination returned an invalid next-page URL.');
            parse_str($queryString, $page);
            if ($page === []) throw new RuntimeException('Tracezilla inventory pagination returned no next-page parameters.');
            $query = array_merge($query, $page); $fingerprint = http_build_query($query);
            if (isset($seen[$fingerprint])) throw new RuntimeException('Tracezilla inventory pagination repeated a page.');
            $seen[$fingerprint] = true;
        } while (true);

        return array_map(fn (array $record): TracezillaInventory => $this->mapper->map($record), $records);
    }
}
