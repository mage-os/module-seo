<?php

declare(strict_types=1);

namespace MageOS\Seo\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use MageOS\Seo\Model\Config;
use MageOS\Seo\Model\Config\Source\SitemapGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What each setting reads, at which scope, and how its raw value becomes the answer.
 *
 * `has_variant_max` and the locale have tests of their own; the llms, feed storage and AI crawler
 * settings are MageOS_Aeo's.
 */
class ConfigTest extends TestCase
{
    private const STORE_ID = 3;

    /**
     * Every read, as [path, scope type, scope code].
     *
     * @var array<int,array{0:string,1:string,2:mixed}>
     */
    private array $reads = [];

    /**
     * A flag reads true from '1' and false from nothing, at the scope it belongs to.
     *
     * @dataProvider flags
     * @param string $method
     * @param string $path
     * @param mixed[] $arguments
     * @param array{0:string,1:mixed} $scope
     * @return void
     */
    #[DataProvider('flags')]
    public function testAFlag(string $method, string $path, array $arguments, array $scope): void
    {
        $this->assertTrue($this->config([$path => '1'])->$method(...$arguments));
        $this->assertSame([$path, ...$scope], end($this->reads));

        $this->assertFalse($this->config([])->$method(...$arguments));
    }

    /**
     * The flags, the arguments they take and the scope each is read at.
     *
     * @return array<string,array{0:string,1:string,2:mixed[],3:array{0:string,1:mixed}}>
     */
    public static function flags(): array
    {
        $store   = [ScopeInterface::SCOPE_STORE, self::STORE_ID];
        $default = [ScopeConfigInterface::SCOPE_TYPE_DEFAULT, null];

        return [
            'og tags'             => ['isOgTagsEnabled', Config::XML_OG_TAGS_ENABLED, [self::STORE_ID], $store],
            'structured data'     => ['isStructuredDataEnabled', Config::XML_SD_ENABLED, [self::STORE_ID], $store],
            'category item list'  => [
                'isCategoryItemListEnabled',
                Config::XML_SD_CATEGORY_ITEM_LIST_ENABLED,
                [self::STORE_ID],
                $store,
            ],
            'aggregate rating'    => [
                'isAggregateRatingEnabled',
                Config::XML_SD_AGGREGATE_RATING_ENABLED,
                [self::STORE_ID],
                $store,
            ],
            'paginated robots'    => [
                'isPaginatedRobotsEnabled',
                Config::XML_ROBOTS_PAGINATED_ENABLED,
                [self::STORE_ID],
                $store,
            ],
            'cms canonical'       => [
                'isCmsCanonicalEnabled',
                Config::XML_CANONICAL_CMS_ENABLED,
                [self::STORE_ID],
                $store,
            ],
            'hreflang'            => ['isHreflangEnabled', Config::XML_HREFLANG_ENABLED, [self::STORE_ID], $store],
            'speakable'           => [
                'isSpeakableEnabled',
                Config::XML_AEO_SPEAKABLE_ENABLED,
                [self::STORE_ID],
                $store,
            ],
            'noindex left out'    => [
                'isSitemapNoindexExcluded',
                Config::XML_SITEMAP_EXCLUDE_NOINDEX,
                [self::STORE_ID],
                $store,
            ],
            'rebuild on change'   => [
                'isSitemapRebuildOnChangeEnabled',
                Config::XML_SITEMAP_REBUILD_ON_CHANGE,
                [self::STORE_ID],
                $store,
            ],
            'language-only codes' => [
                'isHreflangLanguageOnlyEnabled',
                Config::XML_HREFLANG_LANGUAGE_ONLY,
                [],
                $default,
            ],
            'hreflang in sitemap' => ['isHreflangSitemapEnabled', Config::XML_HREFLANG_SITEMAP_ENABLED, [], $default],
            'same website only'   => [
                'isHreflangSameWebsiteOnly',
                Config::XML_HREFLANG_SAME_WEBSITE_ONLY,
                [],
                $default,
            ],
        ];
    }

    /**
     * A text setting returns its value, and '' when it has none.
     *
     * @dataProvider strings
     * @param string $method
     * @param string $path
     * @return void
     */
    #[DataProvider('strings')]
    public function testAString(string $method, string $path): void
    {
        $this->assertSame('NOINDEX,FOLLOW', $this->config([$path => 'NOINDEX,FOLLOW'])->$method(self::STORE_ID));
        $this->assertSame([$path, ScopeInterface::SCOPE_STORE, self::STORE_ID], end($this->reads));

        $this->assertSame('', $this->config([])->$method(self::STORE_ID));
    }

