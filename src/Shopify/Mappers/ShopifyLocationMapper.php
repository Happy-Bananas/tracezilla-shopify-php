<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Shopify\Mappers;

use InvalidArgumentException;
use Tracezilla\Shopify\Shopify\ShopifyLocation;

final class ShopifyLocationMapper
{
    public function map(array $location): ShopifyLocation
    {
        $address = $location['address'] ?? [];
        if ($address !== null && ! is_array($address)) {
            throw new InvalidArgumentException('Shopify location address must be an array.');
        }
        $address ??= [];

        return new ShopifyLocation(
            graphQlId: $this->string($location, 'id'),
            legacyId: $this->string($location, 'legacyResourceId'),
            name: $this->string($location, 'name'),
            isActive: $this->boolean($location, 'isActive'),
            hasActiveInventory: $this->boolean($location, 'hasActiveInventory'),
            fulfillsOnlineOrders: $this->boolean($location, 'fulfillsOnlineOrders'),
            address1: $this->optionalString($address, 'address1'),
            address2: $this->optionalString($address, 'address2'),
            city: $this->optionalString($address, 'city'),
            province: $this->optionalString($address, 'province'),
            country: $this->optionalString($address, 'country'),
            zip: $this->optionalString($address, 'zip'),
        );
    }

    private function string(array $values, string $key): string
    {
        if (! is_scalar($values[$key] ?? null) || trim((string) $values[$key]) === '') {
            throw new InvalidArgumentException("Shopify location field [{$key}] is required.");
        }
        return trim((string) $values[$key]);
    }

    private function boolean(array $values, string $key): bool
    {
        if (! is_bool($values[$key] ?? null)) {
            throw new InvalidArgumentException("Shopify location field [{$key}] must be boolean.");
        }
        return $values[$key];
    }

    private function optionalString(array $values, string $key): ?string
    {
        $value = $values[$key] ?? null;
        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }
}
