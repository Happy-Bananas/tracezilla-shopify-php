<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Retry;

use DateTimeImmutable;

interface RetryRepository
{
    public function recordFailure(TaskIdentity $identity, IntegrationFailure $failure, DateTimeImmutable $now): RetryTask;

    /** @return list<RetryTask> */
    public function due(DateTimeImmutable $now, int $limit): array;

    /** @return list<RetryTask> */
    public function pending(): array;

    /** @return list<RetryTask> */
    public function attention(): array;

    public function find(string $taskId): ?RetryTask;

    public function resolve(RetryTask $task, DateTimeImmutable $now): void;

    public function requireAttention(RetryTask $task, string $reason, DateTimeImmutable $now): RetryTask;

    public function retry(RetryTask $task, DateTimeImmutable $now): RetryTask;

    public function dismiss(RetryTask $task, string $reason, DateTimeImmutable $now): void;
}
