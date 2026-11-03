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
        public string $tracezillaClientId,
        public string $tracezillaClientSecret,
        public string $tracezillaTokenFile,
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
            tracezillaClientId: self::required('TRACEZILLA_CLIENT_ID'),
            tracezillaClientSecret: self::required('TRACEZILLA_CLIENT_SECRET'),
            tracezillaTokenFile: self::tokenFile(),
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

    /**
     * The OAuth tokens are stored in the runtime directory unless
     * TRACEZILLA_TOKEN_FILE names another file.
     */
    private static function tokenFile(): string
    {
        $project = dirname(__DIR__);
        $configured = trim((string) ($_ENV['TRACEZILLA_TOKEN_FILE'] ?? getenv('TRACEZILLA_TOKEN_FILE')));
        if ($configured === '') {
            return RuntimeDirectory::resolve($project).'/tracezilla-token.json';
        }

        return str_starts_with($configured, '/') ? $configured : $project.'/'.$configured;
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
