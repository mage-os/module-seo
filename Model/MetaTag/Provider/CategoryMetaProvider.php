<?php

declare(strict_types=1);

namespace MageOS\Seo\Model\MetaTag\Provider;

use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\Store\Model\StoreManagerInterface;
use MageOS\Seo\Api\MetaTagProviderInterface;
use MageOS\Seo\Model\Config;

class CategoryMetaProvider implements MetaTagProviderInterface
{
    /**
     * @param LayerResolver $layerResolver
     * @param Config $seoConfig
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly LayerResolver         $layerResolver,
        private readonly Config                $seoConfig,
        private readonly StoreManagerInterface $storeManager
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

            $tags = [
                ['property' => 'og:type',  'content' => 'website'],
                ['property' => 'og:title', 'content' => $category->getName()],
                ['property' => 'og:url',   'content' => $category->getUrl()],
            ];

            // The meta description is written for previews; the description is often empty, or a
            // banner or Page Builder layout, so it is only the fallback.
            $rawDescription = trim((string) $category->getData('meta_description'))
                ?: (string) $category->getData('description');
            if ($rawDescription !== '') {
                // Strip PageBuilder <style> blocks before strip_tags
                $clean = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $rawDescription) ?? $rawDescription;
                $clean = trim((string) preg_replace('/\s+/', ' ', strip_tags($clean)));
                $description = mb_substr($clean, 0, 160);
                if ($description !== '') {
                    $tags[] = ['property' => 'og:description', 'content' => $description];
                }
            }

            $image = (string) $category->getImageUrl();
            if ($image !== '') {
                // Ensure the URL is absolute — getImageUrl() can return a relative path
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
}
