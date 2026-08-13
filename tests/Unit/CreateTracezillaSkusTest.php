<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tracezilla\Shopify\Contracts\ShopifyVariantReader;
use Tracezilla\Shopify\Contracts\TracezillaSkuGateway;
use Tracezilla\Shopify\Shopify\ShopifyVariantData;
use Tracezilla\Shopify\Tracezilla\Mappers\ShopifyVariantToTracezillaSkuMapper;
use Tracezilla\Shopify\Tracezilla\TracezillaSkuData;
use Tracezilla\Shopify\Workflows\CreateTracezillaSkus;

final class CreateTracezillaSkusTest extends TestCase
{
    public function test_dry_run_reports_decisions_without_writing(): void
    {
        $shopify = new FakeVariantReader([
            new ShopifyVariantData('variant/1', 'EXISTING'),
            new ShopifyVariantData('variant/2', 'NEW'),
            new ShopifyVariantData('variant/3', 'NEW'),
            new ShopifyVariantData('variant/4', null),
        ]);
        $tracezilla = new FakeSkuGateway(['EXISTING']);

        $result = $this->workflow($shopify, $tracezilla)->run(dryRun: true, limit: 10);
        $data = $result->toArray();

        self::assertSame(0, $tracezilla->writeCount);
        self::assertSame(1, $data['summary']['would_create_count']);
        self::assertSame(2, $data['summary']['skipped_count']);
        self::assertSame(1, $data['summary']['invalid_count']);
        self::assertFalse($result->hasFailures());
    }

    public function test_execution_respects_limit_and_creates_missing_skus(): void
    {
        $shopify = new FakeVariantReader([
            new ShopifyVariantData('variant/1', 'FIRST'),
            new ShopifyVariantData('variant/2', 'SECOND'),
        ]);
        $tracezilla = new FakeSkuGateway([]);

        $result = $this->workflow($shopify, $tracezilla)->run(dryRun: false, limit: 1);

        self::assertSame(['FIRST'], $tracezilla->createdCodes);
        self::assertSame(1, $result->toArray()['summary']['created_count']);
        self::assertSame(1, $result->toArray()['summary']['selected_count']);
    }

    public function test_one_failed_write_does_not_stop_later_records(): void
    {
        $shopify = new FakeVariantReader([
            new ShopifyVariantData('variant/1', 'FAIL'),
            new ShopifyVariantData('variant/2', 'SUCCESS'),
        ]);
        $tracezilla = new FakeSkuGateway([], ['FAIL']);

        $result = $this->workflow($shopify, $tracezilla)->run(dryRun: false, limit: 10);

        self::assertTrue($result->hasFailures());
        self::assertSame(1, $result->toArray()['summary']['failed_count']);
        self::assertSame(1, $result->toArray()['summary']['created_count']);
        self::assertSame(['SUCCESS'], $tracezilla->createdCodes);
    }

    private function workflow(ShopifyVariantReader $shopify, TracezillaSkuGateway $tracezilla): CreateTracezillaSkus
    {
        return new CreateTracezillaSkus($shopify, $tracezilla, new ShopifyVariantToTracezillaSkuMapper());
    }
}

final class FakeVariantReader implements ShopifyVariantReader
{
    public function __construct(private readonly array $variants) {}

    public function readVariants(): array
    {
        return $this->variants;
    }
}

final class FakeSkuGateway implements TracezillaSkuGateway
{
    public int $writeCount = 0;
    public array $createdCodes = [];

    public function __construct(
        private readonly array $existing,
        private readonly array $failures = [],
    ) {}

    public function existingSkuCodes(): array
    {
        return $this->existing;
    }

    public function createSku(TracezillaSkuData $sku): array
    {
        $this->writeCount++;
        if (in_array($sku->skuCode, $this->failures, true)) {
            throw new RuntimeException('Test failure');
        }
        $this->createdCodes[] = $sku->skuCode;

        return ['data' => $sku->toApiPayload()];
    }
}
