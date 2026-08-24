<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Retry;

use InvalidArgumentException;

final readonly class IntegrationFailure
{
    public function __construct(
        public string $code,
        public FailureCategory $category,
        public string $message,
    ) {
        if (trim($code) === '') {
            throw new InvalidArgumentException('Failure code must not be blank.');
        }
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'category' => $this->category->value,
            'message' => $this->message,
        ];
    }
}

