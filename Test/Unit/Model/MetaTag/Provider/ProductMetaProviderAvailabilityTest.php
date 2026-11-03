<?php

declare(strict_types=1);

namespace MageOS\Seo\Test\Unit\Model\MetaTag\Provider;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Framework\View\ConfigInterface as ViewConfig;
use MageOS\Seo\Api\SchemaPriceInterface;
use MageOS\Seo\Model\Catalog\CurrentEntity;
use MageOS\Seo\Model\Config;
use MageOS\Seo\Model\MetaTag\Provider\ProductMetaProvider;
use MageOS\Seo\Model\Product\AvailabilityResolver;
use MageOS\Seo\Model\Product\FinalPrice;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Review low item: Open Graph's product:availability says what the structured data says, read from
 * the same stock index, in the values Meta's catalog reference lists for Open Graph ("in stock",
 * "out of stock", "available for order", "discontinued"). It read isSalable() before, so the two
 * could disagree, and wrote "instock" and "oos".
 */
class ProductMetaProviderAvailabilityTest extends TestCase
{
    /**
     * @dataProvider availabilities
     * @param string $availability
     * @param string $expected
     * @return void
     */
    #[DataProvider('availabilities')]
    public function testAvailabilityComesFromTheStockIndexInMetasValues(string $availability, string $expected): void
    {
        $this->assertSame($expected, $this->availabilityTag($this->provider($availability)));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function availabilities(): array
    {
        return [
            'in stock'     => [AvailabilityResolver::IN_STOCK, 'in stock'],
            'backorder'    => [AvailabilityResolver::BACKORDER, 'available for order'],
            'out of stock' => [AvailabilityResolver::OUT_OF_STOCK, 'out of stock'],
        ];
    }

    /**
     * The product is salable by core's own reckoning, but the stock index, which the structured
     * data reads, says it is out of stock: both say so.
     *
     * @return void
     */
    public function testIsSalableIsNotAsked(): void
    {
        $this->assertSame('out of stock', $this->availabilityTag($this->provider(AvailabilityResolver::OUT_OF_STOCK)));
    }

    /**
     * Decision D8a: a stock lookup that failed leaves the tag out.
     *
     * @return void
     */
    public function testAnUnknownAvailabilityLeavesTheTagOut(): void
    {
        $this->assertNull($this->availabilityTag($this->provider(null)));
    }

    /**
     * The provider over a product page whose product the stock index gives the availability.
     *
     * @param string|null $availability
     * @return ProductMetaProvider
     */
    private function provider(?string $availability): ProductMetaProvider
    {
        $product = $this->createStub(Product::class);
        $product->method('getName')->willReturn('Tee');
        $product->method('getSku')->willReturn('TEE');
        $product->method('getProductUrl')->willReturn('https://example.com/tee.html');
        // Core would call it salable: the tag must not be read from this.
        $product->method('isSalable')->willReturn(true);

        $currentEntity = $this->createStub(CurrentEntity::class);
        $currentEntity->method('getProduct')->willReturn($product);
        $imageHelper = $this->createStub(ImageHelper::class);
        $imageHelper->method('init')->willReturnSelf();
        $imageHelper->method('getUrl')->willReturn('');
        $config = $this->createStub(Config::class);
        $config->method('isOgTagsEnabled')->willReturn(true);
        $finalPrice = $this->createStub(FinalPrice::class);
        $finalPrice->method('get')->willReturn(null);
        $resolver = $this->createStub(AvailabilityResolver::class);
        $resolver->method('resolve')->willReturn($availability);

        return new ProductMetaProvider(
            $currentEntity,
            $this->createStub(SchemaPriceInterface::class),
            $imageHelper,
            $config,
            $finalPrice,
            $resolver,
            $this->createStub(ViewConfig::class)
        );
    }

    /**
     * The content of the provider's product:availability tag, or null when there is none.
     *
     * @param ProductMetaProvider $provider
     * @return string|null
     */
    private function availabilityTag(ProductMetaProvider $provider): ?string
    {
        foreach ($provider->getMetaTags() as $tag) {
            if (($tag['property'] ?? '') === 'product:availability') {
                return (string) $tag['content'];
            }
        }

        return null;
    }
}
