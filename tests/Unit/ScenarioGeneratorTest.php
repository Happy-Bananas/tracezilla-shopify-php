<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tracezilla\Shopify\Scenarios\ScenarioGenerator;

final class ScenarioGeneratorTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/tracezilla-scenario-generator-'.bin2hex(random_bytes(6));
        mkdir($this->directory, 0700, true);
    }

    protected function tearDown(): void
    {
        $this->remove($this->directory);
    }

    public function test_it_generates_the_complete_hello_world_scenario(): void
    {
        $result = (new ScenarioGenerator($this->directory))->generate('confirm-credentials', 'shopify');

        self::assertSame('ConfirmCredentials', $result->className);
        self::assertSame('confirm-credentials', $result->slug);
        self::assertSame('shopify', $result->platform);
        self::assertSame([
            'ShopifyQuery.graphql',
            'TracezillaRequest.php',
            'BusinessRules.php',
            'BusinessRulesTest.php',
        ], array_map('basename', $result->files));

        $scenario = $this->directory.'/custom/Scenarios/Shopify/ConfirmCredentials';
        self::assertStringContainsString('shop {', file_get_contents($scenario.'/ShopifyQuery.graphql'));
        self::assertStringContainsString("return '/skus';", file_get_contents($scenario.'/TracezillaRequest.php'));
        self::assertStringContainsString("'perPage' => 1", file_get_contents($scenario.'/TracezillaRequest.php'));
        self::assertStringContainsString('final class BusinessRules', file_get_contents($scenario.'/BusinessRules.php'));
        self::assertStringContainsString('final class BusinessRulesTest', file_get_contents($scenario.'/BusinessRulesTest.php'));
    }

    public function test_it_refuses_unsafe_names_and_existing_scenarios(): void
    {
        $generator = new ScenarioGenerator($this->directory);

        $this->expectException(InvalidArgumentException::class);
        $generator->generate('../unsafe');
    }

    public function test_it_rejects_an_unsupported_platform_without_creating_files(): void
    {
        $generator = new ScenarioGenerator($this->directory);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported platform');
        $generator->generate('hello-world', 'woocommerce');
    }

    public function test_it_never_overwrites_an_existing_scenario(): void
    {
        $generator = new ScenarioGenerator($this->directory);
        $generator->generate('hello-world');

        $this->expectException(RuntimeException::class);
        $generator->generate('hello-world');
    }

    private function remove(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $name) {
            $child = $path.'/'.$name;
            is_dir($child) ? $this->remove($child) : unlink($child);
        }
        rmdir($path);
    }
}
