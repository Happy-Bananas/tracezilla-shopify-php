<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Scenarios;

use InvalidArgumentException;

final class ScenarioCommandArguments
{
    /** @param list<string> $arguments
     *  @return array{slug: string, platform: string}
     */
    public static function parse(array $arguments): array
    {
        $slug = null;
        $platform = 'shopify';
        for ($index = 0; $index < count($arguments); $index++) {
            $argument = $arguments[$index];
            if ($argument === '--platform') {
                $platform = $arguments[++$index] ?? '';
                if ($platform === '') {
                    throw new InvalidArgumentException('--platform requires a value.');
                }
                continue;
            }
            if (str_starts_with($argument, '--platform=')) {
                $platform = substr($argument, strlen('--platform='));
                if ($platform === '') {
                    throw new InvalidArgumentException('--platform requires a value.');
                }
                continue;
            }
            if (str_starts_with($argument, '-')) {
                throw new InvalidArgumentException("Unknown option: {$argument}");
            }
            if ($slug !== null) {
                throw new InvalidArgumentException('Only one scenario name may be supplied.');
            }
            $slug = $argument;
        }
        if ($slug === null || $slug === '') {
            throw new InvalidArgumentException('A scenario name is required.');
        }

        return ['slug' => $slug, 'platform' => strtolower($platform)];
    }
}
