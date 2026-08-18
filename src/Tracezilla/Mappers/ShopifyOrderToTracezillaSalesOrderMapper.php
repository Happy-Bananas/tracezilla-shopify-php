<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Tracezilla\Mappers;

use DateTimeImmutable;
use InvalidArgumentException;
use Tracezilla\Shopify\Shopify\ShopifyOrderData;
use Tracezilla\Shopify\Tracezilla\TracezillaOrderContextData;
use Tracezilla\Shopify\Tracezilla\TracezillaSalesOrderData;

final class ShopifyOrderToTracezillaSalesOrderMapper
{
    /* Example business rules: review them for every customer implementation. */
    public const ORDER_REFERENCE_PREFIX = 'SHP';
    public const SUPPORTED_CURRENCY = 'DKK';
    public const EXCHANGE_RATE = 100;

    public function map(
        ShopifyOrderData $order,
        TracezillaOrderContextData $context,
    ): TracezillaSalesOrderData {
        if ($order->currency !== self::SUPPORTED_CURRENCY) {
            throw new InvalidArgumentException(
                'Example mapping only supports '.self::SUPPORTED_CURRENCY."; order uses {$order->currency}."
            );
        }
        if ($order->shippingAddress === null) {
            throw new InvalidArgumentException('Shopify order has no shipping address.');
        }

        $lines = $this->summarizeLines($order);
        $date = (new DateTimeImmutable($order->createdAt))->format('Y-m-d');
        $externalReference = self::ORDER_REFERENCE_PREFIX.$order->legacyId;

        return new TracezillaSalesOrderData(
            externalReference: $externalReference,
            shopifyOrderName: $order->name,
            orderHeader: array_filter([
                'ext_ref' => $externalReference,
                'marking' => $order->purchaseOrderNumber,
                'delivery_notify_cell' => $order->phone,
                'delivery_notify_email' => $order->email,
                'remark' => $order->note,
                'currency' => self::SUPPORTED_CURRENCY,
                'exchange_rate' => self::EXCHANGE_RATE,
                'order_date' => $date,
                'pickup_date' => $date,
                'delivery_date' => $date,
                'owned_by_id' => $context->ownerId,
                'status' => 'from_edi',
                'partners' => [
                    'customer' => [
                        'partner_id' => $context->customerPartnerId,
                        'location_id' => $context->customerLocationId,
                    ],
                    'pickup_from' => [
                        'partner_id' => $context->warehousePartnerId,
                        'location_id' => $context->warehouseLocationId,
                    ],
                    'deliver_to' => [
                        'partner_id' => $context->customerPartnerId,
                        'location' => $this->deliveryLocation($order->shippingAddress),
                    ],
                ],
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            lines: $lines,
        );
    }

    private function summarizeLines(ShopifyOrderData $order): array
    {
        $summaries = [];

        foreach ($order->lines as $line) {
            if (! $line->isReportable()) {
                throw new InvalidArgumentException(
                    'Shopify order contains a line without a usable SKU, quantity, price, or currency.'
                );
            }
            if ($line->currency !== $order->currency) {
                throw new InvalidArgumentException('Shopify order line currency does not match the order.');
            }

            $sku = (string) $line->sku;
            $summaries[$sku]['quantity'] = ($summaries[$sku]['quantity'] ?? 0) + $line->quantity;
            $summaries[$sku]['revenue'] = ($summaries[$sku]['revenue'] ?? 0.0)
                + ($line->quantity * (float) $line->unitPrice);
        }

        if ($summaries === []) {
            throw new InvalidArgumentException('Shopify order has no importable SKU lines.');
        }

        return array_values(array_map(
            static fn (array $summary, string $sku): array => [
                'sku_code' => $sku,
                'quantity' => $summary['quantity'],
                'unit_price' => round($summary['revenue'] / $summary['quantity'], 4),
                'price_per' => 'stock_unit',
            ],
            $summaries,
            array_keys($summaries),
        ));
    }

    private function deliveryLocation(array $address): array
    {
        $company = trim((string) ($address['company'] ?? ''));
        $name = trim((string) ($address['name'] ?? ''));
        $recipient = $company !== '' ? $company : $name;

        foreach (['address1', 'zip', 'city', 'countryCodeV2'] as $field) {
            if (trim((string) ($address[$field] ?? '')) === '') {
                throw new InvalidArgumentException("Shopify shipping-address field [{$field}] is required.");
            }
        }
        if ($recipient === '') {
            throw new InvalidArgumentException('Shopify shipping address has no recipient name.');
        }

        return array_filter([
            'name' => $recipient,
            'recipient_name' => $recipient,
            'address' => $address['address1'],
            'address_line_2' => $address['address2'] ?? null,
            'zip' => $address['zip'],
            'city' => $address['city'],
            'state' => $address['province'] ?? null,
            'state_code' => $address['provinceCode'] ?? null,
            'country' => $address['countryCodeV2'],
            'phone' => $address['phone'] ?? null,
            'contact' => $company !== '' && $name !== '' ? $name : null,
            'is_person' => $company === '',
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
