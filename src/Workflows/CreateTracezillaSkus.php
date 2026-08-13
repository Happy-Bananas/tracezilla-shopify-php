<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Workflows;

use Throwable;
use Tracezilla\Shopify\Contracts\ShopifyVariantReader;
use Tracezilla\Shopify\Contracts\TracezillaSkuGateway;
use Tracezilla\Shopify\Tracezilla\Mappers\ShopifyVariantToTracezillaSkuMapper;

final readonly class CreateTracezillaSkus
{
    public function __construct(
        private ShopifyVariantReader $shopifyVariants,
        private TracezillaSkuGateway $tracezillaSkus,
        private ShopifyVariantToTracezillaSkuMapper $mapper,
    ) {}

    public function run(bool $dryRun = true, int $limit = 10): CreateTracezillaSkusResult
    {
        if ($limit < 1) {
            throw new \InvalidArgumentException('The processing limit must be a positive integer.');
        }

        $variants = $this->shopifyVariants->readVariants();
        $existingCodes = $this->tracezillaSkus->existingSkuCodes();
        $selected = array_slice($variants, 0, $limit);
        $result = new CreateTracezillaSkusResult(
            sourceCount: count($variants),
            existingSkuCount: count($existingCodes),
            selectedCount: count($selected),
            dryRun: $dryRun,
            limit: $limit,
        );

        $existing = array_fill_keys(array_map('trim', $existingCodes), true);
        $seenShopify = [];

        foreach ($selected as $variant) {
            if ($variant->sku === null) {
                $result->add($variant->id, null, 'invalid', 'Shopify variant does not have an SKU.');
                continue;
            }

            $sku = trim($variant->sku);
            if (isset($existing[$sku])) {
                $result->add($variant->id, $sku, 'skipped', 'SKU already exists in tracezilla.');
                continue;
            }
            if (isset($seenShopify[$sku])) {
                $result->add($variant->id, $sku, 'skipped', 'Another Shopify variant in this run has the same SKU.');
                continue;
            }
            $seenShopify[$sku] = true;

            $skuData = $this->mapper->map($variant);
            if ($dryRun) {
                $result->add($variant->id, $sku, 'would_create', 'SKU would be created during execution.');
                continue;
            }

            try {
                $this->tracezillaSkus->createSku($skuData);
                $existing[$sku] = true;
                $result->add($variant->id, $sku, 'created', 'SKU was created in tracezilla.');
            } catch (Throwable) {
                $result->add($variant->id, $sku, 'failed', 'tracezilla rejected the SKU creation request.');
            }
        }

        return $result;
    }
}
