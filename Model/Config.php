<?php

declare(strict_types=1);

namespace MageOS\Seo\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use MageOS\Seo\Api\StorefrontSettingsInterface;
use MageOS\Seo\Model\Config\Source\SitemapGenerator;

class Config implements StorefrontSettingsInterface
{
    public const XML_OG_TAGS_ENABLED               = 'mageos_seo_general/og_tags/enabled';
    public const XML_OG_TWITTER_SITE               = 'mageos_seo_general/og_tags/twitter_site';
    public const XML_OG_TWITTER_CREATOR            = 'mageos_seo_general/og_tags/twitter_creator';
    public const XML_SD_ENABLED                    = 'mageos_seo_general/structured_data/enabled';
    public const XML_SD_DEFAULT_TEMPLATE           = 'mageos_seo_general/structured_data/default_product_template';
    public const XML_SD_CATEGORY_ITEM_LIST_ENABLED = 'mageos_seo_general/structured_data/category_item_list_enabled';
    public const XML_SD_CATEGORY_ITEM_LIST_MAX     = 'mageos_seo_general/structured_data/category_item_list_max';
    public const XML_SD_HAS_VARIANT_MAX            = 'mageos_seo_general/structured_data/has_variant_max';
    public const XML_SD_AGGREGATE_RATING_ENABLED   = 'mageos_seo_general/structured_data/aggregate_rating_enabled';
    public const XML_SD_PUBLISH_ZERO_PRICE         = 'mageos_seo_general/structured_data/publish_zero_price';
    public const XML_SD_REMOVE_THEME_MICRODATA     = 'mageos_seo_general/structured_data/remove_theme_microdata';

    public const XML_CATEGORY_INHERITANCE_STRATEGY = 'mageos_seo_general/category_config/inheritance_strategy';

    /**
     * The strategy used when configuration names none, and the one shipped as the default.
     */
    public const DEFAULT_INHERITANCE_STRATEGY = 'category_first';
    public const XML_ROBOTS_PRODUCT_DEFAULT        = 'mageos_seo_general/robots_meta/product_default';
    public const XML_ROBOTS_CATEGORY_DEFAULT       = 'mageos_seo_general/robots_meta/category_default';
    public const XML_ROBOTS_CMS_DEFAULT            = 'mageos_seo_general/robots_meta/cms_page_default';
    public const XML_ROBOTS_SEARCH_DEFAULT         = 'mageos_seo_general/robots_meta/search_default';
    public const XML_ROBOTS_PAGINATED_ENABLED      = 'mageos_seo_general/robots_meta/paginated_enabled';
    public const XML_ROBOTS_PAGINATED              = 'mageos_seo_general/robots_meta/paginated_robots';

    /**
     * Core's Design → Search Engine Robots, which a page keeps when this module has no directive.
     */
    public const XML_ROBOTS_CORE_DEFAULT           = 'design/search_engine_robots/default_robots';

    /**
     * Core's General → Locale Options → Locale (Directory\Helper\Data::XML_PATH_DEFAULT_LOCALE).
     */
    public const XML_LOCALE_CODE                   = 'general/locale/code';
    public const XML_CANONICAL_CMS_ENABLED         = 'mageos_seo_general/canonical/cms_enabled';
    public const XML_HREFLANG_ENABLED              = 'mageos_seo_general/hreflang/enabled';
    public const XML_HREFLANG_XDEFAULT_STORE       = 'mageos_seo_general/hreflang/xdefault_store_id';
    public const XML_HREFLANG_EXCLUDED_STORES      = 'mageos_seo_general/hreflang/excluded_store_ids';
    public const XML_HREFLANG_LANGUAGE_ONLY        = 'mageos_seo_general/hreflang/language_only_enabled';
    public const XML_HREFLANG_SITEMAP_ENABLED      = 'mageos_seo_general/hreflang/sitemap_enabled';
    public const XML_HREFLANG_SAME_WEBSITE_ONLY    = 'mageos_seo_general/hreflang/same_website_only';
    public const XML_HREFLANG_CODES                = 'mageos_seo_general/hreflang/codes';
    public const XML_SITEMAP_GENERATOR             = 'sitemap/generate/mageos_seo_generator';
    public const XML_SITEMAP_EXCLUDE_NOINDEX       = 'sitemap/generate/mageos_seo_exclude_noindex';
    public const XML_SITEMAP_REBUILD_ON_CHANGE     = 'sitemap/generate/mageos_seo_rebuild_on_change';
    public const XML_AEO_SPEAKABLE_ENABLED         = 'mageos_seo_general/aeo/speakable_enabled';
    public const XML_AEO_SPEAKABLE_SELECTORS       = 'mageos_seo_general/aeo/speakable_css_selectors';
    public const XML_RETURN_POLICY_ENABLED         = 'mageos_seo_merchant/return/enabled';
    public const XML_RETURN_POLICY_URL             = 'mageos_seo_merchant/return/policy_url';

