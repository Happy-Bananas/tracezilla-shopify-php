<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Scenarios;

use InvalidArgumentException;
use RuntimeException;

final readonly class ScenarioGenerator
{
    public function __construct(private string $projectDirectory) {}

    public function generate(string $slug, string $platform = 'shopify'): GeneratedScenario
    {
        $platform = strtolower(trim($platform));
        if ($platform !== 'shopify') {
            throw new InvalidArgumentException("Unsupported platform: {$platform}. Available platforms: shopify.");
        }
        if (preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            throw new InvalidArgumentException('Scenario name must use lowercase letters, numbers, and single hyphens.');
        }

        $className = str_replace(' ', '', ucwords(str_replace('-', ' ', $slug)));
        $platformClass = ucfirst($platform);
        $directory = rtrim($this->projectDirectory, '/').'/custom/Scenarios/'.$platformClass.'/'.$className;
        if (file_exists($directory)) {
            throw new RuntimeException("Scenario already exists: {$slug}");
        }
        if (! mkdir($directory, 0750, true) && ! is_dir($directory)) {
            throw new RuntimeException("Could not create scenario directory: {$directory}");
        }

        $namespace = "Consultant\\Scenarios\\{$platformClass}\\{$className}";
        $templates = [
            'ShopifyQuery.graphql' => $this->shopifyQuery(),
            'TracezillaRequest.php' => $this->tracezillaRequest($namespace),
            'BusinessRules.php' => $this->businessRules($namespace),
            'BusinessRulesTest.php' => $this->businessRulesTest($namespace),
        ];
        $files = [];
        foreach ($templates as $name => $contents) {
            $path = $directory.'/'.$name;
            if (file_put_contents($path, $contents, LOCK_EX) === false) {
                throw new RuntimeException("Could not write generated file: {$path}");
            }
            $files[] = $path;
        }

        return new GeneratedScenario($slug, $platform, $className, $directory, $files);
    }

    private function shopifyQuery(): string
    {
        return <<<'GRAPHQL'
query ConfirmShopifyCredentials {
  shop {
    name
    myshopifyDomain
  }
}
GRAPHQL;
    }

    private function tracezillaRequest(string $namespace): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

final class TracezillaRequest
{
    public function path(): string
    {
        return '/skus';
    }

    public function query(): array
    {
        return ['perPage' => 1];
    }
}
PHP;
    }

    private function businessRules(string $namespace): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use RuntimeException;

final class BusinessRules
{
    public function apply(array \$shopify, array \$tracezilla): array
    {
        \$shop = \$shopify['data']['shop'] ?? null;
        if (! is_array(\$shop) || ! is_string(\$shop['name'] ?? null)) {
            throw new RuntimeException('Shopify credential check returned no shop.');
        }
        if (! is_array(\$tracezilla['data'] ?? null)) {
            throw new RuntimeException('Tracezilla credential check returned no data collection.');
        }

        return [
            'status' => 'ok',
            'shopify' => [
                'credentials_valid' => true,
                'shop_name' => \$shop['name'],
                'domain' => \$shop['myshopifyDomain'] ?? null,
            ],
            'tracezilla' => ['credentials_valid' => true],
            'message' => 'Hello! Both API credentials are working.',
        ];
    }
}
PHP;
    }

    private function businessRulesTest(string $namespace): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use PHPUnit\Framework\TestCase;

final class BusinessRulesTest extends TestCase
{
    public function test_it_confirms_both_connections_without_exposing_credentials(): void
    {
        \$result = (new BusinessRules())->apply(
            ['data' => ['shop' => ['name' => 'Example Shop', 'myshopifyDomain' => 'example.myshopify.com']]],
            ['data' => []],
        );

        self::assertSame('ok', \$result['status']);
        self::assertSame('Example Shop', \$result['shopify']['shop_name']);
        self::assertTrue(\$result['tracezilla']['credentials_valid']);
    }
}
PHP;
    }
}
