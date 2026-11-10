<?php

declare(strict_types=1);

namespace MageOS\Seo\Model\MetaTag\Provider;

use Magento\Catalog\Helper\Image as ImageHelper;
use MageOS\Seo\Api\MetaTagProviderInterface;
use MageOS\Seo\Api\SchemaPriceInterface;
use MageOS\Seo\Model\Catalog\CurrentEntity;
use MageOS\Seo\Model\Config;
use MageOS\Seo\Model\Product\AvailabilityResolver;
use MageOS\Seo\Model\Product\FinalPrice;

class ProductMetaProvider implements MetaTagProviderInterface
{
    /**
     * schema.org availability => Open Graph's product:availability, in the values Meta's catalog
     * reference lists for Open Graph ("in stock", "out of stock", "available for order",
     * "discontinued"). A backorder can be bought, but only to be supplied later: available for order.
     */
    private const OG_AVAILABILITY = [
        AvailabilityResolver::IN_STOCK     => 'in stock',
        AvailabilityResolver::BACKORDER    => 'available for order',
        AvailabilityResolver::OUT_OF_STOCK => 'out of stock',
    ];

    /**
     * @param CurrentEntity $currentEntity
     * @param SchemaPriceInterface $schemaPrice
     * @param ImageHelper $imageHelper
     * @param Config $seoConfig
     * @param FinalPrice $finalPrice
     * @param AvailabilityResolver $availabilityResolver
     */
    public function __construct(
        private readonly CurrentEntity        $currentEntity,
        private readonly SchemaPriceInterface $schemaPrice,
        private readonly ImageHelper          $imageHelper,
        private readonly Config               $seoConfig,
        private readonly FinalPrice           $finalPrice,
        private readonly AvailabilityResolver $availabilityResolver
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getHandles(): array
    {
        return ['catalog_product_view'];
    }

    /**
     * @inheritdoc
     */
    public function getMetaTags(): array
    {
        if (!$this->seoConfig->isOgTagsEnabled()) {
            return [];
        }

        $product = $this->currentEntity->getProduct();
        if (!$product) {
            return [];
        }
        /** @var \Magento\Catalog\Model\Product $product */

        $title = $product->getName();

        // The meta description is written for previews; the short description is often a list or
        // a spec block, so it is only the fallback.
        $description = mb_substr(
            trim(strip_tags(
                (string) $product->getData('meta_description')
                ?: (string) $product->getData('short_description')
                ?: (string) $product->getData('description')
            )),
            0,
            160
        );

        $url = $product->getProductUrl();

        // Image
        $imageUrl = '';
        try {
            $imageUrl = (string) $this->imageHelper
                ->init($product, 'product_page_image_large')
                ->getUrl();
        } catch (\Exception) { // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock.DetectedCatch -- no image, leave empty
        }

        // The price the structured data publishes, or none (issue #38): an unknown price — a
        // composite nothing can price, or a lookup that failed, which FinalPrice logs — is left out
        // rather than written as 0.00, which reads as free. FinalPrice's amount is already in the
        // display currency.
        $amount   = $this->finalPrice->get($product);
        $price    = $amount === null ? '' : $this->schemaPrice->amount($amount);
        $currency = $this->schemaPrice->currencyCode();

        $tags = [
            ['property' => 'og:type',        'content' => 'product'],
            ['property' => 'og:title',       'content' => $title],
            ['property' => 'og:url',         'content' => $url],
        ];

        if ($description !== '') {
            $tags[] = ['property' => 'og:description', 'content' => $description];
        }

        if ($imageUrl !== '') {
            $tags[] = ['property' => 'og:image', 'content' => $imageUrl];
        }

        if ($price !== '') {
            $tags[] = ['property' => 'product:price:amount',   'content' => $price];
            $tags[] = ['property' => 'product:price:currency', 'content' => $currency];
        }

        // From the stock index the structured data reads, so the two never disagree (isSalable() could:
        // review low item). A lookup that failed is left out, not published as out of stock (D8a).
        $availability = $this->availabilityResolver->resolve($product);
        if ($availability !== null && isset(self::OG_AVAILABILITY[$availability])) {
            $tags[] = ['property' => 'product:availability', 'content' => self::OG_AVAILABILITY[$availability]];
        }

        return $tags;
    }
}
