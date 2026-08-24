<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Retry;

use InvalidArgumentException;

final readonly class TaskIdentity
{
    public function __construct(
        public string $workflow,
        public string $source,
        public string $externalId,
    ) {
        foreach (['workflow' => $workflow, 'source' => $source, 'external ID' => $externalId] as $name => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException("Task {$name} must not be blank.");
            }
        }
    }

    public function taskId(): string
    {
        return hash('sha256', implode('|', [$this->workflow, $this->source, $this->externalId]));
    }

    public function toArray(): array
    {
        return [
            'workflow' => $this->workflow,
            'source' => $this->source,
            'external_id' => $this->externalId,
        ];
    }
}

