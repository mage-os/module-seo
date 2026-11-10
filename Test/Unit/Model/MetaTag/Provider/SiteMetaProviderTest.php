<?php

declare(strict_types=1);

namespace MageOS\Seo\Test\Unit\Model\MetaTag\Provider;

use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use MageOS\Seo\Api\Data\OrganizationInterface;
use MageOS\Seo\Api\OrganizationRepositoryInterface;
use MageOS\Seo\Model\Config;
use MageOS\Seo\Model\MetaTag\Provider\SiteMetaProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class SiteMetaProviderTest extends TestCase
{
    /**
     * @var Config&Stub
     */
    private Config&Stub $config;

    /**
     * @var StoreManagerInterface&Stub
     */
    private StoreManagerInterface&Stub $storeManager;

    /**
     * @var OrganizationRepositoryInterface&Stub
     */
    private OrganizationRepositoryInterface&Stub $repository;

    /**
     * @var StoreInterface&Stub
     */
    private StoreInterface&Stub $store;

    /**
     * @var SiteMetaProvider
     */
    private SiteMetaProvider $provider;

    protected function setUp(): void
    {
        $this->config       = $this->createStub(Config::class);
        $this->storeManager = $this->createStub(StoreManagerInterface::class);
        $this->repository   = $this->createStub(OrganizationRepositoryInterface::class);

        $this->store = $this->createStub(StoreInterface::class);
        $this->store->method('getId')->willReturn(1);
        $this->store->method('getName')->willReturn('Store Name');
        $website = $this->createStub(WebsiteInterface::class);
        $website->method('getId')->willReturn(1);
        $this->storeManager->method('getStore')->willReturn($this->store);
        $this->storeManager->method('getWebsite')->willReturn($website);

        $this->provider = new SiteMetaProvider(
            $this->config,
            $this->storeManager,
            $this->repository
        );
    }

    private function withOrgName(string $name): void
    {
        $org = $this->createStub(OrganizationInterface::class);
        $org->method('getName')->willReturn($name);
        $this->repository->method('getForScope')->willReturn($org);
    }

    public function testHandlesEveryPage(): void
    {
        $this->assertSame(['*'], $this->provider->getHandles());
    }

    public function testReturnsEmptyWhenOgDisabled(): void
    {
        $this->config->method('isOgTagsEnabled')->willReturn(false);
        $this->assertSame([], $this->provider->getMetaTags());
    }

    public function testEmitsSiteNameAndLocale(): void
    {
        $this->config->method('isOgTagsEnabled')->willReturn(true);
        $this->withOrgName('Acme Ltd');
        $this->config->method('getLocaleCode')->willReturn('en_GB');

        $this->assertSame(
            [
                ['property' => 'og:site_name', 'content' => 'Acme Ltd'],
                ['property' => 'og:locale', 'content' => 'en_GB'],
            ],
            $this->provider->getMetaTags()
        );
    }

    public function testFallsBackToStoreNameWhenOrgNameEmpty(): void
    {
        $this->config->method('isOgTagsEnabled')->willReturn(true);
        $this->withOrgName('');
        $this->config->method('getLocaleCode')->willReturn('en_GB');

        $tags = $this->provider->getMetaTags();

        $this->assertContains(['property' => 'og:site_name', 'content' => 'Store Name'], $tags);
    }

    public function testOmitsLocaleWhenEmpty(): void
    {
        $this->config->method('isOgTagsEnabled')->willReturn(true);
        $this->withOrgName('Acme Ltd');
        $this->config->method('getLocaleCode')->willReturn('');

        $tags       = $this->provider->getMetaTags();
        $properties = array_column($tags, 'property');

        $this->assertNotContains('og:locale', $properties);
    }

    public function testLeavesTheXCardToTheCompositor(): void
    {
        // The card type depends on the page's image, which only MetaTag\Compositor sees.
        $this->config->method('isOgTagsEnabled')->willReturn(true);
        $this->withOrgName('Acme Ltd');
        $this->config->method('getLocaleCode')->willReturn('en_GB');

        $names = array_column($this->provider->getMetaTags(), 'name');

        $this->assertNotContains('twitter:card', $names);
    }

    public function testEmitsTheXAccountsWhenSet(): void
    {
        $this->config->method('isOgTagsEnabled')->willReturn(true);
        $this->withOrgName('Acme Ltd');
        $this->config->method('getTwitterSite')->willReturn('@acme');
        $this->config->method('getTwitterCreator')->willReturn('@acme_team');

        $tags = $this->provider->getMetaTags();

        $this->assertContains(['name' => 'twitter:site', 'content' => '@acme'], $tags);
        $this->assertContains(['name' => 'twitter:creator', 'content' => '@acme_team'], $tags);
    }

    public function testOmitsTheXAccountsWhenNotSet(): void
    {
        $this->config->method('isOgTagsEnabled')->willReturn(true);
        $this->withOrgName('Acme Ltd');
        $this->config->method('getTwitterSite')->willReturn('');
        $this->config->method('getTwitterCreator')->willReturn('');

        $names = array_column($this->provider->getMetaTags(), 'name');

        $this->assertNotContains('twitter:site', $names);
        $this->assertNotContains('twitter:creator', $names);
    }
}
