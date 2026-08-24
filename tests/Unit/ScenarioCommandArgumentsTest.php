<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tracezilla\Shopify\Scenarios\ScenarioCommandArguments;

final class ScenarioCommandArgumentsTest extends TestCase
{
    public function test_it_accepts_both_platform_option_styles(): void
    {
        self::assertSame(
            ['slug' => 'hello-world', 'platform' => 'shopify'],
            ScenarioCommandArguments::parse(['hello-world', '--platform=shopify']),
        );
        self::assertSame(
            ['slug' => 'hello-world', 'platform' => 'shopify'],
            ScenarioCommandArguments::parse(['--platform', 'shopify', 'hello-world']),
        );
    }

    public function test_shopify_is_the_simple_default(): void
    {
        self::assertSame(
            ['slug' => 'hello-world', 'platform' => 'shopify'],
            ScenarioCommandArguments::parse(['hello-world']),
        );
    }

    public function test_it_rejects_unknown_options(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ScenarioCommandArguments::parse(['hello-world', '--shopify']);
    }
}
