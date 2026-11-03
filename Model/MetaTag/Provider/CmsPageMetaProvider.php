<?php

declare(strict_types=1);

namespace MageOS\Seo\Model\MetaTag\Provider;

use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\DataObject;
use Magento\Store\Model\StoreManagerInterface;
use MageOS\Seo\Api\MetaTagProviderInterface;
use MageOS\Seo\Model\Cms\CmsPageResolver;
use MageOS\Seo\Model\Cms\OgImagePath;
use MageOS\Seo\Model\Config;
use Psr\Log\LoggerInterface;

/**
 * A CMS page's Open Graph tags: its URL, and its Open Graph title, description and image.
 *
 * Empty Open Graph fields fall back to the meta title (or title), the meta description and the
 * first image.
 *
 * The Open Graph image is stored as the Media Gallery posts it, and published under the media URL
 * (Model\Cms\OgImagePath). Without one, the image is the first `<img>` in the page's content, as
 * stored. Page Builder and the WYSIWYG editor store its src as a `{{media url=…}}` directive, so
 * that src, and only that, goes through the page filter to become a URL a crawler can fetch (review
 * M10). Filtering the whole content would render every widget on the page a second time, on every
 * uncached request, to find one image.
 */
class CmsPageMetaProvider implements MetaTagProviderInterface
{
    /**
     * The Open Graph cms_page columns.
     */
    public const OG_TITLE       = 'og_title';
    public const OG_DESCRIPTION = 'og_description';
    public const OG_IMAGE       = 'og_image';

    /**
     * @param CmsPageResolver $cmsPageResolver
     * @param Config $seoConfig
     * @param StoreManagerInterface $storeManager
     * @param FilterProvider $filterProvider
     * @param LoggerInterface $logger
     * @param OgImagePath $ogImagePath
     */
    public function __construct(
        private readonly CmsPageResolver       $cmsPageResolver,
        private readonly Config                $seoConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly FilterProvider        $filterProvider,
        private readonly LoggerInterface       $logger,
        private readonly OgImagePath           $ogImagePath
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getHandles(): array
    {
        return ['cms_page_view'];
    }

    /**
     * @inheritdoc
     */
    public function getMetaTags(): array
    {
        if (!$this->seoConfig->isOgTagsEnabled()) {
            return [];
        }

        try {
            $page = $this->cmsPageResolver->resolve();
            if ($page === null) {
                return [];
            }

            // The base URL still prefixes a relative og:image; og:url is the page's own URL.
            $baseUrl = rtrim((string) $this->storeManager->getStore()->getBaseUrl(), '/');
            $url     = $this->cmsPageResolver->currentUrl();

            // The Open Graph title and description are written for previews, so they come first, as
            // written. The meta title and description come next.
            $title = $this->plainText($this->column($page, self::OG_TITLE))
                ?: trim((string) $page->getMetaTitle())
                ?: (string) $page->getTitle();

            $tags = [
                ['property' => 'og:type',  'content' => 'website'],
                ['property' => 'og:title', 'content' => $title],
                ['property' => 'og:url',   'content' => $url],
            ];

            $description = $this->plainText($this->column($page, self::OG_DESCRIPTION))
                ?: mb_substr(trim((string) $page->getMetaDescription()), 0, 160);
            if ($description !== '') {
                $tags[] = ['property' => 'og:description', 'content' => $description];
            }

            $image = $this->ogImagePath->getUrl($this->column($page, self::OG_IMAGE));
            if ($image === '') {
                $image = $this->extractFirstImage((string) $page->getContent());
                if ($image !== '') {
                    $image = trim((string) $this->filterProvider->getPageFilter()->filter($image));
                }
            }
            if ($image !== '') {
                if (!str_starts_with($image, 'http')) {
                    $image = $baseUrl . '/' . ltrim($image, '/');
                }
                $tags[] = ['property' => 'og:image', 'content' => $image];
            }

            return $tags;
        } catch (\Exception $e) {
            // The page renders without Open Graph tags, and the log says why.
            $this->logger->error(
                'MageOS_Seo: could not build the CMS page\'s Open Graph tags: ' . $e->getMessage(),
                ['exception' => $e]
            );

            return [];
        }
    }

    /**
     * A cms_page column that PageInterface has no getter for.
     *
     * @param PageInterface $page
     * @param string $column
     * @return string
     */
    private function column(PageInterface $page, string $column): string
    {
        return $page instanceof DataObject ? (string) $page->getData($column) : '';
    }

    /**
     * A value without its HTML, on one line.
     *
     * @param string $value
     * @return string
     */
    private function plainText(string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', strip_tags($value)));
    }

    /**
     * Extract the first image's src from an HTML string, as stored: possibly a directive.
     *
     * @param string $html
     * @return string
     */
    private function extractFirstImage(string $html): string
    {
        if ($html === '') {
            return '';
        }

        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
            return $matches[1];
        }

        return '';
    }
}
