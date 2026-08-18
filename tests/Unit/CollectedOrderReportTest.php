<?php

declare(strict_types=1);

namespace Tests\Unit;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tracezilla\Shopify\Contracts\ShopifyOrderReader;
use Tracezilla\Shopify\Output\CollectedOrderReportRenderer;
use Tracezilla\Shopify\Shopify\ShopifyOrderData;
use Tracezilla\Shopify\Shopify\ShopifyOrderLineData;
use Tracezilla\Shopify\Workflows\BuildCollectedOrderReport;
use Tracezilla\Shopify\Workflows\CollectedOrderReportOptions;

final class CollectedOrderReportTest extends TestCase
{
    public function test_it_groups_lines_by_business_date_currency_and_sku(): void
    {
        $reader = new FakeShopifyOrderReader([
            self::order('#1001', '2026-08-17T23:30:00Z', [
                new ShopifyOrderLineData('BANANA-1', 2, '10.25', 'DKK'),
                new ShopifyOrderLineData('BANANA-1', 1, '10.25', 'DKK'),
            ]),
            self::order('#1002', '2026-08-18T00:30:00+02:00', [
                new ShopifyOrderLineData('BANANA-1', 1, '9.50', 'DKK'),
            ]),
        ]);

        $result = (new BuildCollectedOrderReport($reader))->run(
            new CollectedOrderReportOptions(days: 3, timezone: 'Europe/Copenhagen', limit: 10),
            new DateTimeImmutable('2026-08-18T12:00:00+02:00'),
        );

        self::assertSame([
            ['date' => '2026-08-18', 'currency' => 'DKK', 'sku' => 'BANANA-1', 'quantity' => 4, 'revenue' => '40.25'],
        ], $result->lines);
        self::assertSame(2, $result->sourceOrders);
        self::assertSame(2, $result->selectedOrders);
        self::assertSame('2026-08-15T12:00:00+02:00', $reader->createdSince?->format(DATE_ATOM));
    }

    public function test_it_skips_cancelled_truncated_and_invalid_records(): void
    {
        $reader = new FakeShopifyOrderReader([
            self::order('#cancelled', '2026-08-18T08:00:00Z', [], cancelled: true),
            self::order('#truncated', '2026-08-18T08:00:00Z', [], hasMoreLines: true),
            self::order('#valid', '2026-08-18T08:00:00Z', [
                new ShopifyOrderLineData('', 1, '10.00', 'DKK'),
                new ShopifyOrderLineData('BANANA-1', 0, '10.00', 'DKK'),
                new ShopifyOrderLineData('BANANA-2', 2, '5.00', 'DKK'),
            ]),
        ]);

        $result = (new BuildCollectedOrderReport($reader))->run(
            new CollectedOrderReportOptions(limit: 10),
            new DateTimeImmutable('2026-08-18T12:00:00Z'),
        );

        self::assertSame(2, $result->skippedOrders);
        self::assertSame(2, $result->skippedLines);
        self::assertSame([['date' => '2026-08-18', 'currency' => 'DKK', 'sku' => 'BANANA-2', 'quantity' => 2, 'revenue' => '10.00']], $result->lines);
    }

    public function test_it_limits_selected_orders_and_renders_a_summary(): void
    {
        $reader = new FakeShopifyOrderReader([
            self::order('#1001', '2026-08-18T08:00:00Z', [new ShopifyOrderLineData('A', 1, '2.00', 'DKK')]),
            self::order('#1002', '2026-08-18T08:00:00Z', [new ShopifyOrderLineData('B', 1, '3.00', 'DKK')]),
        ]);
        $result = (new BuildCollectedOrderReport($reader))->run(
            new CollectedOrderReportOptions(limit: 1),
            new DateTimeImmutable('2026-08-18T12:00:00Z'),
        );

        self::assertSame(2, $result->sourceOrders);
        self::assertSame(1, $result->selectedOrders);
        $output = (new CollectedOrderReportRenderer())->render($result);
        self::assertStringContainsString('A', $output);
        self::assertStringNotContainsString('B', $output);
        self::assertStringContainsString('Orders returned: 2; selected: 1', $output);
    }

    public function test_it_rejects_invalid_options(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CollectedOrderReportOptions(timezone: 'Not/A_Timezone');
    }

    /** @param list<ShopifyOrderLineData> $lines */
    private static function order(
        string $name,
        string $createdAt,
        array $lines,
        bool $cancelled = false,
        bool $hasMoreLines = false,
    ): ShopifyOrderData {
        return new ShopifyOrderData(
            graphQlId: "gid://shopify/Order/{$name}",
            legacyId: ltrim($name, '#'),
            name: $name,
            createdAt: $createdAt,
            cancelledAt: $cancelled ? $createdAt : null,
            currency: 'DKK',
            lines: $lines,
            hasMoreLines: $hasMoreLines,
        );
    }
}

final class FakeShopifyOrderReader implements ShopifyOrderReader
{
    public ?DateTimeImmutable $createdSince = null;

    /** @param list<ShopifyOrderData> $orders */
    public function __construct(private readonly array $orders) {}

    public function readCreatedSince(DateTimeImmutable $createdSince): array
    {
        $this->createdSince = $createdSince;
        return $this->orders;
    }
}
