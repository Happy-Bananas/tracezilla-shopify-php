<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Workflows;

final class ImportIndividualOrdersResult
{
    /** @var list<array{shopify_order:string,external_reference:?string,status:string,message:string}> */
    private array $items = [];

    public function __construct(
        public readonly int $sourceCount,
        public readonly int $selectedCount,
        public readonly bool $dryRun,
        public readonly int $days,
        public readonly int $limit,
    ) {}

    public function add(
        string $shopifyOrder,
        ?string $externalReference,
        string $status,
        string $message,
    ): void {
        $this->items[] = [
            'shopify_order' => $shopifyOrder,
            'external_reference' => $externalReference,
            'status' => $status,
            'message' => $message,
        ];
    }

    public function hasFailures(): bool
    {
        return $this->count('invalid') > 0 || $this->count('failed') > 0;
    }

    public function toArray(): array
    {
        return [
            'summary' => [
                'source_count' => $this->sourceCount,
                'selected_count' => $this->selectedCount,
                'processed_count' => count($this->items),
                'created_count' => $this->count('created'),
                'would_create_count' => $this->count('would_create'),
                'skipped_count' => $this->count('skipped'),
                'invalid_count' => $this->count('invalid'),
                'failed_count' => $this->count('failed'),
                'dry_run' => $this->dryRun,
                'days' => $this->days,
                'limit' => $this->limit,
            ],
            'items' => $this->items,
        ];
    }

    private function count(string $status): int
    {
        return count(array_filter(
            $this->items,
            static fn (array $item): bool => $item['status'] === $status,
        ));
    }
}
