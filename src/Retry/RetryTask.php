<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Retry;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class RetryTask
{
    public function __construct(
        public TaskIdentity $identity,
        public RetryStatus $status,
        public int $attempts,
        public DateTimeImmutable $firstFailedAt,
        public DateTimeImmutable $lastFailedAt,
        public ?DateTimeImmutable $nextAttemptAt,
        public IntegrationFailure $lastError,
        public ?string $attentionReason = null,
    ) {
        if ($attempts < 1) {
            throw new InvalidArgumentException('Retry attempts must be positive.');
        }
    }

    public function toArray(): array
    {
        return [
            'schema_version' => 1,
            'task_id' => $this->identity->taskId(),
            ...$this->identity->toArray(),
            'status' => $this->status->value,
            'attempts' => $this->attempts,
            'first_failed_at' => $this->firstFailedAt->format(DATE_ATOM),
            'last_failed_at' => $this->lastFailedAt->format(DATE_ATOM),
            'next_attempt_at' => $this->nextAttemptAt?->format(DATE_ATOM),
            'last_error' => $this->lastError->toArray(),
            'attention_reason' => $this->attentionReason,
        ];
    }

    public static function fromArray(array $data): self
    {
        if (($data['schema_version'] ?? null) !== 1) {
            throw new InvalidArgumentException('Unsupported retry-task schema.');
        }

        $identity = new TaskIdentity(
            (string) ($data['workflow'] ?? ''),
            (string) ($data['source'] ?? ''),
            (string) ($data['external_id'] ?? ''),
        );
        if (($data['task_id'] ?? null) !== $identity->taskId()) {
            throw new InvalidArgumentException('Retry-task identity does not match its task ID.');
        }
        $error = is_array($data['last_error'] ?? null) ? $data['last_error'] : [];

        return new self(
            identity: $identity,
            status: RetryStatus::from((string) ($data['status'] ?? '')),
            attempts: (int) ($data['attempts'] ?? 0),
            firstFailedAt: new DateTimeImmutable((string) ($data['first_failed_at'] ?? '')),
            lastFailedAt: new DateTimeImmutable((string) ($data['last_failed_at'] ?? '')),
            nextAttemptAt: is_string($data['next_attempt_at'] ?? null)
                ? new DateTimeImmutable($data['next_attempt_at'])
                : null,
            lastError: new IntegrationFailure(
                (string) ($error['code'] ?? ''),
                FailureCategory::from((string) ($error['category'] ?? '')),
                (string) ($error['message'] ?? ''),
            ),
            attentionReason: is_string($data['attention_reason'] ?? null)
                ? $data['attention_reason']
                : null,
        );
    }
}