    /**
     * The text settings read at store view scope.
     *
     * @return array<string,array{0:string,1:string}>
     */
    public static function strings(): array
    {
        return [
            'default template'     => ['getDefaultProductTemplate', Config::XML_SD_DEFAULT_TEMPLATE],
            'product robots'       => ['getRobotsProductDefault', Config::XML_ROBOTS_PRODUCT_DEFAULT],
            'category robots'      => ['getRobotsCategoryDefault', Config::XML_ROBOTS_CATEGORY_DEFAULT],
            'cms page robots'      => ['getRobotsCmsDefault', Config::XML_ROBOTS_CMS_DEFAULT],
            'search results robots' => ['getRobotsSearchDefault', Config::XML_ROBOTS_SEARCH_DEFAULT],
            'core default robots'  => ['getRobotsCoreDefault', Config::XML_ROBOTS_CORE_DEFAULT],
            'paginated robots'     => ['getRobotsPaginated', Config::XML_ROBOTS_PAGINATED],
        ];
    }

    /**
     * An X account reads as "@handle" from a handle, "@handle" or a profile URL, and '' otherwise.
     *
     * @dataProvider twitterHandles
     * @param string|null $raw
     * @param string $handle
     * @return void
     */
    #[DataProvider('twitterHandles')]
    public function testAnXAccountIsAHandle(?string $raw, string $handle): void
    {
        foreach (['getTwitterSite' => Config::XML_OG_TWITTER_SITE,
                     'getTwitterCreator' => Config::XML_OG_TWITTER_CREATOR] as $method => $path) {
            $values = $raw === null ? [] : [$path => $raw];
            $this->assertSame($handle, $this->config($values)->$method(self::STORE_ID));
            $this->assertSame([$path, ScopeInterface::SCOPE_STORE, self::STORE_ID], end($this->reads));
        }
    }

    /**
     * @return array<string,array{0:string|null,1:string}>
     */
    public static function twitterHandles(): array
    {
        return [
            'none'            => [null, ''],
            'empty'           => ['  ', ''],
            'bare'            => ['examplestore', '@examplestore'],
            'with @'          => [' @example_store ', '@example_store'],
            'x.com url'       => ['https://x.com/examplestore/', '@examplestore'],
            'twitter.com url' => ['http://www.twitter.com/examplestore', '@examplestore'],
            'too long'        => ['@abcdefghijklmnop', ''],
            'not a username'  => ['@example store', ''],
            'other site'      => ['https://instagram.com/examplestore', ''],
        ];
    }

    public function testTheSitemapGeneratorIsThisModulesOnlyWhenSelected(): void
    {
        $path = Config::XML_SITEMAP_GENERATOR;

        $this->assertTrue($this->config([$path => SitemapGenerator::MAGEOS_SEO])->isSitemapGeneratorEnabled(1));
        $this->assertSame([$path, ScopeInterface::SCOPE_STORE, 1], end($this->reads));
        $this->assertFalse($this->config([$path => SitemapGenerator::MAGENTO])->isSitemapGeneratorEnabled(1));
        $this->assertFalse($this->config([])->isSitemapGeneratorEnabled(1));
    }

    public function testTheCategoryItemListMaxFallsBackTo36AndIsAtLeastOne(): void
    {
        $path = Config::XML_SD_CATEGORY_ITEM_LIST_MAX;

        $this->assertSame(12, $this->config([$path => '12'])->getCategoryItemListMax(self::STORE_ID));
        $this->assertSame([$path, ScopeInterface::SCOPE_STORE, self::STORE_ID], end($this->reads));
        $this->assertSame(36, $this->config([])->getCategoryItemListMax(self::STORE_ID));
        $this->assertSame(36, $this->config([$path => ''])->getCategoryItemListMax(self::STORE_ID));
        $this->assertSame(36, $this->config([$path => '0'])->getCategoryItemListMax(self::STORE_ID));
        $this->assertSame(1, $this->config([$path => '-5'])->getCategoryItemListMax(self::STORE_ID));
    }

    public function testTheInheritanceStrategyIsReadAtDefaultScopeWithADefault(): void
    {
        $path = Config::XML_CATEGORY_INHERITANCE_STRATEGY;

        $this->assertSame('parent_first', $this->config([$path => 'parent_first'])->getCategoryInheritanceStrategy());
        $this->assertSame([$path, ScopeConfigInterface::SCOPE_TYPE_DEFAULT, null], end($this->reads));
        $this->assertSame(Config::DEFAULT_INHERITANCE_STRATEGY, $this->config([])->getCategoryInheritanceStrategy());
        $this->assertSame(
            Config::DEFAULT_INHERITANCE_STRATEGY,
            $this->config([$path => ''])->getCategoryInheritanceStrategy()
        );
    }

