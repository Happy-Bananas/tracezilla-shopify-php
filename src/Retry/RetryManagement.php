<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Retry;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class RetryManagement
{
    public function __construct(private RetryRepository $repository) {}

    public function list(): array
    {
        return [
            'pending' => array_map($this->summarize(...), $this->repository->pending()),
            'attention' => array_map($this->summarize(...), $this->repository->attention()),
        ];
    }

    public function retry(string $taskId, DateTimeImmutable $now): RetryTask
    {
        return $this->repository->retry($this->requiredTask($taskId), $now);
    }

    public function dismiss(string $taskId, string $reason, DateTimeImmutable $now): void
    {
        $this->repository->dismiss($this->requiredTask($taskId), $reason, $now);
    }

    private function requiredTask(string $taskId): RetryTask
    {
        $task = $this->repository->find($taskId);
        if ($task === null) {
            throw new InvalidArgumentException("Retry task [{$taskId}] was not found.");
        }

        return $task;
    }

    private function summarize(RetryTask $task): array
    {
        return [
            'task_id' => $task->identity->taskId(),
            ...$task->identity->toArray(),
            'status' => $task->status->value,
            'attempts' => $task->attempts,
            'next_attempt_at' => $task->nextAttemptAt?->format(DATE_ATOM),
            'attention_reason' => $task->attentionReason,
            'last_error' => [
                'code' => $task->lastError->code,
                'category' => $task->lastError->category->value,
            ],
        ];
    }
}

