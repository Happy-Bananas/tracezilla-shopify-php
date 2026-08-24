<?php

declare(strict_types=1);

namespace Tests\Unit;

use DateTimeImmutable;
use FilesystemIterator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tracezilla\Shopify\Retry\FailureCategory;
use Tracezilla\Shopify\Retry\FileRetryRepository;
use Tracezilla\Shopify\Retry\IntegrationFailure;
use Tracezilla\Shopify\Retry\RetryManagement;
use Tracezilla\Shopify\Retry\RetryStatus;
use Tracezilla\Shopify\Retry\TaskIdentity;

final class RetryManagementTest extends TestCase
{
    private string $runtimeDirectory;

    protected function setUp(): void
    {
        $this->runtimeDirectory = sys_get_temp_dir().'/tracezilla-management-'.bin2hex(random_bytes(8));
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

    public function test_it_lists_pending_and_attention_tasks_without_exposing_error_messages(): void
    {
        $repository = new FileRetryRepository($this->runtimeDirectory);
        $repository->recordFailure(
            new TaskIdentity('orders:import-individual', 'shopify', '1001'),
            new IntegrationFailure('timeout', FailureCategory::Temporary, 'Sensitive response'),
            new DateTimeImmutable('2026-08-24T12:00:00Z'),
        );
        $repository->recordFailure(
            new TaskIdentity('orders:import-individual', 'shopify', '1002'),
            new IntegrationFailure('unknown_sku', FailureCategory::Business, 'Sensitive SKU context'),
            new DateTimeImmutable('2026-08-24T12:00:00Z'),
        );

        $result = (new RetryManagement($repository))->list();

        self::assertCount(1, $result['pending']);
        self::assertCount(1, $result['attention']);
        self::assertSame('1001', $result['pending'][0]['external_id']);
        self::assertArrayNotHasKey('message', $result['pending'][0]['last_error']);
    }

    public function test_retry_and_dismiss_require_existing_tasks(): void
    {
        $repository = new FileRetryRepository($this->runtimeDirectory);
        $management = new RetryManagement($repository);

        $this->expectException(InvalidArgumentException::class);
        $management->retry(str_repeat('a', 64), new DateTimeImmutable('2026-08-24T12:00:00Z'));
    }

    public function test_it_retries_and_dismisses_attention_tasks(): void
    {
        $repository = new FileRetryRepository($this->runtimeDirectory);
        $task = $repository->recordFailure(
            new TaskIdentity('orders:import-individual', 'shopify', '1002'),
            new IntegrationFailure('unknown_sku', FailureCategory::Business, 'Unknown SKU'),
            new DateTimeImmutable('2026-08-24T12:00:00Z'),
        );
        $management = new RetryManagement($repository);

        $pending = $management->retry($task->identity->taskId(), new DateTimeImmutable('2026-08-24T12:05:00Z'));
        self::assertSame(RetryStatus::Pending, $pending->status);

        $repository->requireAttention($pending, 'manual_review', new DateTimeImmutable('2026-08-24T12:06:00Z'));
        $management->dismiss(
            $task->identity->taskId(),
            'Customer chose not to import it',
            new DateTimeImmutable('2026-08-24T12:07:00Z'),
        );
        self::assertNull($repository->find($task->identity->taskId()));
    }
}

