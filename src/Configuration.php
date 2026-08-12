<?php

declare(strict_types=1);

namespace Tracezilla\Shopify;

use InvalidArgumentException;

final readonly class Configuration
{
    public function __construct(
        public string $shopifyShopUrl,
        public string $shopifyClientId,
        public string $shopifyClientSecret,
        public string $shopifyScope,
        public string $shopifyApiVersion,
        public string $tracezillaBaseUrl,
        public string $tracezillaTeamSlug,
        public string $tracezillaApiKey,
        public int $timeout,
        public int $connectTimeout,
    ) {}

    public static function fromEnvironment(): self
    {
        return new self(
            shopifyShopUrl: self::shopDomain(self::required('SHOPIFY_SHOP_URL')),
            shopifyClientId: self::required('SHOPIFY_CLIENT_ID'),
            shopifyClientSecret: self::required('SHOPIFY_CLIENT_SECRET'),
            shopifyScope: self::required('SHOPIFY_SCOPE'),
            shopifyApiVersion: self::required('SHOPIFY_API_VERSION'),
            tracezillaBaseUrl: rtrim(self::required('TRACEZILLA_BASE_URL'), '/'),
            tracezillaTeamSlug: self::required('TRACEZILLA_TEAM_SLUG'),
            tracezillaApiKey: self::required('TRACEZILLA_API_KEY'),
            timeout: self::positiveInteger('HTTP_TIMEOUT'),
            connectTimeout: self::positiveInteger('HTTP_CONNECT_TIMEOUT'),
        );
    }

    private static function required(string $key): string
    {
        $value = $_ENV[$key] ?? getenv($key);

        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("Missing required configuration: {$key}");
        }

        return trim($value);
    }

    private static function positiveInteger(string $key): int
    {
        $value = filter_var(self::required($key), FILTER_VALIDATE_INT);

        if ($value === false || $value < 1) {
            throw new InvalidArgumentException("{$key} must be a positive integer.");
        }

        return $value;
    }

    private static function shopDomain(string $value): string
    {
        $domain = rtrim((string) preg_replace('#^https?://#i', '', $value), '/');

        if (! str_ends_with($domain, '.myshopify.com') || str_contains($domain, '/')) {
            throw new InvalidArgumentException(
                'SHOPIFY_SHOP_URL must look like your-store.myshopify.com.'
            );
        }

        return $domain;
    }
}
