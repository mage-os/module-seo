<?php

declare(strict_types=1);

namespace MageOS\Seo\Model\MetaTag\Provider;

use Magento\Store\Model\StoreManagerInterface;
use MageOS\Seo\Api\MetaTagProviderInterface;
use MageOS\Seo\Api\OrganizationRepositoryInterface;
use MageOS\Seo\Model\Config;

/**
 * Site-level Open Graph meta tags emitted on every page.
 *
 * Adds the document-wide tags the per-page providers don't set: og:site_name, og:locale, and the
 * store's X (Twitter) accounts as twitter:site and twitter:creator when they are configured. The X
 * card tags are not set here: the card type depends on whether the page has an image, which only
 * MetaTag\Compositor sees, so it adds them after collecting every provider's tags.
 */
class SiteMetaProvider implements MetaTagProviderInterface
{
    /**
     * @param Config $seoConfig
     * @param StoreManagerInterface $storeManager
     * @param OrganizationRepositoryInterface $organizationRepository
     */
    public function __construct(
        private readonly Config                          $seoConfig,
        private readonly StoreManagerInterface           $storeManager,
        private readonly OrganizationRepositoryInterface $organizationRepository
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getHandles(): array
    {
        return ['*'];
    }

    /**
     * @inheritdoc
     */
    public function getMetaTags(): array
    {
        if (!$this->seoConfig->isOgTagsEnabled()) {
            return [];
        }

        $store     = $this->storeManager->getStore();
        $storeId   = (int) $store->getId();
        $websiteId = (int) $this->storeManager->getWebsite()->getId();
        $org       = $this->organizationRepository->getForScope($storeId, $websiteId);

        $siteName = $org->getName() ?: (string) $store->getName();
        $locale   = $this->seoConfig->getLocaleCode($storeId);

        $tags = [];
        if ($siteName !== '') {
            $tags[] = ['property' => 'og:site_name', 'content' => $siteName];
        }
        if ($locale !== '') {
            $tags[] = ['property' => 'og:locale', 'content' => $locale];
        }

        $twitterSite    = $this->seoConfig->getTwitterSite($storeId);
        $twitterCreator = $this->seoConfig->getTwitterCreator($storeId);
        if ($twitterSite !== '') {
            $tags[] = ['name' => 'twitter:site', 'content' => $twitterSite];
        }
        if ($twitterCreator !== '') {
            $tags[] = ['name' => 'twitter:creator', 'content' => $twitterCreator];
        }

        return $tags;
    }
}
