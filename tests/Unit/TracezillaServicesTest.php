<?php

declare(strict_types=1);

namespace Tests\Unit;

use Bifrost\Core\Tracezilla\Client as TracezillaClient;
use Bifrost\Core\Tracezilla\OAuthTokens;
use Bifrost\Core\Tracezilla\TokenProvider;
use Bifrost\Core\Tracezilla\TokenStore;
use Bifrost\Core\Tracezilla\TracezillaConfiguration;
use DateTimeImmutable;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Tracezilla\Shopify\Tracezilla\Mappers\TracezillaInventoryMapper;
use Tracezilla\Shopify\Tracezilla\Mappers\TracezillaSkuMapper;
use Tracezilla\Shopify\Tracezilla\TracezillaCatalogService;
use Tracezilla\Shopify\Tracezilla\TracezillaInventoryService;
use Tracezilla\Shopify\Tracezilla\TracezillaSalesOrderService;

/**
 * The tracezilla services work with the client from bifrost-core.
 */
final class TracezillaServicesTest extends TestCase
{
    private string $directory;
    private MockHandler $api;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/tz-services-'.bin2hex(random_bytes(4));
        $this->api = new MockHandler();
    }

    protected function tearDown(): void
    {
        $files = glob($this->directory.'/*');
        if ($files !== false) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
        @rmdir($this->directory);
    }

    public function test_the_catalog_service_reads_every_page_of_skus(): void
    {
        $this->api->append(
            new Response(200, [], '{"data":[{"id":"1","sku_code":"TZ-A"}],"links":{"next_page":"https://tracezilla.test/api/v1/team/skus?page=2"}}'),
            new Response(200, [], '{"data":[{"id":"2","sku_code":"TZ-B"}],"links":{"next_page":null}}'),
        );

        $codes = (new TracezillaCatalogService($this->client(), new TracezillaSkuMapper()))->existingSkuCodes();

        self::assertSame(['TZ-A', 'TZ-B'], $codes);
    }

    public function test_every_service_accepts_the_client(): void
    {
        $client = $this->client();

        self::assertInstanceOf(TracezillaInventoryService::class, new TracezillaInventoryService($client, new TracezillaInventoryMapper()));
        self::assertInstanceOf(TracezillaSalesOrderService::class, new TracezillaSalesOrderService($client));
    }

    private function client(): TracezillaClient
    {
        $store = new TokenStore($this->directory.'/token.json');
        $store->save(new OAuthTokens('access', 'refresh', new DateTimeImmutable('+300 days')));

        return new TracezillaClient(
            new TracezillaConfiguration('https://tracezilla.test', 'team', 'id', 'secret', $store->path(), 5, 5),
            new TokenProvider(new HttpClient(['handler' => HandlerStack::create(new MockHandler())]), $store, 'id', 'secret'),
            new HttpClient(['handler' => HandlerStack::create($this->api), 'base_uri' => 'https://tracezilla.test/api/v1/team/']),
        );
    }
}
