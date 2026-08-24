<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Retry;

use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class FileRetryRepository implements RetryRepository
{
    private string $pendingDirectory;
    private string $attentionDirectory;
    private string $corruptDirectory;
    private string $historyDirectory;

    public function __construct(
        private readonly string $runtimeDirectory,
        private readonly BackoffPolicy $backoff = new BackoffPolicy(),
    ) {
        $this->pendingDirectory = $runtimeDirectory.'/retry/pending';
        $this->attentionDirectory = $runtimeDirectory.'/retry/attention';
        $this->corruptDirectory = $runtimeDirectory.'/retry/corrupt';
        $this->historyDirectory = $runtimeDirectory.'/history';

        foreach ([$runtimeDirectory, dirname($this->pendingDirectory), $this->pendingDirectory, $this->attentionDirectory, $this->corruptDirectory, $this->historyDirectory] as $directory) {
            $this->ensureDirectory($directory);
        }
    }

    public function recordFailure(TaskIdentity $identity, IntegrationFailure $failure, DateTimeImmutable $now): RetryTask
    {
        $existing = $this->find($identity->taskId());
        $attempts = ($existing?->attempts ?? 0) + 1;
        $requiresAttention = $failure->category === FailureCategory::Business
            || $attempts > $this->backoff->maximumAutomaticAttempts;
        $reason = match (true) {
            $failure->category === FailureCategory::Business => 'business_rule_requires_attention',
            $attempts > $this->backoff->maximumAutomaticAttempts => 'maximum_attempts_reached',
            default => null,
        };
        $task = new RetryTask(
            identity: $identity,
            status: $requiresAttention ? RetryStatus::Attention : RetryStatus::Pending,
            attempts: $attempts,
            firstFailedAt: $existing?->firstFailedAt ?? $now,
            lastFailedAt: $now,
            nextAttemptAt: $requiresAttention
                ? null
                : $now->modify('+'.$this->backoff->delayForAttempt($attempts).' seconds'),
            lastError: $failure,
            attentionReason: $reason,
        );

        $this->persist($task);
        $this->removeFromOtherState($task);
        if ($requiresAttention) {
            $this->appendHistory('attention_required', $task, $now, ['reason' => $reason]);
        }

        return $task;
    }

    public function due(DateTimeImmutable $now, int $limit): array
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('Due-task limit must be positive.');
        }

        $tasks = $this->readDirectory($this->pendingDirectory, $now);
        $tasks = array_values(array_filter(
            $tasks,
            static fn (RetryTask $task): bool => $task->nextAttemptAt !== null && $task->nextAttemptAt <= $now,
        ));
        usort($tasks, static fn (RetryTask $left, RetryTask $right): int => [
            $left->nextAttemptAt?->getTimestamp(),
            $left->identity->externalId,
        ] <=> [
            $right->nextAttemptAt?->getTimestamp(),
            $right->identity->externalId,
        ]);

        return array_slice($tasks, 0, $limit);
    }

    public function pending(): array
    {
        $tasks = $this->readDirectory($this->pendingDirectory, new DateTimeImmutable('now'));
        usort($tasks, static fn (RetryTask $left, RetryTask $right): int => $left->identity->externalId <=> $right->identity->externalId);

        return $tasks;
    }

    public function attention(): array
    {
        $tasks = $this->readDirectory($this->attentionDirectory, new DateTimeImmutable('now'));
        usort($tasks, static fn (RetryTask $left, RetryTask $right): int => $left->identity->externalId <=> $right->identity->externalId);

        return $tasks;
    }

    public function find(string $taskId): ?RetryTask
    {
        $this->assertTaskId($taskId);
        foreach ([$this->pendingDirectory, $this->attentionDirectory] as $directory) {
            $path = $directory.'/'.$taskId.'.json';
            if (is_file($path)) {
                try {
                    return $this->readTask($path);
                } catch (Throwable) {
                    $this->quarantine($path, new DateTimeImmutable('now'));
                    return null;
                }
            }
        }

        return null;
    }

    public function resolve(RetryTask $task, DateTimeImmutable $now): void
    {
        $this->appendHistory('resolved', $task, $now);
        $this->remove($task);
    }

    public function requireAttention(RetryTask $task, string $reason, DateTimeImmutable $now): RetryTask
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Attention reason must not be blank.');
        }
        $attention = new RetryTask(
            $task->identity,
            RetryStatus::Attention,
            $task->attempts,
            $task->firstFailedAt,
            $task->lastFailedAt,
            null,
            $task->lastError,
            $reason,
        );
        $this->persist($attention);
        $this->removeFromOtherState($attention);
        $this->appendHistory('attention_required', $attention, $now, ['reason' => $reason]);

        return $attention;
    }

    public function retry(RetryTask $task, DateTimeImmutable $now): RetryTask
    {
        $pending = new RetryTask(
            $task->identity,
            RetryStatus::Pending,
            $task->attempts,
            $task->firstFailedAt,
            $task->lastFailedAt,
            $now,
            $task->lastError,
        );
        $this->persist($pending);
        $this->removeFromOtherState($pending);
        $this->appendHistory('retried_manually', $pending, $now);

        return $pending;
    }

    public function dismiss(RetryTask $task, string $reason, DateTimeImmutable $now): void
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Dismissal reason must not be blank.');
        }
        $this->appendHistory('dismissed', $task, $now, ['reason' => $reason]);
        $this->remove($task);
    }

    private function persist(RetryTask $task): void
    {
        $directory = $task->status === RetryStatus::Pending ? $this->pendingDirectory : $this->attentionDirectory;
        $path = $directory.'/'.$task->identity->taskId().'.json';
        $temporary = $path.'.tmp-'.bin2hex(random_bytes(6));
        $json = json_encode($task->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
        $handle = fopen($temporary, 'xb');
        if ($handle === false) {
            throw new RuntimeException("Could not create temporary retry file [{$temporary}].");
        }

        try {
            chmod($temporary, 0600);
            if (fwrite($handle, $json) !== strlen($json) || ! fflush($handle)) {
                throw new RuntimeException("Could not write retry task [{$temporary}].");
            }
        } finally {
            fclose($handle);
        }

        if (! rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException("Could not publish retry task [{$path}].");
        }
    }

    /** @return list<RetryTask> */
    private function readDirectory(string $directory, DateTimeImmutable $now): array
    {
        $tasks = [];
        foreach (glob($directory.'/*.json') ?: [] as $path) {
            try {
                $tasks[] = $this->readTask($path);
            } catch (Throwable $exception) {
                $this->quarantine($path, $now, $exception);
            }
        }

        return $tasks;
    }

    private function readTask(string $path): RetryTask
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Could not read retry task [{$path}].");
        }
        $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            throw new RuntimeException("Retry task [{$path}] is not a JSON object.");
        }

        return RetryTask::fromArray($data);
    }

    private function quarantine(string $path, DateTimeImmutable $now, ?Throwable $cause = null): void
    {
        $destination = $this->corruptDirectory.'/'.basename($path);
        if (! rename($path, $destination)) {
            throw new RuntimeException("Could not quarantine corrupt retry task [{$path}].", 0, $cause);
        }
        $this->appendRawHistory([
            'event' => 'corrupt_task_quarantined',
            'task_file' => basename($path),
            'recorded_at' => $now->format(DATE_ATOM),
        ], $now);
    }

    private function removeFromOtherState(RetryTask $task): void
    {
        $otherDirectory = $task->status === RetryStatus::Pending ? $this->attentionDirectory : $this->pendingDirectory;
        $other = $otherDirectory.'/'.$task->identity->taskId().'.json';
        if (is_file($other) && ! unlink($other)) {
            throw new RuntimeException("Could not remove previous retry state [{$other}].");
        }
    }

    private function remove(RetryTask $task): void
    {
        foreach ([$this->pendingDirectory, $this->attentionDirectory] as $directory) {
            $path = $directory.'/'.$task->identity->taskId().'.json';
            if (is_file($path) && ! unlink($path)) {
                throw new RuntimeException("Could not remove retry task [{$path}].");
            }
        }
    }

    private function appendHistory(string $event, RetryTask $task, DateTimeImmutable $now, array $extra = []): void
    {
        $this->appendRawHistory([
            'event' => $event,
            'task_id' => $task->identity->taskId(),
            ...$task->identity->toArray(),
            'attempts' => $task->attempts,
            'recorded_at' => $now->format(DATE_ATOM),
            ...$extra,
        ], $now);
    }

    private function appendRawHistory(array $event, DateTimeImmutable $now): void
    {
        $path = $this->historyDirectory.'/'.$now->format('Y-m').'.ndjson';
        $handle = fopen($path, 'ab');
        if ($handle === false) {
            throw new RuntimeException("Could not open retry history [{$path}].");
        }
        chmod($path, 0600);
        try {
            if (! flock($handle, LOCK_EX)) {
                throw new RuntimeException("Could not lock retry history [{$path}].");
            }
            $line = json_encode($event, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
            if (fwrite($handle, $line) !== strlen($line) || ! fflush($handle)) {
                throw new RuntimeException("Could not append retry history [{$path}].");
            }
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException("Could not create runtime directory [{$directory}].");
        }
        chmod($directory, 0700);
        if (! is_writable($directory)) {
            throw new RuntimeException("Runtime directory is not writable [{$directory}].");
        }
    }

    private function assertTaskId(string $taskId): void
    {
        if (! preg_match('/^[a-f0-9]{64}$/', $taskId)) {
            throw new InvalidArgumentException('Task ID must be a SHA-256 hash.');
        }
    }
}
