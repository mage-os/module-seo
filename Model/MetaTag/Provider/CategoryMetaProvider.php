<?php

declare(strict_types=1);

namespace MageOS\Seo\Model\MetaTag\Provider;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Category\Image as CategoryImage;
use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\Store\Model\StoreManagerInterface;
use MageOS\Seo\Api\MetaTagProviderInterface;
use MageOS\Seo\Model\Cms\OgImagePath;
use MageOS\Seo\Model\Config;

class CategoryMetaProvider implements MetaTagProviderInterface
{
    /**
     * The Open Graph attributes.
     */
    public const OG_TITLE       = 'og_title';
    public const OG_DESCRIPTION = 'og_description';
    public const OG_IMAGE       = 'og_image';

    /**
     * @param LayerResolver $layerResolver
     * @param Config $seoConfig
     * @param StoreManagerInterface $storeManager
     * @param CategoryImage $categoryImage
     */
    public function __construct(
        private readonly LayerResolver         $layerResolver,
        private readonly Config                $seoConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly CategoryImage         $categoryImage
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getHandles(): array
    {
        return ['catalog_category_view'];
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
            $category = $this->layerResolver->get()->getCurrentCategory();
            if (!$category) {
                return [];
            }

            // The Open Graph title and description are written for previews, so they come first, as
            // written. The meta title and description come next. The description is often empty or a
            // Page Builder layout, so it is the last fallback.
            $title = $this->plainText((string) $category->getData(self::OG_TITLE))
                ?: trim((string) $category->getData('meta_title'))
                ?: (string) $category->getName();

            $tags = [
                ['property' => 'og:type',  'content' => 'website'],
                ['property' => 'og:title', 'content' => $title],
                ['property' => 'og:url',   'content' => $category->getUrl()],
            ];

            $description = $this->description($category);
            if ($description !== '') {
                $tags[] = ['property' => 'og:description', 'content' => $description];
            }

            // The Open Graph image, else the category image, as stored.
            $image = $this->categoryImage->getUrl($category, self::OG_IMAGE)
                ?: $this->categoryImage->getUrl($category, 'image');
            if ($image !== '') {
                // Ensure the URL is absolute: a path from the web root is returned as is
                if (!str_starts_with($image, 'http')) {
                    $baseUrl = rtrim((string) $this->storeManager->getStore()->getBaseUrl(), '/');
                    $image = $baseUrl . '/' . ltrim($image, '/');
                }
                $tags[] = ['property' => 'og:image', 'content' => $image];
            }

            return $tags;
        } catch (\Exception) {
            return [];
        }
    }

    /**
     * The Open Graph description as written. Else the meta description or description, truncated.
     *
     * @param Category $category
     * @return string
     */
    private function description(Category $category): string
    {
        $ogDescription = $this->plainText((string) $category->getData(self::OG_DESCRIPTION));
        if ($ogDescription !== '') {
            return $ogDescription;
        }

        $raw = trim((string) $category->getData('meta_description')) ?: (string) $category->getData('description');
        // Strip PageBuilder <style> blocks before strip_tags
        $clean = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $raw) ?? $raw;

        return mb_substr($this->plainText($clean), 0, 160);
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
}
