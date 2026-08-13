<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Workflows;

final class SynchronizeInventoryResult
{
    private array $items = [];
    public function __construct(public readonly bool $dryRun) {}
    public function add(string $sku, string $status, string $message, ?int $from = null, ?int $to = null): void { $this->items[] = compact('sku', 'status', 'message', 'from', 'to'); }
    public function toArray(): array
    {
        $summary = ['dry_run' => $this->dryRun, 'updated' => 0, 'would_update' => 0, 'unchanged' => 0, 'skipped' => 0, 'failed' => 0];
        foreach ($this->items as $item) $summary[$item['status']]++;
        return ['summary' => $summary, 'items' => $this->items];
    }
    public function hasFailures(): bool { return array_filter($this->items, fn (array $item): bool => $item['status'] === 'failed') !== []; }
}
