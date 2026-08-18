<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tracezilla\Shopify\Output\CommandExecution;

final class CommandExecutionTest extends TestCase
{
    public function test_it_renders_a_human_header(): void
    {
        $execution = new CommandExecution('php bin/compare-catalogs --limit=10', 'read_only');

        self::assertSame(
            "Command: php bin/compare-catalogs --limit=10\nMode: READ ONLY\n\n",
            $execution->header(),
        );
    }

    public function test_it_wraps_a_structured_result(): void
    {
        $execution = new CommandExecution('php bin/compare-catalogs --json', 'read_only');

        self::assertSame([
            'command' => 'php bin/compare-catalogs --json',
            'mode' => 'read_only',
            'result' => ['status' => 'match'],
        ], $execution->withResult(['status' => 'match']));
    }
}
