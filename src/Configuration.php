<?php

declare(strict_types=1);

namespace Tracezilla\Shopify;

use RuntimeException;

/**
 * The Shopify settings from .env. The tracezilla settings are read by
 * bifrost-core's TracezillaConfiguration.
 */
final readonly class Configuration
{
    public function __construct(
        public string $shopifyShopUrl,
        public string $shopifyClientId,
        public string $shopifyClientSecret,
        public string $shopifyScope,
        public string $shopifyApiVersion,
        public float $timeout,
        public float $connectTimeout,
    ) {
    }

    public static function fromEnvironment(): self
    {
        return new self(
            self::shopDomain(),
            self::required('SHOPIFY_CLIENT_ID'),
            self::required('SHOPIFY_CLIENT_SECRET'),
            self::required('SHOPIFY_SCOPE'),
            self::required('SHOPIFY_API_VERSION'),
            (float) self::optional('HTTP_TIMEOUT', '30'),
            (float) self::optional('HTTP_CONNECT_TIMEOUT', '10'),
        );
    }

    /**
     * The shop's domain, such as your-store.myshopify.com. An address with
     * https:// or a trailing slash is accepted, because that is what the
     * browser shows.
     */
    private static function shopDomain(): string
    {
        $url = self::required('SHOPIFY_SHOP_URL');
        $domain = (string) preg_replace('#^https?://#i', '', $url);
        $domain = rtrim($domain, '/');
        if (! self::isShopifyDomain($domain)) {
            throw new RuntimeException("SHOPIFY_SHOP_URL must look like your-store.myshopify.com: {$url}");
        }

        return $domain;
    }

    private static function isShopifyDomain(string $domain): bool
    {
        if (str_contains($domain, '/')) {
            return false;
        }

        return str_ends_with($domain, '.myshopify.com');
    }

    private static function required(string $name): string
    {
        $value = (string) getenv($name);
        if ($value === '') {
            throw new RuntimeException("Missing required environment variable: {$name}");
        }

        return $value;
    }

    private static function optional(string $name, string $default): string
    {
        $value = (string) getenv($name);
        if ($value === '') {
            return $default;
        }

        return $value;
    }
}
