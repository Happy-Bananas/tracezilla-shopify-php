<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tracezilla\Shopify\Shopify\Mappers\ShopifyVariantMapper;
use Tracezilla\Shopify\Shopify\ShopifyVariantData;
use Tracezilla\Shopify\Tracezilla\Mappers\ShopifyVariantToTracezillaSkuMapper;
use Tracezilla\Shopify\Tracezilla\Mappers\TracezillaSkuMapper;

final class MappersTest extends TestCase
{
    public function test_shopify_mapper_normalizes_a_variant(): void
    {
        $item = (new ShopifyVariantMapper())->map(ShopifyVariantData::fromApiResponse(
            ['id' => 'gid://variant/1', 'sku' => ' BANANA-001 ', 'displayName' => 'Banana'],
        ));
        self::assertSame('BANANA-001', $item?->sku);
        self::assertSame('gid://variant/1', $item?->sourceId);
    }

    public function test_shopify_mapper_skips_a_blank_sku(): void
    {
        self::assertNull((new ShopifyVariantMapper())->map(
            ShopifyVariantData::fromApiResponse(['id' => 'gid://variant/1', 'sku' => ' ']),
        ));
    }

    public function test_tracezilla_mapper_normalizes_an_sku(): void
    {
        $item = (new TracezillaSkuMapper())->map(['id' => 42, 'sku_code' => ' BANANA-001 ', 'name' => 'Banana']);
        self::assertSame('BANANA-001', $item?->sku);
        self::assertSame('42', $item?->sourceId);
    }

    public function test_sku_creation_mapper_keeps_business_assumptions_visible(): void
    {
        $sku = (new ShopifyVariantToTracezillaSkuMapper())->map(new ShopifyVariantData(
            id: 'gid://variant/1',
            sku: 'BANANA-001',
            name: 'Banana box',
        ));

        self::assertSame([
            'sku_code' => 'BANANA-001',
            'global_name' => 'BANANA-001',
            'weight_factor_net' => 1.0,
            'weight_factor_gross' => 1.0,
            'unit_of_measure' => 'pcs',
            'lot_unit' => 'colli',
            'default_uom_conversion' => 1.0,
        ], $sku->toApiPayload());
    }
}
