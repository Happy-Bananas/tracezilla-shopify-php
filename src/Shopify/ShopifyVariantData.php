<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Shopify;

use InvalidArgumentException;

final readonly class ShopifyVariantData
{
    public function __construct(
        public string $id,
        public ?string $sku,
        public ?string $name = null,
    ) {
        if (trim($id) === '') {
            throw new InvalidArgumentException('A Shopify variant must have an ID.');
        }
    }

    public static function fromApiResponse(array $variant): self
    {
        if (! is_string($variant['id'] ?? null) || trim($variant['id']) === '') {
            throw new InvalidArgumentException('A Shopify variant is missing its ID.');
        }

        return new self(
            id: $variant['id'],
            sku: self::optionalString($variant['sku'] ?? null),
            name: self::optionalString($variant['displayName'] ?? null),
        );
    }

    private static function optionalString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
