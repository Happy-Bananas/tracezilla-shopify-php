<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Shopify;

use InvalidArgumentException;

final readonly class ShopifyLocation
{
    public function __construct(
        public string $graphQlId,
        public string $legacyId,
        public string $name,
        public bool $isActive,
        public bool $hasActiveInventory,
        public bool $fulfillsOnlineOrders,
        public ?string $address1 = null,
        public ?string $address2 = null,
        public ?string $city = null,
        public ?string $province = null,
        public ?string $country = null,
        public ?string $zip = null,
    ) {
        if (trim($graphQlId) === '' || trim($name) === '') {
            throw new InvalidArgumentException('A Shopify location requires an ID and name.');
        }
    }

    public function formattedAddress(): string
    {
        return implode(', ', array_filter([
            $this->address1,
            $this->address2,
            trim(implode(' ', array_filter([$this->zip, $this->city]))),
            $this->province,
            $this->country,
        ], static fn (?string $value): bool => $value !== null && trim($value) !== ''));
    }

    public function toArray(): array
    {
        return [
            'graph_ql_id' => $this->graphQlId,
            'legacy_id' => $this->legacyId,
            'name' => $this->name,
            'is_active' => $this->isActive,
            'has_active_inventory' => $this->hasActiveInventory,
            'fulfills_online_orders' => $this->fulfillsOnlineOrders,
            'address' => [
                'address1' => $this->address1, 'address2' => $this->address2,
                'city' => $this->city, 'province' => $this->province,
                'country' => $this->country, 'zip' => $this->zip,
            ],
        ];
    }
}
