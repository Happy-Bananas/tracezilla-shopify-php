<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tracezilla\Shopify\Scenarios\ScenarioGenerator;
use Tracezilla\Shopify\Scenarios\ScenarioRunner;

final class ScenarioRunnerTest extends TestCase
{
    public function test_it_runs_the_generated_read_only_credential_check(): void
    {
        $project = sys_get_temp_dir().'/tracezilla-scenario-runner-'.bin2hex(random_bytes(6));
        mkdir($project, 0700, true);
        (new ScenarioGenerator($project))->generate('hello-world', 'shopify');

        $shopify = new class {
            public array $calls = [];
            public function graphql(string $query, array $variables = []): array
            {
                $this->calls[] = [$query, $variables];
                return ['data' => ['shop' => ['name' => 'Test Shop', 'myshopifyDomain' => 'test.myshopify.com']]];
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

        $result = (new ScenarioRunner($project, $shopify, $tracezilla))->run('hello-world', 'shopify');

        self::assertSame('ok', $result['status']);
        self::assertSame('Test Shop', $result['shopify']['shop_name']);
        self::assertTrue($result['tracezilla']['credentials_valid']);
        self::assertSame('/skus', $tracezilla->calls[0][0]);
        self::assertSame(['perPage' => 1], $tracezilla->calls[0][1]);

        $this->remove($project);
    }

    private function remove(string $path): void
    {
        if (! is_dir($path)) return;
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $name) {
            $child = $path.'/'.$name;
            is_dir($child) ? $this->remove($child) : unlink($child);
        }
        rmdir($path);
    }
}
