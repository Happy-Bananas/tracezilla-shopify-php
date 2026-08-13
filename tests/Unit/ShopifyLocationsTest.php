<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tracezilla\Shopify\Output\LocationTableRenderer;
use Tracezilla\Shopify\Shopify\Mappers\ShopifyLocationMapper;

final class ShopifyLocationsTest extends TestCase
{
    public function test_mapper_creates_a_typed_location(): void
    {
        $location = (new ShopifyLocationMapper())->map($this->location());

        self::assertSame('Development Warehouse', $location->name);
        self::assertTrue($location->isActive);
        self::assertSame('Banana Street 1, 1000 Copenhagen, Denmark', $location->formattedAddress());
        self::assertSame('gid://shopify/Location/1', $location->toArray()['graph_ql_id']);
    }

    public function test_renderer_displays_location_identity_and_status(): void
    {
        $location = (new ShopifyLocationMapper())->map($this->location());
        $output = (new LocationTableRenderer())->render([
            'count' => 1,
            'locations' => [$location->toArray()],
        ]);

        self::assertStringContainsString('Development Warehouse', $output);
        self::assertStringContainsString('Active', $output);
        self::assertStringContainsString('gid://shopify/Location/1', $output);
        self::assertStringContainsString('1 location(s) returned.', $output);
    }

    private function location(): array
    {
        return [
            'id' => 'gid://shopify/Location/1', 'legacyResourceId' => '1',
            'name' => 'Development Warehouse', 'isActive' => true,
            'hasActiveInventory' => true, 'fulfillsOnlineOrders' => true,
            'address' => ['address1' => 'Banana Street 1', 'address2' => null,
                'city' => 'Copenhagen', 'province' => null, 'country' => 'Denmark', 'zip' => '1000'],
        ];
    }
}
