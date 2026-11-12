<?php

declare(strict_types=1);

namespace Tests\Unit\Connection;

use PHPUnit\Framework\TestCase;
use Tracezilla\Shopify\Connection\ConnectionChecker;

final class ConnectionCheckerTest extends TestCase
{
    public function test_it_reports_both_connections(): void
    {
        $checker = new ConnectionChecker(
            FakeShopifyConnection::answering(['data' => ['shop' => ['name' => ' Test Shop ', 'myshopifyDomain' => 'test-shop.myshopify.com']]]),
            FakeTracezillaConnection::answering(['data' => []]),
            'dev-team',
        );

        $result = $checker->check();

        self::assertSame([
            'status' => 'ok',
            'shopify' => ['connected' => true, 'shop_name' => 'Test Shop', 'domain' => 'test-shop.myshopify.com'],
            'tracezilla' => ['team' => 'dev-team', 'connected' => true],
        ], $result);
    }

    public function test_a_shopify_failure_does_not_hide_the_tracezilla_result(): void
    {
        $checker = new ConnectionChecker(
            FakeShopifyConnection::failingWith('Missing required environment variable: SHOPIFY_CLIENT_ID'),
            FakeTracezillaConnection::answering(['data' => []]),
            'dev-team',
        );

        $result = $checker->check();

        self::assertSame('failed', $result['status']);
        self::assertSame(['connected' => false, 'error' => 'Missing required environment variable: SHOPIFY_CLIENT_ID'], $result['shopify']);
        self::assertTrue($result['tracezilla']['connected']);
    }

    public function test_a_tracezilla_failure_does_not_hide_the_shopify_result(): void
    {
        $checker = new ConnectionChecker(
            FakeShopifyConnection::answering(['data' => ['shop' => ['name' => 'Test Shop']]]),
            FakeTracezillaConnection::failingWith('tracezilla token request failed with HTTP 400: invalid_grant'),
            'dev-team',
        );

        $result = $checker->check();

        self::assertSame('failed', $result['status']);
        self::assertSame(['connected' => true, 'shop_name' => 'Test Shop', 'domain' => null], $result['shopify']);
        self::assertStringContainsString('invalid_grant', $result['tracezilla']['error']);
    }

    public function test_unexpected_responses_count_as_failures(): void
    {
        $checker = new ConnectionChecker(
            FakeShopifyConnection::answering(['data' => ['shop' => ['name' => '  ']]]),
            FakeTracezillaConnection::answering(['unexpected' => true]),
            'dev-team',
        );

        $result = $checker->check();

        self::assertSame('failed', $result['status']);
        self::assertSame('Shopify returned no shop.', $result['shopify']['error']);
        self::assertSame('tracezilla returned no data collection.', $result['tracezilla']['error']);
    }
}
