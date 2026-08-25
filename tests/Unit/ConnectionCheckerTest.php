<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tracezilla\Shopify\Connection\ConnectionChecker;

final class ConnectionCheckerTest extends TestCase
{
    public function test_it_performs_the_smallest_read_only_check_against_both_services(): void
    {
        $shopify = new class {
            public array $calls = [];
            public function graphql(string $query, array $variables = []): array
            {
                $this->calls[] = [$query, $variables];
                return ['data' => ['shop' => [
                    'name' => 'Test Shop',
                    'myshopifyDomain' => 'test-shop.myshopify.com',
                ]]];
            }
        };
        $tracezilla = new class {
            public array $calls = [];
            public function get(string $path, array $query = []): array
            {
                $this->calls[] = [$path, $query];
                return ['data' => []];
            }
        };

        $result = (new ConnectionChecker($shopify, $tracezilla))->check();

        self::assertSame('ok', $result['status']);
        self::assertSame('Test Shop', $result['shopify']['shop_name']);
        self::assertSame('test-shop.myshopify.com', $result['shopify']['domain']);
        self::assertTrue($result['tracezilla']['connected']);
        self::assertStringContainsString('shop {', $shopify->calls[0][0]);
        self::assertSame(['/skus', ['perPage' => 1]], $tracezilla->calls[0]);
    }

    public function test_it_rejects_an_invalid_shopify_response(): void
    {
        $shopify = new class {
            public function graphql(string $query, array $variables = []): array
            {
                return ['data' => []];
            }
        };
        $tracezilla = new class {
            public function get(string $path, array $query = []): array
            {
                return ['data' => []];
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Shopify connection returned no shop');

        (new ConnectionChecker($shopify, $tracezilla))->check();
    }

    public function test_it_rejects_an_invalid_tracezilla_response(): void
    {
        $shopify = new class {
            public function graphql(string $query, array $variables = []): array
            {
                return ['data' => ['shop' => ['name' => 'Test Shop']]];
            }
        };
        $tracezilla = new class {
            public function get(string $path, array $query = []): array
            {
                return ['unexpected' => true];
            }
        };

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tracezilla connection returned no data collection');

        (new ConnectionChecker($shopify, $tracezilla))->check();
    }
}
