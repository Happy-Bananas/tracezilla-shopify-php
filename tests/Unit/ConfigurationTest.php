<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tracezilla\Shopify\Configuration;

final class ConfigurationTest extends TestCase
{
    private const VARIABLES = [
        'SHOPIFY_SHOP_URL',
        'SHOPIFY_CLIENT_ID',
        'SHOPIFY_CLIENT_SECRET',
        'SHOPIFY_SCOPE',
        'SHOPIFY_API_VERSION',
        'HTTP_TIMEOUT',
        'HTTP_CONNECT_TIMEOUT',
    ];

    protected function tearDown(): void
    {
        foreach (self::VARIABLES as $name) {
            putenv($name);
        }
    }

    public function test_it_loads_and_normalizes_environment_configuration(): void
    {
        $this->putCompleteShopifySettings();
        putenv('SHOPIFY_SHOP_URL=https://your-store.myshopify.com/');
        putenv('HTTP_TIMEOUT=12');
        putenv('HTTP_CONNECT_TIMEOUT=4');

        $configuration = Configuration::fromEnvironment();

        self::assertSame('your-store.myshopify.com', $configuration->shopifyShopUrl);
        self::assertSame('client-id', $configuration->shopifyClientId);
        self::assertSame('client-secret', $configuration->shopifyClientSecret);
        self::assertSame('read_products', $configuration->shopifyScope);
        self::assertSame('2025-10', $configuration->shopifyApiVersion);
        self::assertSame(12.0, $configuration->timeout);
        self::assertSame(4.0, $configuration->connectTimeout);
    }

    public function test_timeouts_have_defaults(): void
    {
        $this->putCompleteShopifySettings();
        putenv('HTTP_TIMEOUT');
        putenv('HTTP_CONNECT_TIMEOUT');

        $configuration = Configuration::fromEnvironment();

        self::assertSame(30.0, $configuration->timeout);
        self::assertSame(10.0, $configuration->connectTimeout);
    }

    public function test_the_shop_must_be_a_myshopify_domain(): void
    {
        $this->putCompleteShopifySettings();
        putenv('SHOPIFY_SHOP_URL=https://shop.example/admin');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SHOPIFY_SHOP_URL must look like your-store.myshopify.com');

        Configuration::fromEnvironment();
    }

    public function test_it_rejects_missing_credentials(): void
    {
        $this->putCompleteShopifySettings();
        putenv('SHOPIFY_CLIENT_ID');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing required environment variable: SHOPIFY_CLIENT_ID');

        Configuration::fromEnvironment();
    }

    private function putCompleteShopifySettings(): void
    {
        putenv('SHOPIFY_SHOP_URL=your-store.myshopify.com');
        putenv('SHOPIFY_CLIENT_ID=client-id');
        putenv('SHOPIFY_CLIENT_SECRET=client-secret');
        putenv('SHOPIFY_SCOPE=read_products');
        putenv('SHOPIFY_API_VERSION=2025-10');
    }
}
