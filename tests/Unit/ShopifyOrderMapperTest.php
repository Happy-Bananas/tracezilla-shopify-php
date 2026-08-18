<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tracezilla\Shopify\Shopify\ShopifyOrderData;
use Tracezilla\Shopify\Shopify\ShopifyOrderLineData;
use Tracezilla\Shopify\Tracezilla\Mappers\ShopifyOrderToTracezillaSalesOrderMapper;
use Tracezilla\Shopify\Tracezilla\TracezillaOrderContextData;

final class ShopifyOrderMapperTest extends TestCase
{
    public function test_it_maps_header_address_and_summarized_lines(): void
    {
        $order = $this->order([
            new ShopifyOrderLineData('BANANA-1', 2, '10.00', 'DKK'),
            new ShopifyOrderLineData('BANANA-1', 1, '13.00', 'DKK'),
        ]);

        $mapped = (new ShopifyOrderToTracezillaSalesOrderMapper())->map($order, $this->context());
        $payload = $mapped->toApiPayload();

        self::assertSame('SHP1001', $mapped->externalReference);
        self::assertSame('SHP1001', $payload['order_header']['ext_ref']);
        self::assertSame('Ada Buyer', $payload['order_header']['partners']['deliver_to']['location']['name']);
        self::assertSame(3, $payload['outbound_skus'][0]['quantity']);
        self::assertSame(11.0, $payload['outbound_skus'][0]['unit_price']);
        self::assertSame('none', $payload['post_save_action']);
    }

    public function test_it_rejects_an_unsupported_currency(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('only supports DKK');

        (new ShopifyOrderToTracezillaSalesOrderMapper())->map(
            $this->order([new ShopifyOrderLineData('BANANA-1', 1, '10.00', 'EUR')], currency: 'EUR'),
            $this->context(),
        );
    }

    public function test_it_rejects_one_invalid_line_instead_of_creating_a_partial_order(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('without a usable SKU');

        (new ShopifyOrderToTracezillaSalesOrderMapper())->map(
            $this->order([
                new ShopifyOrderLineData('BANANA-1', 1, '10.00', 'DKK'),
                new ShopifyOrderLineData('', 1, '10.00', 'DKK'),
            ]),
            $this->context(),
        );
    }

    public function test_it_rejects_an_incomplete_shipping_address(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('field [zip] is required');

        $order = $this->order([new ShopifyOrderLineData('BANANA-1', 1, '10.00', 'DKK')]);
        $order = new ShopifyOrderData(
            graphQlId: $order->graphQlId,
            legacyId: $order->legacyId,
            name: $order->name,
            createdAt: $order->createdAt,
            cancelledAt: null,
            currency: $order->currency,
            lines: $order->lines,
            hasMoreLines: false,
            shippingAddress: ['name' => 'Ada', 'address1' => 'Street', 'city' => 'Copenhagen', 'countryCodeV2' => 'DK'],
        );

        (new ShopifyOrderToTracezillaSalesOrderMapper())->map($order, $this->context());
    }

    /** @param list<ShopifyOrderLineData> $lines */
    private function order(array $lines, string $currency = 'DKK'): ShopifyOrderData
    {
        return new ShopifyOrderData(
            graphQlId: 'gid://shopify/Order/1001',
            legacyId: '1001',
            name: '#1001',
            createdAt: '2026-08-18T08:00:00Z',
            cancelledAt: null,
            currency: $currency,
            lines: $lines,
            hasMoreLines: false,
            email: 'buyer@example.test',
            shippingAddress: [
                'name' => 'Ada Buyer',
                'address1' => 'Test Street 1',
                'zip' => '1000',
                'city' => 'Copenhagen',
                'countryCodeV2' => 'DK',
            ],
        );
    }

    private function context(): TracezillaOrderContextData
    {
        return new TracezillaOrderContextData('customer', 'customer-location', 7, 'warehouse', 'warehouse-location');
    }
}
