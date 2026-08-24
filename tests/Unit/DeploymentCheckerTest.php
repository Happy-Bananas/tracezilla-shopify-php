<?php

declare(strict_types=1);

namespace Tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tracezilla\Shopify\Deployment\DeploymentChecker;

final class DeploymentCheckerTest extends TestCase
{
    private string $runtimeDirectory;

    protected function setUp(): void
    {
        $this->runtimeDirectory = sys_get_temp_dir().'/tracezilla-deployment-'.bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        if (! is_dir($this->runtimeDirectory)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->runtimeDirectory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->runtimeDirectory);
    }

    public function test_it_verifies_directories_atomic_rename_and_locking_without_leaving_test_files(): void
    {
        $result = (new DeploymentChecker())->check($this->runtimeDirectory);

        self::assertTrue($result['ready']);
        self::assertSame([
            'runtime_directory' => true,
            'atomic_file_replace' => true,
            'global_lock' => true,
        ], $result['checks']);
        self::assertSame([], glob($this->runtimeDirectory.'/.deployment-check-*') ?: []);
        self::assertDirectoryExists($this->runtimeDirectory.'/log');
        self::assertDirectoryExists($this->runtimeDirectory.'/retry/pending');
        self::assertDirectoryExists($this->runtimeDirectory.'/retry/attention');
        self::assertDirectoryExists($this->runtimeDirectory.'/retry/corrupt');
        self::assertDirectoryExists($this->runtimeDirectory.'/history');
    }
}