    public function testTheXDefaultStoreIsReadPerWebsiteOrAtDefaultScope(): void
    {
        $path = Config::XML_HREFLANG_XDEFAULT_STORE;

        $this->assertSame(4, $this->config([$path => '4'])->getHreflangXDefaultStoreId(2));
        $this->assertSame([$path, ScopeInterface::SCOPE_WEBSITE, 2], end($this->reads));

        $this->assertSame(4, $this->config([$path => '4'])->getHreflangXDefaultStoreId());
        $this->assertSame([$path, ScopeConfigInterface::SCOPE_TYPE_DEFAULT, null], end($this->reads));

        $this->assertSame(0, $this->config([])->getHreflangXDefaultStoreId());
    }

    public function testTheHreflangCodesAreAListTrimmedWithoutEmptyEntries(): void
    {
        $path = Config::XML_HREFLANG_CODES;

        $this->assertSame(['en-GB', 'fr'], $this->config([$path => ' en-GB, ,fr '])->getHreflangCodes(self::STORE_ID));
        $this->assertSame([$path, ScopeInterface::SCOPE_STORE, self::STORE_ID], end($this->reads));
        $this->assertSame([], $this->config([])->getHreflangCodes(self::STORE_ID));
    }

    public function testTheExcludedStoresAreStoreIdsWithoutZero(): void
    {
        $path = Config::XML_HREFLANG_EXCLUDED_STORES;

        $this->assertSame([1, 3], $this->config([$path => ' 1, 3 '])->getHreflangExcludedStoreIds());
        $this->assertSame([$path, ScopeConfigInterface::SCOPE_TYPE_DEFAULT, null], end($this->reads));
        $this->assertSame([2], $this->config([$path => '0,2'])->getHreflangExcludedStoreIds());
        $this->assertSame([], $this->config([$path => ''])->getHreflangExcludedStoreIds());
        $this->assertSame([], $this->config([])->getHreflangExcludedStoreIds());
    }

    public function testTheSpeakableSelectorsAreOnePerLine(): void
    {
        $path = Config::XML_AEO_SPEAKABLE_SELECTORS;

        $this->assertSame(
            ['.a', '.b', '.c'],
            $this->config([$path => ".a\r\n\r\n .b \r.c\n\n"])->getSpeakableCssSelectors(self::STORE_ID)
        );
        $this->assertSame([$path, ScopeInterface::SCOPE_STORE, self::STORE_ID], end($this->reads));
        $this->assertSame([], $this->config([])->getSpeakableCssSelectors(self::STORE_ID));
    }

    public function testTheReturnPolicyUrlIsReadOnlyWhileTheReturnPolicyIsOn(): void
    {
        $enabled = Config::XML_RETURN_POLICY_ENABLED;
        $url     = Config::XML_RETURN_POLICY_URL;

        $this->assertSame(
            'https://shop.test/returns',
            $this->config([$enabled => '1', $url => ' https://shop.test/returns '])->getReturnPolicyUrl(self::STORE_ID)
        );
        $this->assertSame([$url, ScopeInterface::SCOPE_STORE, self::STORE_ID], end($this->reads));
        $this->assertNull(
            $this->config([$enabled => '0', $url => 'https://shop.test/returns'])->getReturnPolicyUrl(self::STORE_ID),
            'A value left behind while the policy is off is not used.'
        );
        $this->assertSame([$enabled, ScopeInterface::SCOPE_STORE, self::STORE_ID], end($this->reads));
        $this->assertNull($this->config([$enabled => '1', $url => ''])->getReturnPolicyUrl(self::STORE_ID));
    }

    /**
     * Config over the given values by path, recording every read in $this->reads.
     *
     * @param array<string,string> $values
     * @return Config
     */
    private function config(array $values): Config
    {
        $this->reads = [];
        $read = function (
            $path,
            $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            $scopeCode = null
        ) use ($values) {
            $this->reads[] = [$path, $scopeType, $scopeCode];

            return $values[$path] ?? null;
        };

        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback($read);
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn (...$arguments): bool => (bool) $read(...$arguments)
        );

        return new Config($scopeConfig);
    }
}
