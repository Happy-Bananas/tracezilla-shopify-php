<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Tracezilla;

use RuntimeException;
use Tracezilla\Shopify\Contracts\TracezillaSalesOrderGateway;

final readonly class TracezillaSalesOrderService implements TracezillaSalesOrderGateway
{
    public function __construct(private TracezillaClient $client) {}

    public function getContext(string $customerName, int $warehouseLocationNumber): TracezillaOrderContextData
    {
        $payload = $this->client->get('/partners', [
            'keyword' => ['ct' => $customerName],
            'role' => ['eq' => 'customer'],
            'include' => 'locations',
            'perPage' => 50,
        ]);
        $partners = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $customer = null;

        foreach ($partners as $partner) {
            if (is_array($partner) && strcasecmp(trim((string) ($partner['name'] ?? '')), $customerName) === 0) {
                $customer = $partner;
                break;
            }
        }

        if (! is_array($customer) || empty($customer['id'])) {
            throw new RuntimeException("tracezilla customer [{$customerName}] could not be resolved.");
        }

        $customerLocation = null;
        foreach ($customer['locations'] ?? [] as $location) {
            if (is_array($location) && (bool) ($location['is_primary'] ?? false)) {
                $customerLocation = $location;
                break;
            }
        }
        $customerLocation ??= is_array($customer['locations'][0] ?? null)
            ? $customer['locations'][0]
            : null;

        if (! is_array($customerLocation) || empty($customerLocation['id'])) {
            throw new RuntimeException("tracezilla customer [{$customerName}] has no primary location.");
        }
        if (! is_numeric($customer['owned_by_id'] ?? null)) {
            throw new RuntimeException(
                "tracezilla customer [{$customerName}] has no owner. Assign one before importing orders."
            );
        }

        $warehousePayload = $this->client->get('/location-by-number/'.$warehouseLocationNumber);
        $warehouse = $warehousePayload['data'] ?? null;
        if (! is_array($warehouse) || empty($warehouse['id']) || empty($warehouse['partner_id'])) {
            throw new RuntimeException(
                "tracezilla warehouse location [{$warehouseLocationNumber}] could not be resolved."
            );
        }

        return new TracezillaOrderContextData(
            customerPartnerId: (string) $customer['id'],
            customerLocationId: (string) $customerLocation['id'],
            ownerId: (int) $customer['owned_by_id'],
            warehousePartnerId: (string) $warehouse['partner_id'],
            warehouseLocationId: (string) $warehouse['id'],
        );
    }

    public function existingExternalReferences(string $prefix): array
    {
        $references = [];
        $query = ['ext_ref' => ['ct' => $prefix], 'perPage' => 250];
        $visitedPages = [];

        do {
            $payload = $this->client->get('/orders/sales', $query);
            if (! is_array($payload['data'] ?? null)) {
                throw new RuntimeException('tracezilla response is missing sales-order data.');
            }

            foreach ($payload['data'] as $order) {
                $reference = is_array($order) ? ($order['ext_ref'] ?? null) : null;
                if (is_string($reference) && $reference !== '') {
                    $references[$reference] = true;
                }
            }

            $nextPage = $payload['links']['next_page'] ?? null;
            if (! is_string($nextPage) || $nextPage === '') {
                break;
            }
            $queryString = parse_url($nextPage, PHP_URL_QUERY);
            if (! is_string($queryString) || $queryString === '') {
                throw new RuntimeException('tracezilla returned an invalid sales-order next-page URL.');
            }
            parse_str($queryString, $nextQuery);
            $query = array_merge($query, $nextQuery);
            $fingerprint = http_build_query($query);
            if (isset($visitedPages[$fingerprint])) {
                throw new RuntimeException('tracezilla returned the same sales-order page repeatedly.');
            }
            $visitedPages[$fingerprint] = true;
        } while (true);

        return $references;
    }

    public function createSalesOrder(TracezillaSalesOrderData $order): array
    {
        return $this->client->put('/orders/sales', $order->toApiPayload());
    }
}
