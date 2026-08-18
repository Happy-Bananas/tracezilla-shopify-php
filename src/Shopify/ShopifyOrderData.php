<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Shopify;

use InvalidArgumentException;

final readonly class ShopifyOrderData
{
    /** @param list<ShopifyOrderLineData> $lines */
    public function __construct(
        public string $graphQlId,
        public string $legacyId,
        public string $name,
        public string $createdAt,
        public ?string $cancelledAt,
        public string $currency,
        public array $lines,
        public bool $hasMoreLines,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $note = null,
        public ?string $purchaseOrderNumber = null,
        public ?array $shippingAddress = null,
    ) {}

    public static function fromApiResponse(array $order): self
    {
        foreach (['id', 'legacyResourceId', 'name', 'createdAt', 'currencyCode'] as $key) {
            if (! isset($order[$key]) || ! is_scalar($order[$key]) || (string) $order[$key] === '') {
                throw new InvalidArgumentException("Shopify order field [{$key}] is required.");
            }
        }

        return new self(
            graphQlId: (string) $order['id'],
            legacyId: (string) $order['legacyResourceId'],
            name: (string) $order['name'],
            createdAt: (string) $order['createdAt'],
            cancelledAt: self::nullableString($order['cancelledAt'] ?? null),
            currency: (string) $order['currencyCode'],
            lines: array_map(
                static fn (array $line): ShopifyOrderLineData => ShopifyOrderLineData::fromApiResponse($line),
                $order['lineItems']['nodes'] ?? [],
            ),
            hasMoreLines: (bool) ($order['lineItems']['pageInfo']['hasNextPage'] ?? false),
            email: self::nullableString($order['email'] ?? null),
            phone: self::nullableString($order['phone'] ?? null),
            note: self::nullableString($order['note'] ?? null),
            purchaseOrderNumber: self::nullableString($order['poNumber'] ?? null),
            shippingAddress: is_array($order['shippingAddress'] ?? null)
                ? $order['shippingAddress']
                : null,
        );
    }

    public function isCancelled(): bool
    {
        return $this->cancelledAt !== null;
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
