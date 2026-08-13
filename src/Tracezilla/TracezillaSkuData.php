<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Tracezilla;

use InvalidArgumentException;

final readonly class TracezillaSkuData
{
    public function __construct(
        public string $skuCode,
        public string $globalName,
        public float $weightFactorNet,
        public float $weightFactorGross,
        public string $unitOfMeasure,
        public string $lotUnit,
        public float $defaultUomConversion,
    ) {
        foreach (['skuCode', 'globalName', 'unitOfMeasure', 'lotUnit'] as $property) {
            if (trim($this->{$property}) === '') {
                throw new InvalidArgumentException("Tracezilla SKU property [{$property}] must not be blank.");
            }
        }
    }

    public function toApiPayload(): array
    {
        return [
            'sku_code' => $this->skuCode,
            'global_name' => $this->globalName,
            'weight_factor_net' => $this->weightFactorNet,
            'weight_factor_gross' => $this->weightFactorGross,
            'unit_of_measure' => $this->unitOfMeasure,
            'lot_unit' => $this->lotUnit,
            'default_uom_conversion' => $this->defaultUomConversion,
        ];
    }
}
