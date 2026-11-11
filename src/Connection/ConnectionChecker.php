<?php

declare(strict_types=1);

namespace Tracezilla\Shopify\Connection;

use Throwable;

/**
 * Checks that the Shopify and tracezilla credentials work, with one small
 * read-only request each. Both are always checked, so a failure in one does
 * not hide the state of the other.
 */
final class ConnectionChecker
{
    public function __construct(
        private ShopifyConnection $shopify,
        private TracezillaConnection $tracezilla,
        private string $teamSlug,
    ) {
    }

    /** @return array<string, mixed> */
    public function check(): array
    {
        $shopify = $this->checkShopify();
        $tracezilla = $this->checkTracezilla();

        return $this->summary($shopify, $tracezilla);
    }

    /** @return array<string, mixed> */
    private function checkShopify(): array
    {
        try {
            $answer = $this->shopify->shop();
        } catch (Throwable $exception) {
            return $this->failure($exception->getMessage());
        }

        $shop = $this->shopFrom($answer);
        if ($shop === null) {
            return $this->failure('Shopify returned no shop.');
        }

        return [
            'connected' => true,
            'shop_name' => trim($shop['name']),
            'domain' => $this->textOrNull($shop, 'myshopifyDomain'),
        ];
    }

    /** @return array<string, mixed> */
    private function checkTracezilla(): array
    {
        $result = ['team' => $this->teamSlug];

        try {
            $page = $this->tracezilla->firstSku();
        } catch (Throwable $exception) {
            $result['connected'] = false;
            $result['error'] = $exception->getMessage();

            return $result;
        }

        if (! $this->hasSkuList($page)) {
            $result['connected'] = false;
            $result['error'] = 'tracezilla returned no data collection.';

            return $result;
        }

        $result['connected'] = true;

        return $result;
    }

    /**
     * @param array<string, mixed> $shopify
     * @param array<string, mixed> $tracezilla
     * @return array<string, mixed>
     */
    private function summary(array $shopify, array $tracezilla): array
    {
        $status = 'failed';
        if ($shopify['connected'] && $tracezilla['connected']) {
            $status = 'ok';
        }

        return [
            'status' => $status,
            'shopify' => $shopify,
            'tracezilla' => $tracezilla,
        ];
    }

    /**
     * The shop in Shopify's answer, or null if it has no shop with a name.
     *
     * @param array<string, mixed> $answer
     * @return array<string, mixed>|null
     */
    private function shopFrom(array $answer): ?array
    {
        if (! isset($answer['data']['shop'])) {
            return null;
        }
        $shop = $answer['data']['shop'];
        if (! is_array($shop)) {
            return null;
        }
        if ($this->textOrNull($shop, 'name') === null) {
            return null;
        }
        if (trim($shop['name']) === '') {
            return null;
        }

        return $shop;
    }

    /**
     * Whether tracezilla answered with a list of SKUs, even an empty one.
     *
     * @param array<string, mixed> $page
     */
    private function hasSkuList(array $page): bool
    {
        if (! isset($page['data'])) {
            return false;
        }

        return is_array($page['data']);
    }

    /** @return array{connected: false, error: string} */
    private function failure(string $message): array
    {
        return ['connected' => false, 'error' => $message];
    }

    /** @param array<string, mixed> $values */
    private function textOrNull(array $values, string $key): ?string
    {
        if (isset($values[$key]) && is_string($values[$key])) {
            return $values[$key];
        }

        return null;
    }
}
