<?php

declare(strict_types=1);

namespace Tests\Unit;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tracezilla\Shopify\Contracts\ShopifyOrderReader;
use Tracezilla\Shopify\Contracts\TracezillaSalesOrderGateway;
use Tracezilla\Shopify\Output\IndividualOrderImportRenderer;
use Tracezilla\Shopify\Shopify\ShopifyOrderData;
use Tracezilla\Shopify\Shopify\ShopifyOrderLineData;
use Tracezilla\Shopify\Tracezilla\Mappers\ShopifyOrderToTracezillaSalesOrderMapper;
use Tracezilla\Shopify\Tracezilla\TracezillaOrderContextData;
use Tracezilla\Shopify\Tracezilla\TracezillaSalesOrderData;
use Tracezilla\Shopify\Workflows\ImportIndividualOrders;
use Tracezilla\Shopify\Workflows\ImportIndividualOrdersOptions;

final class IndividualOrderImportTest extends TestCase
{
    public function test_dry_run_reports_decisions_without_writing(): void
    {
        $reader = new FakeIndividualOrderReader([
            self::order('1001'),
            self::order('1002', cancelled: true),
            self::order('1003', hasMoreLines: true),
            self::order('1004', lines: [new ShopifyOrderLineData('', 1, '10.00', 'DKK')]),
            self::order('1005'),
            self::order('1005'),
        ]);
        $gateway = new FakeSalesOrderGateway(['SHP1001' => true]);

        $result = $this->workflow($reader, $gateway)->run(
            new ImportIndividualOrdersOptions('Webshop customer', 2, dryRun: true, days: 3, limit: 10),
            new DateTimeImmutable('2026-08-18T12:00:00Z'),
        );
        $summary = $result->toArray()['summary'];

        self::assertSame(0, $gateway->writeCount);
        self::assertSame(1, $summary['would_create_count']);
        self::assertSame(4, $summary['skipped_count']);
        self::assertSame(1, $summary['invalid_count']);
        self::assertTrue($result->hasFailures());
        self::assertSame('2026-08-15T12:00:00+00:00', $reader->createdSince?->format(DATE_ATOM));
    }

    public function test_execution_creates_orders_and_continues_after_a_rejected_write(): void
    {
        $reader = new FakeIndividualOrderReader([
            self::order('1001'),
            self::order('1002'),
        ]);
        $gateway = new FakeSalesOrderGateway([], ['SHP1001']);

        $result = $this->workflow($reader, $gateway)->run(
            new ImportIndividualOrdersOptions('Webshop customer', 2, dryRun: false, limit: 10),
            new DateTimeImmutable('2026-08-18T12:00:00Z'),
        );
        $summary = $result->toArray()['summary'];

        self::assertSame(2, $gateway->writeCount);
        self::assertSame(['SHP1002'], $gateway->createdReferences);
        self::assertSame(1, $summary['created_count']);
        self::assertSame(1, $summary['failed_count']);
        self::assertTrue($result->hasFailures());
    }

    public function test_empty_source_does_not_resolve_tracezilla_context(): void
    {
        $gateway = new FakeSalesOrderGateway([]);

        $result = $this->workflow(new FakeIndividualOrderReader([]), $gateway)->run(
            new ImportIndividualOrdersOptions('Webshop customer', 2),
            new DateTimeImmutable('2026-08-18T12:00:00Z'),
        );

        self::assertSame(0, $gateway->contextReads);
        self::assertSame(0, $result->toArray()['summary']['processed_count']);
        self::assertFalse($result->hasFailures());
    }

    public function test_limit_bounds_selected_orders(): void
    {
        $reader = new FakeIndividualOrderReader([self::order('1001'), self::order('1002')]);
        $gateway = new FakeSalesOrderGateway([]);

        $result = $this->workflow($reader, $gateway)->run(
            new ImportIndividualOrdersOptions('Webshop customer', 2, limit: 1),
            new DateTimeImmutable('2026-08-18T12:00:00Z'),
        );

        self::assertSame(2, $result->toArray()['summary']['source_count']);
        self::assertSame(1, $result->toArray()['summary']['selected_count']);
        self::assertSame(1, $result->toArray()['summary']['would_create_count']);
    }

    public function test_renderer_includes_items_and_summary(): void
    {
        $result = $this->workflow(
            new FakeIndividualOrderReader([self::order('1001')]),
            new FakeSalesOrderGateway([]),
        )->run(
            new ImportIndividualOrdersOptions('Webshop customer', 2),
            new DateTimeImmutable('2026-08-18T12:00:00Z'),
        );

        $output = (new IndividualOrderImportRenderer())->render($result);
        self::assertStringContainsString('#1001', $output);
        self::assertStringContainsString('SHP1001', $output);
        self::assertStringContainsString('would create: 1', $output);
    }

    public function test_options_reject_unsafe_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ImportIndividualOrdersOptions('', 0, days: 0, limit: 0);
    }

    private function workflow(
        ShopifyOrderReader $reader,
        TracezillaSalesOrderGateway $gateway,
    ): ImportIndividualOrders {
        return new ImportIndividualOrders(
            $reader,
            $gateway,
            new ShopifyOrderToTracezillaSalesOrderMapper(),
        );
    }

    /** @param null|list<ShopifyOrderLineData> $lines */
    private static function order(
        string $legacyId,
        bool $cancelled = false,
        bool $hasMoreLines = false,
        ?array $lines = null,
    ): ShopifyOrderData {
        return new ShopifyOrderData(
            graphQlId: "gid://shopify/Order/{$legacyId}",
            legacyId: $legacyId,
            name: "#{$legacyId}",
            createdAt: '2026-08-18T08:00:00Z',
            cancelledAt: $cancelled ? '2026-08-18T09:00:00Z' : null,
            currency: 'DKK',
            lines: $lines ?? [new ShopifyOrderLineData('BANANA-1', 2, '10.00', 'DKK')],
            hasMoreLines: $hasMoreLines,
            email: 'buyer@example.test',
            phone: '+4512345678',
            shippingAddress: [
                'name' => 'Ada Buyer',
                'company' => '',
                'address1' => 'Test Street 1',
                'zip' => '1000',
                'city' => 'Copenhagen',
                'countryCodeV2' => 'DK',
            ],
        );
    }
}

final class FakeIndividualOrderReader implements ShopifyOrderReader
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

final class FakeSalesOrderGateway implements TracezillaSalesOrderGateway
{
    public int $contextReads = 0;
    public int $writeCount = 0;
    public array $createdReferences = [];

    /** @param array<string, true> $existing */
    public function __construct(
        private readonly array $existing,
        private readonly array $rejectedReferences = [],
    ) {}

    public function getContext(string $customerName, int $warehouseLocationNumber): TracezillaOrderContextData
    {
        $this->contextReads++;
        return new TracezillaOrderContextData('customer', 'customer-location', 7, 'warehouse', 'warehouse-location');
    }

    public function existingExternalReferences(string $prefix): array
    {
        return $this->existing;
    }

    public function createSalesOrder(TracezillaSalesOrderData $order): array
    {
        $this->writeCount++;
        if (in_array($order->externalReference, $this->rejectedReferences, true)) {
            throw new RuntimeException('Rejected for test');
        }
        $this->createdReferences[] = $order->externalReference;
        return ['data' => ['ext_ref' => $order->externalReference]];
    }
}
