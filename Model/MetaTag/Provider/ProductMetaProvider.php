<?php

declare(strict_types=1);

namespace MageOS\Seo\Model\MetaTag\Provider;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Framework\View\ConfigInterface as ViewConfig;
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
     * The Open Graph attributes and image role.
     */
    public const OG_TITLE       = 'og_title';
    public const OG_DESCRIPTION = 'og_description';
    public const OG_IMAGE       = 'og_image';

    /**
     * The Open Graph image's id in view.xml, under the module's images.
     */
    public const OG_IMAGE_ID = 'mageos_seo_og_image';

    /**
     * The view.xml module that holds catalog image sizes.
     */
    private const OG_IMAGE_MODULE = 'Magento_Catalog';

    /**
     * An image role's value when no image has it.
     */
    private const NO_SELECTION = 'no_selection';

    /**
     * The catalog image helper's resized image path, under the media URL.
     */
    private const RESIZED_IMAGE_PATH = '/catalog/product/cache/';

    /**
     * @param CurrentEntity $currentEntity
     * @param SchemaPriceInterface $schemaPrice
     * @param ImageHelper $imageHelper
     * @param Config $seoConfig
     * @param FinalPrice $finalPrice
     * @param AvailabilityResolver $availabilityResolver
     * @param ViewConfig $viewConfig
     */
    public function __construct(
        private readonly CurrentEntity        $currentEntity,
        private readonly SchemaPriceInterface $schemaPrice,
        private readonly ImageHelper          $imageHelper,
        private readonly Config               $seoConfig,
        private readonly FinalPrice           $finalPrice,
        private readonly AvailabilityResolver $availabilityResolver,
        private readonly ViewConfig           $viewConfig
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
        /** @var Product $product */

        // The Open Graph title and description are written for previews, so they come first, as
        // written. The meta title and description come next. Then the name, the short description
        // and the description.
        $title = $this->plainText((string) $product->getData(self::OG_TITLE))
            ?: trim((string) $product->getData('meta_title'))
            ?: (string) $product->getName();

        $description = $this->description($product);

        $url = $product->getProductUrl();

        // The Open Graph role image, else the base image. Resized when the setting is on.
        $resized         = $this->seoConfig->isOgImageResized();
        $imageAttributes = $this->imageAttributes($product);
        $imageUrl        = '';
        try {
            $imageUrl = (string) $this->imageHelper
                ->init($product, $resized ? self::OG_IMAGE_ID : 'product_page_image_large', $imageAttributes)
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
            // Only a resized image has a known size. A missing image gets the placeholder, which is
            // not resized.
            $size = $resized && $this->isResizedImage($imageUrl) ? $this->getOgImageExactSize() : null;
            if ($size !== null) {
                $tags[] = ['property' => 'og:image:width',  'content' => (string) $size['width']];
                $tags[] = ['property' => 'og:image:height', 'content' => (string) $size['height']];
            }
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

    /**
     * The product's Open Graph description.
     *
     * The Open Graph Description as written. Else the first of the meta description, short description
     * and description, without HTML or Page Builder styles, truncated to 160 characters.
     *
     * @param Product $product
     * @return string
     */
    private function description(Product $product): string
    {
        $ogDescription = $this->plainText((string) $product->getData(self::OG_DESCRIPTION));
        if ($ogDescription !== '') {
            return $ogDescription;
        }

        foreach (['meta_description', 'short_description', 'description'] as $attribute) {
            $raw = (string) $product->getData($attribute);
            // Strip Page Builder <style> blocks before strip_tags
            $text = $this->plainText(preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $raw) ?? $raw);
            if ($text !== '') {
                return mb_substr($text, 0, 160);
            }
        }

        return '';
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
     * The image helper's attributes for the product's Open Graph image.
     *
     * The Open Graph role when an image has it. Else the image id's own role, the base image.
     *
     * @param Product $product
     * @return array<string, string>
     */
    private function imageAttributes(Product $product): array
    {
        $ogImage = (string) $product->getData(self::OG_IMAGE);

        return $ogImage !== '' && $ogImage !== self::NO_SELECTION ? ['type' => self::OG_IMAGE] : [];
    }

    /**
     * The Open Graph image's attributes in the current theme's view.xml: width, height, frame, etc.
     *
     * @return array<string, mixed>
     */
    private function getOgImageViewAttributes(): array
    {
        return (array) $this->viewConfig->getViewConfig()->getMediaAttributes(
            self::OG_IMAGE_MODULE,
            ImageHelper::MEDIA_TYPE_CONFIG_NODE,
            self::OG_IMAGE_ID
        );
    }

    /**
     * The exact size of a resized image, or null when unknown.
     *
     * A framed image keeps its proportions on a canvas of exactly that size. Without a frame, sizes
     * vary, so none is published.
     *
     * @return array{width: int, height: int}|null
     */
    private function getOgImageExactSize(): ?array
    {
        $attributes = $this->getOgImageViewAttributes();
        $width      = (int) ($attributes['width'] ?? 0);
        $height     = (int) ($attributes['height'] ?? 0);
        $frame      = $attributes['frame'] ?? null;
        if ($width <= 0 || $height <= 0 || !\in_array($frame, [true, 1, '1', 'true'], true)) {
            return null;
        }

        return ['width' => $width, 'height' => $height];
    }

    /**
     * Whether an image helper URL is a resized image, not the placeholder.
     *
     * Resized product images are served from the product image cache. The placeholder is not.
     *
     * @param string $url
     * @return bool
     */
    private function isResizedImage(string $url): bool
    {
        return str_contains($url, self::RESIZED_IMAGE_PATH);
    }
}
