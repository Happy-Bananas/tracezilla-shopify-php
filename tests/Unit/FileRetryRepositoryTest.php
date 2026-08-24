<?php

declare(strict_types=1);

namespace Tests\Unit;

use DateTimeImmutable;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tracezilla\Shopify\Retry\BackoffPolicy;
use Tracezilla\Shopify\Retry\FailureCategory;
use Tracezilla\Shopify\Retry\FileRetryRepository;
use Tracezilla\Shopify\Retry\IntegrationFailure;
use Tracezilla\Shopify\Retry\RetryStatus;
use Tracezilla\Shopify\Retry\TaskIdentity;

final class FileRetryRepositoryTest extends TestCase
{
    private string $runtimeDirectory;

    protected function setUp(): void
    {
        $this->runtimeDirectory = sys_get_temp_dir().'/tracezilla-retry-'.bin2hex(random_bytes(8));
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

    public function test_it_persists_one_atomic_file_per_pending_task(): void
    {
        $repository = $this->repository();
        $identity = new TaskIdentity('orders:import-individual', 'shopify', '1842');
        $now = new DateTimeImmutable('2026-08-24T12:30:00Z');

        $task = $repository->recordFailure(
            $identity,
            new IntegrationFailure('tracezilla_unavailable', FailureCategory::Temporary, 'HTTP 503'),
            $now,
        );

        self::assertSame(RetryStatus::Pending, $task->status);
        self::assertSame(1, $task->attempts);
        self::assertEquals($now, $task->nextAttemptAt);
        self::assertFileExists($this->runtimeDirectory.'/retry/pending/'.$identity->taskId().'.json');
        self::assertSame([], glob($this->runtimeDirectory.'/retry/pending/*.tmp-*') ?: []);
        self::assertSame($task->toArray(), $repository->find($identity->taskId())?->toArray());
    }

    public function test_temporary_failures_back_off_and_eventually_require_attention(): void
    {
        $repository = $this->repository();
        $identity = new TaskIdentity('orders:import-individual', 'shopify', '1842');
        $failure = new IntegrationFailure('timeout', FailureCategory::Temporary, 'Timed out');
        $now = new DateTimeImmutable('2026-08-24T12:00:00Z');

        $expectedDelays = [0, 300, 900, 3600, 21600];
        foreach ($expectedDelays as $index => $delay) {
            $task = $repository->recordFailure($identity, $failure, $now);
            self::assertSame($index + 1, $task->attempts);
            self::assertEquals($now->modify("+{$delay} seconds"), $task->nextAttemptAt);
            self::assertSame(RetryStatus::Pending, $task->status);
        }

        $task = $repository->recordFailure($identity, $failure, $now);

        self::assertSame(6, $task->attempts);
        self::assertSame(RetryStatus::Attention, $task->status);
        self::assertSame('maximum_attempts_reached', $task->attentionReason);
        self::assertFileDoesNotExist($this->runtimeDirectory.'/retry/pending/'.$identity->taskId().'.json');
        self::assertFileExists($this->runtimeDirectory.'/retry/attention/'.$identity->taskId().'.json');
    }

    public function test_business_failures_require_attention_immediately(): void
    {
        $repository = $this->repository();
        $identity = new TaskIdentity('orders:import-individual', 'shopify', '1842');

        $task = $repository->recordFailure(
            $identity,
            new IntegrationFailure('unknown_sku', FailureCategory::Business, 'SKU COFFEE-500 is unknown'),
            new DateTimeImmutable('2026-08-24T12:00:00Z'),
        );

        self::assertSame(RetryStatus::Attention, $task->status);
        self::assertSame('business_rule_requires_attention', $task->attentionReason);
        self::assertCount(1, $repository->attention());
    }

    public function test_resolving_a_task_deletes_it_and_appends_sanitized_history(): void
    {
        $repository = $this->repository();
        $identity = new TaskIdentity('orders:import-individual', 'shopify', '1842');
        $task = $repository->recordFailure(
            $identity,
            new IntegrationFailure('timeout', FailureCategory::Temporary, 'Secret response must not be copied'),
            new DateTimeImmutable('2026-08-24T12:00:00Z'),
        );

        $repository->resolve($task, new DateTimeImmutable('2026-08-24T12:05:00Z'));

        self::assertNull($repository->find($identity->taskId()));
        $history = file_get_contents($this->runtimeDirectory.'/history/2026-08.ndjson');
        self::assertIsString($history);
        self::assertStringContainsString('"event":"resolved"', $history);
        self::assertStringContainsString('"external_id":"1842"', $history);
        self::assertStringNotContainsString('Secret response', $history);
    }

    public function test_an_operator_can_retry_or_dismiss_an_attention_task(): void
    {
        $repository = $this->repository();
        $identity = new TaskIdentity('orders:import-individual', 'shopify', '1842');
        $attention = $repository->recordFailure(
            $identity,
            new IntegrationFailure('unknown_sku', FailureCategory::Business, 'Unknown SKU'),
            new DateTimeImmutable('2026-08-24T12:00:00Z'),
        );

        $pending = $repository->retry($attention, new DateTimeImmutable('2026-08-24T12:10:00Z'));
        self::assertSame(RetryStatus::Pending, $pending->status);
        self::assertSame('2026-08-24T12:10:00+00:00', $pending->nextAttemptAt?->format(DATE_ATOM));

        $repository->requireAttention($pending, 'manual_review', new DateTimeImmutable('2026-08-24T12:11:00Z'));
        $repository->dismiss(
            $repository->find($identity->taskId()),
            'Customer cancelled the import',
            new DateTimeImmutable('2026-08-24T12:12:00Z'),
        );

        self::assertNull($repository->find($identity->taskId()));
        $history = (string) file_get_contents($this->runtimeDirectory.'/history/2026-08.ndjson');
        self::assertStringContainsString('"event":"retried_manually"', $history);
        self::assertStringContainsString('"event":"dismissed"', $history);
        self::assertStringContainsString('Customer cancelled the import', $history);
    }

    public function test_due_tasks_are_ordered_and_bounded(): void
    {
        $repository = $this->repository();
        $now = new DateTimeImmutable('2026-08-24T12:00:00Z');
        foreach (['3', '1', '2'] as $externalId) {
            $repository->recordFailure(
                new TaskIdentity('orders:import-individual', 'shopify', $externalId),
                new IntegrationFailure('timeout', FailureCategory::Temporary, 'Timeout'),
                $now,
            );
        }

        $due = $repository->due($now, 2);

        self::assertCount(2, $due);
        self::assertSame(['1', '2'], array_map(static fn ($task): string => $task->identity->externalId, $due));
    }

    public function test_corrupt_task_files_are_quarantined_without_blocking_valid_tasks(): void
    {
        $repository = $this->repository();
        $identity = new TaskIdentity('orders:import-individual', 'shopify', '1842');
        $repository->recordFailure(
            $identity,
            new IntegrationFailure('timeout', FailureCategory::Temporary, 'Timeout'),
            new DateTimeImmutable('2026-08-24T12:00:00Z'),
        );
        file_put_contents($this->runtimeDirectory.'/retry/pending/broken.json', '{not json');

        $due = $repository->due(new DateTimeImmutable('2026-08-24T12:00:00Z'), 10);

        self::assertCount(1, $due);
        self::assertFileDoesNotExist($this->runtimeDirectory.'/retry/pending/broken.json');
        self::assertFileExists($this->runtimeDirectory.'/retry/corrupt/broken.json');
        $history = (string) file_get_contents($this->runtimeDirectory.'/history/2026-08.ndjson');
        self::assertStringContainsString('"event":"corrupt_task_quarantined"', $history);
    }

    public function test_finding_a_corrupt_known_task_quarantines_it(): void
    {
        $repository = $this->repository();
        $identity = new TaskIdentity('orders:import-individual', 'shopify', '1842');
        $repository->recordFailure(
            $identity,
            new IntegrationFailure('timeout', FailureCategory::Temporary, 'Timeout'),
            new DateTimeImmutable('2026-08-24T12:00:00Z'),
        );
        $pending = $this->runtimeDirectory.'/retry/pending/'.$identity->taskId().'.json';
        file_put_contents($pending, '{not json');

        self::assertNull($repository->find($identity->taskId()));
        self::assertFileDoesNotExist($pending);
        self::assertFileExists($this->runtimeDirectory.'/retry/corrupt/'.$identity->taskId().'.json');
    }

    private function repository(): FileRetryRepository
    {
        return new FileRetryRepository(
            $this->runtimeDirectory,
            new BackoffPolicy([0, 300, 900, 3600, 21600], 5),
        );
    }
}
