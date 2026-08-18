<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Output;

final readonly class CommandExecution
{
    public function __construct(
        public string $command,
        public string $mode,
    ) {}

    public function header(): string
    {
        $displayMode = strtoupper(str_replace('_', ' ', $this->mode));

        return "Command: {$this->command}\nMode: {$displayMode}\n\n";
    }

    public function withResult(array $result): array
    {
        return [
            'command' => $this->command,
            'mode' => $this->mode,
            'result' => $result,
        ];
    }
}
