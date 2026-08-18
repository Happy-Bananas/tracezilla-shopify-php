<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Workflows;

use DateTimeImmutable;
use InvalidArgumentException;
use Throwable;
use Tracezilla\Shopify\Contracts\ShopifyOrderReader;
use Tracezilla\Shopify\Contracts\TracezillaSalesOrderGateway;
use Tracezilla\Shopify\Tracezilla\Mappers\ShopifyOrderToTracezillaSalesOrderMapper;

final readonly class ImportIndividualOrders
{
    public function __construct(
        private ShopifyOrderReader $shopify,
        private TracezillaSalesOrderGateway $tracezilla,
        private ShopifyOrderToTracezillaSalesOrderMapper $mapper,
    ) {}

    public function run(
        ImportIndividualOrdersOptions $options,
        ?DateTimeImmutable $now = null,
    ): ImportIndividualOrdersResult {
        $now ??= new DateTimeImmutable('now');
        $orders = $this->shopify->readCreatedSince($now->modify("-{$options->days} days"));
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

            try {
                $mapped = $this->mapper->map($order, $context);
            } catch (InvalidArgumentException $exception) {
                $result->add($order->name, null, 'invalid', $exception->getMessage());
                continue;
            }

            if (isset($existing[$mapped->externalReference])) {
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
                $existing[$mapped->externalReference] = true;
                $result->add(
                    $order->name,
                    $mapped->externalReference,
                    'created',
                    'Created one tracezilla sales order.',
                );
            } catch (Throwable) {
                $result->add(
                    $order->name,
                    $mapped->externalReference,
                    'failed',
                    'tracezilla rejected the sales-order creation request.',
                );
            }
        }

        return $result;
    }
}
