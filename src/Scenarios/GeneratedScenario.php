<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Scenarios;

final readonly class GeneratedScenario
{
    /** @param list<string> $files */
    public function __construct(
        public string $slug,
        public string $platform,
        public string $className,
        public string $directory,
        public array $files,
    ) {}
}
