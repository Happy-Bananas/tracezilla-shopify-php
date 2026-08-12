<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tracezilla\Shopify\Output\TableRenderer;
use Tracezilla\Shopify\Workflows\CatalogComparisonResult;

final class TableRendererTest extends TestCase
{
    public function test_it_renders_each_comparison_category_and_summary(): void
    {
        $result = new CatalogComparisonResult(
            presentInBoth: ['BANANA-001'],
            onlyInShopify: ['BANANA-002'],
            onlyInTracezilla: ['BANANA-003'],
            limit: 10,
        );

        $output = (new TableRenderer())->render($result);

        self::assertStringContainsString('BANANA-001', $output);
        self::assertStringContainsString('Missing in tracezilla', $output);
        self::assertStringContainsString('Missing in Shopify', $output);
        self::assertStringContainsString('Matched: 1', $output);
    }
}