    /**
     * Initialize Config with scope configuration.
     *
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Check if Open Graph tags output is enabled.
     *
     * @param int|string|null $storeId
     * @return bool
     */
    public function isOgTagsEnabled(int|string|null $storeId = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::XML_OG_TAGS_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * The store's X (Twitter) account for twitter:site, as "@handle", or '' when none is set.
     *
     * @param int|string|null $storeId
     * @return string
     */
    public function getTwitterSite(int|string|null $storeId = null): string
    {
        return $this->twitterHandle(self::XML_OG_TWITTER_SITE, $storeId);
    }

    /**
     * The X (Twitter) account for twitter:creator, as "@handle", or '' when none is set.
     *
     * @param int|string|null $storeId
     * @return string
     */
    public function getTwitterCreator(int|string|null $storeId = null): string
    {
        return $this->twitterHandle(self::XML_OG_TWITTER_CREATOR, $storeId);
    }

    /**
     * Read an X account setting as "@handle".
     *
     * Accepts "handle", "@handle" or a profile URL (x.com or twitter.com). Anything that isn't a
     * valid X username (1-15 letters, digits or underscores) gives '', so no broken tag is output.
     *
     * @param string $path
     * @param int|string|null $storeId
     * @return string
     */
    private function twitterHandle(string $path, int|string|null $storeId): string
    {
        $value = trim((string) $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId));
        $value = (string) preg_replace('#^(?:https?://)?(?:www\.|mobile\.)?(?:x|twitter)\.com/#i', '', $value);
        $value = ltrim(rtrim($value, '/'), '@');

        return preg_match('/^\w{1,15}$/', $value) ? '@' . $value : '';
    }

