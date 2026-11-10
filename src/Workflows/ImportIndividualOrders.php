<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Workflows;

use DateTimeImmutable;
use InvalidArgumentException;
use Throwable;
use Tracezilla\Shopify\Contracts\ShopifyOrderReader;
use Tracezilla\Shopify\Contracts\TracezillaSalesOrderGateway;
use Tracezilla\Shopify\Retry\FailureCategory;
use Tracezilla\Shopify\Retry\IntegrationFailure;
use Tracezilla\Shopify\Retry\RetryRepository;
use Tracezilla\Shopify\Retry\RetryStatus;
use Tracezilla\Shopify\Retry\TaskIdentity;
use Tracezilla\Shopify\Shopify\ShopifyOrderData;
use Tracezilla\Shopify\Tracezilla\Mappers\ShopifyOrderToTracezillaSalesOrderMapper;

/**
 * Imports Shopify orders as tracezilla sales orders: the orders created in the
 * last --days, and older orders whose earlier import failed and is due for a
 * retry. An order whose external reference already exists is skipped.
 */
final readonly class ImportIndividualOrders
{
    private const WORKFLOW = 'orders:import-individual';

    /** How much of tracezilla's error message is kept in the output and the retry record. */
    private const MAX_ERROR_LENGTH = 500;

    public function __construct(
        private ShopifyOrderReader $shopify,
        private TracezillaSalesOrderGateway $tracezilla,
        private ShopifyOrderToTracezillaSalesOrderMapper $mapper,
        private ?RetryRepository $retries = null,
    ) {}

    public function run(
        ImportIndividualOrdersOptions $options,
        ?DateTimeImmutable $now = null,
    ): ImportIndividualOrdersResult {
        $now ??= new DateTimeImmutable('now');
        $orders = $this->ordersToImport($options, $now);
        $selected = array_slice($orders, 0, $options->limit);
        $result = new ImportIndividualOrdersResult(
            sourceCount: count($orders),
            selectedCount: count($selected),
            dryRun: $options->dryRun,
            days: $options->days,
            limit: $options->limit,
        );

        if ($selected === []) {
            return $result;
        }

        $context = $this->tracezilla->getContext(
            $options->customerName,
            $options->warehouseLocationNumber,
        );
        $existing = $this->tracezilla->existingExternalReferences(
            ShopifyOrderToTracezillaSalesOrderMapper::ORDER_REFERENCE_PREFIX,
        );

        foreach ($selected as $order) {
            if ($order->isCancelled()) {
                $result->add($order->name, null, 'skipped', 'Shopify order is cancelled.');
                continue;
            }
            if ($order->hasMoreLines) {
                $result->add(
                    $order->name,
                    null,
                    'skipped',
                    'Order has more than 250 lines; implement line-item pagination before importing it.',
                );
                continue;
            }

            if (! $options->dryRun && $this->retries !== null) {
                $retry = $this->retries->find($this->retryIdentity($order)->taskId());
                if ($retry?->status === RetryStatus::Attention) {
                    $result->add(
                        $order->name,
                        null,
                        'skipped',
                        'A previous failure requires attention before this order can be retried.',
                    );
                    continue;
                }
                if ($retry?->nextAttemptAt !== null && $retry->nextAttemptAt > $now) {
                    $result->add(
                        $order->name,
                        null,
                        'skipped',
                        'The previous failure retry is scheduled for '.$retry->nextAttemptAt->format(DATE_ATOM).'.',
                    );
                    continue;
                }
            }

            try {
                $mapped = $this->mapper->map($order, $context);
            } catch (InvalidArgumentException $exception) {
                if (! $options->dryRun) {
                    $this->retries?->recordFailure(
                        $this->retryIdentity($order),
                        new IntegrationFailure('invalid_order', FailureCategory::Business, $exception->getMessage()),
                        $now,
                    );
                }
                $result->add($order->name, null, 'invalid', $exception->getMessage());
                continue;
            }

            if (isset($existing[$mapped->externalReference])) {
                if (! $options->dryRun) {
                    $this->resolveRetry($order, $now);
                }
                $result->add(
                    $order->name,
                    $mapped->externalReference,
                    'skipped',
                    'A tracezilla sales order already has this external reference.',
                );
                continue;
            }

            if ($options->dryRun) {
                $existing[$mapped->externalReference] = true;
                $result->add(
                    $order->name,
                    $mapped->externalReference,
                    'would_create',
                    'Would create one tracezilla sales order.',
                );
                continue;
            }

            try {
                $this->tracezilla->createSalesOrder($mapped);
                $this->resolveRetry($order, $now);
                $existing[$mapped->externalReference] = true;
                $result->add(
                    $order->name,
                    $mapped->externalReference,
                    'created',
                    'Created one tracezilla sales order.',
                );
            } catch (Throwable $exception) {
                $message = $this->failureMessage($exception);
                $this->retries?->recordFailure(
                    $this->retryIdentity($order),
                    new IntegrationFailure('tracezilla_order_creation_failed', FailureCategory::Unexpected, $message),
                    $now,
                );
                $result->add($order->name, $mapped->externalReference, 'failed', $message);
            }
        }

        return $result;
    }

    /**
     * Older orders whose retry is due come first, then the orders created in
     * the last --days. Without the older ones, a failure that outlives the
     * --days window would never be retried.
     *
     * @return list<ShopifyOrderData>
     */
    private function ordersToImport(ImportIndividualOrdersOptions $options, DateTimeImmutable $now): array
    {
        $recentOrders = $this->shopify->readCreatedSince($now->modify("-{$options->days} days"));
        $olderOrderIds = $this->dueRetryOrderIds($recentOrders, $now, $options->limit);
        if ($olderOrderIds === []) {
            return $recentOrders;
        }

        $olderOrders = $this->shopify->readByLegacyIds($olderOrderIds);

        return array_merge($olderOrders, $recentOrders);
    }

    /**
     * The Shopify IDs of orders whose retry is due and that are not among the
     * recent orders already.
     *
     * @param list<ShopifyOrderData> $recentOrders
     * @return list<string>
     */
    private function dueRetryOrderIds(array $recentOrders, DateTimeImmutable $now, int $limit): array
    {
        if ($this->retries === null) {
            return [];
        }

        $recentIds = [];
        foreach ($recentOrders as $order) {
            $recentIds[$order->legacyId] = true;
        }

        $ids = [];
        foreach ($this->retries->due($now, $limit) as $task) {
            if ($task->identity->workflow !== self::WORKFLOW) {
                continue;
            }
            if (isset($recentIds[$task->identity->externalId])) {
                continue;
            }
            $ids[] = $task->identity->externalId;
        }

        return $ids;
    }

    /** What went wrong, in tracezilla's own words, for the output and the retry record. */
    private function failureMessage(Throwable $exception): string
    {
        $message = trim($exception->getMessage());
        if ($message === '') {
            return 'tracezilla rejected the sales order without an explanation.';
        }

        return mb_substr($message, 0, self::MAX_ERROR_LENGTH);
    }

    private function retryIdentity(ShopifyOrderData $order): TaskIdentity
    {
        return new TaskIdentity(self::WORKFLOW, 'shopify', $order->legacyId);
    }

    private function resolveRetry(ShopifyOrderData $order, DateTimeImmutable $now): void
    {
        if ($this->retries === null) {
            return;
        }
        $task = $this->retries->find($this->retryIdentity($order)->taskId());
        if ($task !== null) {
            $this->retries->resolve($task, $now);
        }
    }
}
