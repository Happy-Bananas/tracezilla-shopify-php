<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tracezilla\Shopify\Contracts\CatalogReader;
use Tracezilla\Shopify\Output\ShopifyProductDecisionReportRenderer;
use Tracezilla\Shopify\Shared\CatalogItem;
use Tracezilla\Shopify\Workflows\ReportTracezillaSkusNeedingShopifyDecision;

final class ShopifyProductDecisionReportTest extends TestCase
{
    public function test_it_reports_only_tracezilla_skus_without_a_shopify_variant(): void
    {
        $shopify = new ProductDecisionCatalogReader([
            new CatalogItem('BANANA-001', 'shopify-1'),
        ]);
        $tracezilla = new ProductDecisionCatalogReader([
            new CatalogItem('BANANA-003', 'tz-3', 'Green banana'),
            new CatalogItem('BANANA-001', 'tz-1', 'Yellow banana'),
            new CatalogItem('BANANA-002', 'tz-2', 'Red banana'),
        ]);

        $result = (new ReportTracezillaSkusNeedingShopifyDecision($shopify, $tracezilla))->run(1);

        self::assertSame(['BANANA-002', 'BANANA-003'], array_map(static fn (CatalogItem $item): string => $item->sku, $result->candidates));
        self::assertSame(2, $result->toArray()['candidate_count']);
        self::assertCount(1, $result->toArray()['candidates']);
        self::assertSame('tz-2', $result->toArray()['candidates'][0]['tracezilla_id']);
        self::assertStringContainsString('create a product', (new ShopifyProductDecisionReportRenderer())->render($result));
    }

    public function test_it_reports_when_no_decisions_are_required(): void
    {
        $items = [new CatalogItem('BANANA-001', '1')];
        $result = (new ReportTracezillaSkusNeedingShopifyDecision(
            new ProductDecisionCatalogReader($items),
            new ProductDecisionCatalogReader($items),
        ))->run();

        self::assertSame('no_decisions_required', $result->toArray()['status']);
        self::assertSame([], $result->candidates);
    }
}

final class ProductDecisionCatalogReader implements CatalogReader
{
    /** @param list<CatalogItem> $items */
    public function __construct(private readonly array $items) {}

    public function read(): array
    {
        return $this->items;
    }
}
