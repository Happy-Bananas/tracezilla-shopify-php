<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Scenarios;

use InvalidArgumentException;
use RuntimeException;

final readonly class ScenarioRunner
{
    public function __construct(
        private string $projectDirectory,
        private object $shopifyClient,
        private object $tracezillaClient,
    ) {}

    public function run(string $slug, string $platform = 'shopify'): array
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
        foreach (['ShopifyQuery.graphql', 'TracezillaRequest.php', 'BusinessRules.php'] as $file) {
            if (! is_file($directory.'/'.$file)) {
                throw new RuntimeException("Scenario {$slug} is missing {$file}.");
            }
        }

        require_once $directory.'/TracezillaRequest.php';
        require_once $directory.'/BusinessRules.php';
        $namespace = "Consultant\\Scenarios\\{$platformClass}\\{$className}\\";
        $requestClass = $namespace.'TracezillaRequest';
        $rulesClass = $namespace.'BusinessRules';
        $query = file_get_contents($directory.'/ShopifyQuery.graphql');
        if (! is_string($query) || trim($query) === '') {
            throw new RuntimeException("Scenario {$slug} has an empty Shopify query.");
        }

        $request = new $requestClass();
        $shopify = $this->shopifyClient->graphql($query);
        $tracezilla = $this->tracezillaClient->get($request->path(), $request->query());

        return (new $rulesClass())->apply($shopify, $tracezilla);
    }
}
