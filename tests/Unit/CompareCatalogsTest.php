<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tracezilla\Shopify\Contracts\CatalogReader;
use Tracezilla\Shopify\Shared\CatalogItem;
use Tracezilla\Shopify\Workflows\CompareCatalogs;

final class CompareCatalogsTest extends TestCase
{
    public function test_it_compares_complete_normalized_catalogs(): void
    {
        $shopify = new FakeCatalogReader(['BANANA-001', 'BANANA-002']);
        $tracezilla = new FakeCatalogReader(['BANANA-001', 'BANANA-003']);

        $result = (new CompareCatalogs($shopify, $tracezilla))->run(10);

        self::assertSame(['BANANA-001'], $result->presentInBoth);
        self::assertSame(['BANANA-002'], $result->onlyInShopify);
        self::assertSame(['BANANA-003'], $result->onlyInTracezilla);
        self::assertSame('differences', $result->toArray()['status']);
    }
}

final class FakeCatalogReader implements CatalogReader
{
    public function __construct(private readonly array $skus) {}

    public function read(): array
    {
        return array_map(static fn (string $sku): CatalogItem => new CatalogItem($sku, $sku), $this->skus);
    }
}