    /**
     * Check if structured data output is enabled.
     *
     * @param int|string|null $storeId
     * @return bool
     */
    public function isStructuredDataEnabled(int|string|null $storeId = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::XML_SD_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Return the default product schema template code.
     *
     * @param int|string|null $storeId
     * @return string
     */
    public function getDefaultProductTemplate(int|string|null $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_SD_DEFAULT_TEMPLATE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if the category ItemList schema block is enabled.
     *
     * @param int|string|null $storeId
     * @return bool
     */
    public function isCategoryItemListEnabled(int|string|null $storeId = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::XML_SD_CATEGORY_ITEM_LIST_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Return the maximum number of products to include in a category ItemList.
     *
     * @param int|string|null $storeId
     * @return int
     */
    public function getCategoryItemListMax(int|string|null $storeId = null): int
    {
        return max(1, (int) ($this->scopeConfig->getValue(
            self::XML_SD_CATEGORY_ITEM_LIST_MAX,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) ?: 36));
    }

    /**
     * Return the code of the configured category inheritance strategy.
     *
     * Read at default scope and takes no store ID: this decides how store-view scope itself is
     * resolved against the category tree, so letting it vary per store view would mean the rule
     * for choosing between scopes depended on the scope it was choosing.
     *
     * @return string
     */
    public function getCategoryInheritanceStrategy(): string
    {
        $configured = (string) $this->scopeConfig->getValue(self::XML_CATEGORY_INHERITANCE_STRATEGY);

        return $configured !== '' ? $configured : self::DEFAULT_INHERITANCE_STRATEGY;
    }

    /**
     * Return how many sellable children a configurable may have and still be a ProductGroup.
     *
     * One with more is described as a Product with an AggregateOffer. 0 turns variants off:
     * every configurable gets the AggregateOffer. Unset reads as the default, 50.
     *
     * @param int|string|null $storeId
     * @return int
     */
    public function getHasVariantMax(int|string|null $storeId = null): int
    {
        $value = $this->scopeConfig->getValue(self::XML_SD_HAS_VARIANT_MAX, ScopeInterface::SCOPE_STORE, $storeId);
        if ($value === null || $value === '') {
            return 50;
        }

        return max(0, (int) $value);
    }

    /**
     * Whether a simple product priced 0 is published as free.
     *
     * Off by default: a price of 0 is usually one hidden from the visitor (B2B and trade stores), and
     * Google's merchant listings refuse it. Model\Product\FinalPrice reads it for the structured
     * data, Open Graph and MageOS_Aeo's llms.jsonl alike.
     *
     * @param int|string|null $storeId
     * @return bool
     */
    public function isZeroPricePublished(int|string|null $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_SD_PUBLISH_ZERO_PRICE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Check if AggregateRating output on product schema is enabled.
     *
     * @param int|string|null $storeId
     * @return bool
     */
    public function isAggregateRatingEnabled(int|string|null $storeId = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::XML_SD_AGGREGATE_RATING_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Whether the theme's Product microdata is removed from product pages this module describes.
     *
     * @param int|string|null $storeId
     * @return bool
     */
    public function isThemeMicrodataRemoved(int|string|null $storeId = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::XML_SD_REMOVE_THEME_MICRODATA,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Return the default robots meta value for product pages.
     *
     * @param int|string|null $storeId
     * @return string
     */
    public function getRobotsProductDefault(int|string|null $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_ROBOTS_PRODUCT_DEFAULT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Return the default robots meta value for category pages.
     *
     * @param int|string|null $storeId
     * @return string
     */
    public function getRobotsCategoryDefault(int|string|null $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_ROBOTS_CATEGORY_DEFAULT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Return the default robots meta value for CMS pages.
     *
     * @param int|string|null $storeId
     * @return string
     */
    public function getRobotsCmsDefault(int|string|null $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_ROBOTS_CMS_DEFAULT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Return the default robots meta value for search result pages (quick and advanced search).
     *
     * Empty means no opinion: core's Design → Search Engine Robots applies, as for the other
     * page-type defaults.
     *
     * @param int|string|null $storeId
     * @return string
     */
    public function getRobotsSearchDefault(int|string|null $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_ROBOTS_SEARCH_DEFAULT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Core's default robots directive for a store view (Design → Search Engine Robots).
     *
     * @param int $storeId
     * @return string
     */
    public function getRobotsCoreDefault(int $storeId): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_ROBOTS_CORE_DEFAULT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check whether a dedicated robots meta is applied to paginated listing pages (?p=N, N>1).
     *
     * @param int|string|null $storeId
     * @return bool
     */
    public function isPaginatedRobotsEnabled(int|string|null $storeId = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::XML_ROBOTS_PAGINATED_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Return the robots meta value applied to paginated listing pages (?p=N, N>1).
     *
     * @param int|string|null $storeId
     * @return string
     */
    public function getRobotsPaginated(int|string|null $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_ROBOTS_PAGINATED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if CMS pages, the home page included, get a canonical link.
     *
     * @param int|string|null $storeId
     * @return bool
     */
    public function isCmsCanonicalEnabled(int|string|null $storeId = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::XML_CANONICAL_CMS_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Check if hreflang alternate output is enabled.
     *
     * @param int|string|null $storeId
     * @return bool
     */
    public function isHreflangEnabled(int|string|null $storeId = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::XML_HREFLANG_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Return the store view ID to advertise as hreflang x-default (0 = none).
     *
     * Read at website scope: on an installation with several websites — a .com and a .co.uk, say —
     * each has its own idea of which store view a visitor matching no alternate should land on.
     * A website with no value of its own inherits the default-scope one.
     *
     * @param int|null $websiteId null reads the default scope
     * @return int
     */
    public function getHreflangXDefaultStoreId(?int $websiteId = null): int
    {
        if ($websiteId === null) {
            return (int) $this->scopeConfig->getValue(self::XML_HREFLANG_XDEFAULT_STORE);
        }

        return (int) $this->scopeConfig->getValue(
            self::XML_HREFLANG_XDEFAULT_STORE,
            ScopeInterface::SCOPE_WEBSITE,
            $websiteId
        );
    }

    /**
     * Return the hreflang codes configured for a store view, as stored.
     *
     * None when it relies on its locale. Saved through the admin they are already checked and
     * normalised; set any other way they may not be. Model\Hreflang\StoreLocaleMap checks them as it
     * reads them (Hreflang\CodeList).
     *
     * @param int $storeId
     * @return string[]
     */
    public function getHreflangCodes(int $storeId): array
    {
        $raw = (string) $this->scopeConfig->getValue(
            self::XML_HREFLANG_CODES,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            static fn (string $code): bool => $code !== ''
        ));
    }

    /**
     * Return a store view's locale code (e.g. en_GB), or '' when none is configured.
     *
     * The one place this module reads a store view's locale: hreflang, og:locale and the llms
     * documents all take it from here. It is configuration, not a property of the store: Store has
     * no locale of its own.
     *
     * @param int $storeId
     * @return string
     */
    public function getLocaleCode(int $storeId): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_LOCALE_CODE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Return store view IDs excluded from all hreflang output.
     *
     * @return int[]
     */
    public function getHreflangExcludedStoreIds(): array
    {
        $raw = (string) $this->scopeConfig->getValue(self::XML_HREFLANG_EXCLUDED_STORES);
        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $id): int => (int) trim($id),
            explode(',', $raw)
        )));
    }

    /**
     * Check if automatic language-only hreflang tags are enabled.
     *
     * @return bool
     */
    public function isHreflangLanguageOnlyEnabled(): bool
    {
        return (bool) $this->scopeConfig->getValue(self::XML_HREFLANG_LANGUAGE_ONLY);
    }

    /**
     * Whether this module generates the store view's sitemaps, rather than Magento.
     *
     * @param int $storeId
     * @return bool
     */
    public function isSitemapGeneratorEnabled(int $storeId): bool
    {
        return $this->scopeConfig->getValue(
            self::XML_SITEMAP_GENERATOR,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ) === SitemapGenerator::MAGEOS_SEO;
    }

    /**
     * Whether this module's generator leaves out pages whose robots directive is NOINDEX.
     *
     * @param int $storeId
     * @return bool
     */
    public function isSitemapNoindexExcluded(int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_SITEMAP_EXCLUDE_NOINDEX,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Whether a change to what the store view's sitemaps list rebuilds them through the queue.
     *
     * @param int $storeId
     * @return bool
     */
    public function isSitemapRebuildOnChangeEnabled(int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_SITEMAP_REBUILD_ON_CHANGE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Whether the MageOS SEO sitemap generator lists each URL's hreflang alternates beside it.
     *
     * @return bool
     */
    public function isHreflangSitemapEnabled(): bool
    {
        return (bool) $this->scopeConfig->getValue(self::XML_HREFLANG_SITEMAP_ENABLED);
    }

    /**
     * Check if hreflang alternates are limited to stores of the current website.
     *
     * @return bool
     */
    public function isHreflangSameWebsiteOnly(): bool
    {
        return (bool) $this->scopeConfig->getValue(self::XML_HREFLANG_SAME_WEBSITE_ONLY);
    }

    /**
     * Check if Speakable structured data output is enabled.
     *
     * @param int|string|null $storeId
     * @return bool
     */
    public function isSpeakableEnabled(int|string|null $storeId = null): bool
    {
        return (bool) $this->scopeConfig->getValue(
            self::XML_AEO_SPEAKABLE_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Return the configured Speakable CSS selectors (one per line in admin).
     *
     * @param int|string|null $storeId
     * @return string[]
     */
    public function getSpeakableCssSelectors(int|string|null $storeId = null): array
    {
        $raw = (string) $this->scopeConfig->getValue(
            self::XML_AEO_SPEAKABLE_SELECTORS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw) ?: [])));
    }

    /**
     * The store view's returns policy page, or null while the return policy is off or names none.
     *
     * The URL is only shown in the admin while the return policy is on, so a value left behind
     * when it was switched off is not used. Read by the offers' merchantReturnLink and by
     * MageOS_Aeo's Policies section in /llms.txt.
     *
     * @param int $storeId
     * @return string|null
     */
    public function getReturnPolicyUrl(int $storeId): ?string
    {
        if (!$this->scopeConfig->isSetFlag(self::XML_RETURN_POLICY_ENABLED, ScopeInterface::SCOPE_STORE, $storeId)) {
            return null;
        }

        $url = trim((string) $this->scopeConfig->getValue(
            self::XML_RETURN_POLICY_URL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));

        return $url === '' ? null : $url;
    }
}
