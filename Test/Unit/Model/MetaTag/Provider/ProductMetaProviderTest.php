<?php

declare(strict_types=1);

namespace MageOS\Seo\Test\Unit\Model\MetaTag\Provider;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type\AbstractType;
use Magento\Framework\Pricing\Amount\AmountInterface;
use Magento\Framework\Pricing\Price\PriceInterface;
use Magento\Framework\Pricing\PriceInfoInterface;
use MageOS\Seo\Model\Catalog\CurrentEntity;
use MageOS\Seo\Model\Config;
use MageOS\Seo\Model\MetaTag\Provider\ProductMetaProvider;
use MageOS\Seo\Model\Product\AvailabilityResolver;
use MageOS\Seo\Model\Product\FinalPrice;
use MageOS\Seo\Test\Unit\Model\Price\SchemaPrices;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Issue #38: Open Graph's product price is the price the structured data would publish, or none.
 */
class ProductMetaProviderTest extends TestCase
{
    use SchemaPrices;

    public function testAKnownPriceIsPublishedWithItsCurrency(): void
    {
        $tags = $this->priceTags($this->provider($this->product('simple', 12.5)));

        $this->assertSame(['product:price:amount' => '12.50', 'product:price:currency' => 'GBP'], $tags);
    }

    public function testAPriceOfZeroIsLeftOut(): void
    {
        // Issue #41: usually a price hidden from the visitor, not a free product.
        $this->assertSame([], $this->priceTags($this->provider($this->product('simple', 0.0))));
    }

    public function testAPriceOfZeroIsPublishedAsFreeWhenZeroPricesArePublished(): void
    {
        $tags = $this->priceTags($this->provider($this->product('simple', 0.0), publishZero: true));

        $this->assertSame(['product:price:amount' => '0.00', 'product:price:currency' => 'GBP'], $tags);
    }

    public function testAnUnknownCompositePriceIsLeftOut(): void
    {
        // A configurable with no salable child is priced 0 by core: unknown, not free.
        $tags = $this->priceTags($this->provider($this->product('configurable', 0.0)));

        $this->assertSame([], $tags);
    }

    public function testAPriceThatCannotBeReadIsLeftOutAndLogged(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $tags = $this->priceTags($this->provider($this->product('simple', null), $logger));

        $this->assertSame([], $tags);
    }

    public function testTheMetaDescriptionIsTheDescription(): void
    {
        // Issue #51: the meta description is written for previews.
        $product = $this->product('simple', 12.5, [
            'meta_description'  => 'Soft cotton tee.',
            'short_description' => '<ul><li>100% cotton</li></ul>',
        ]);

        $this->assertSame('Soft cotton tee.', $this->tag($this->provider($product), 'og:description'));
    }

    public function testTheShortDescriptionIsTheFallback(): void
    {
        $product = $this->product('simple', 12.5, [
            'meta_description'  => '',
            'short_description' => '<p>A <b>soft</b> tee.</p>',
            'description'       => 'Long description.',
        ]);

        $this->assertSame('A soft tee.', $this->tag($this->provider($product), 'og:description'));
    }

    public function testTheDescriptionIsTheLastFallback(): void
    {
        $product = $this->product('simple', 12.5, ['description' => 'Long description.']);

        $this->assertSame('Long description.', $this->tag($this->provider($product), 'og:description'));
    }

    /**
     * The provider over a product page showing the given product.
     *
     * @param Product $product
     * @param LoggerInterface|null $logger
     * @param bool $publishZero Publish a Price of Zero
     * @return ProductMetaProvider
     */
    private function provider(
        Product $product,
        ?LoggerInterface $logger = null,
        bool $publishZero = false
    ): ProductMetaProvider {
        $currentEntity = $this->createStub(CurrentEntity::class);
        $currentEntity->method('getProduct')->willReturn($product);
        $imageHelper = $this->createStub(ImageHelper::class);
        $imageHelper->method('init')->willReturnSelf();
        $imageHelper->method('getUrl')->willReturn('');
        $config = $this->createStub(Config::class);
        $config->method('isOgTagsEnabled')->willReturn(true);
        $config->method('isZeroPricePublished')->willReturn($publishZero);

        $availability = $this->createStub(AvailabilityResolver::class);
        $availability->method('resolve')->willReturn(AvailabilityResolver::IN_STOCK);

        return new ProductMetaProvider(
            $currentEntity,
            $this->schemaPrice('GBP'),
            $imageHelper,
            $config,
            new FinalPrice($logger ?? $this->createStub(LoggerInterface::class), $config),
            $availability
        );
    }

    /**
     * A product with a final price, or with one that throws when read (null).
     *
     * @param string $typeId
     * @param float|null $finalPrice
     * @param array<string, string> $data Attribute values read through getData()
     * @return Product
     */
    private function product(string $typeId, ?float $finalPrice, array $data = []): Product
    {
        $price = $this->createStub(PriceInterface::class);
        if ($finalPrice === null) {
            $price->method('getAmount')->willThrowException(new \RuntimeException('no price index'));
        } else {
            $amount = $this->createStub(AmountInterface::class);
            $amount->method('getValue')->willReturn($finalPrice);
            $price->method('getAmount')->willReturn($amount);
        }
        $priceInfo = $this->createStub(PriceInfoInterface::class);
        $priceInfo->method('getPrice')->willReturn($price);
        $type = $this->createStub(AbstractType::class);
        $type->method('isComposite')->willReturn($typeId === 'configurable');

        $product = $this->createStub(Product::class);
        $product->method('getTypeId')->willReturn($typeId);
        $product->method('getTypeInstance')->willReturn($type);
        $product->method('getPriceInfo')->willReturn($priceInfo);
        $product->method('getName')->willReturn('Tee');
        $product->method('getSku')->willReturn('TEE');
        $product->method('getProductUrl')->willReturn('https://example.com/tee.html');
        $product->method('getData')->willReturnCallback(
            static fn ($key = '') => $data[$key] ?? null
        );

        return $product;
    }

    /**
     * The price tags among the provider's tags: property => content.
     *
     * @param ProductMetaProvider $provider
     * @return array<string, string>
     */
    private function priceTags(ProductMetaProvider $provider): array
    {
        $tags = [];
        foreach ($provider->getMetaTags() as $tag) {
            if (str_starts_with((string) ($tag['property'] ?? ''), 'product:price:')) {
                $tags[(string) $tag['property']] = (string) $tag['content'];
            }
        }

        return $tags;
    }

    /**
     * The content of one Open Graph tag, or null when the provider has none.
     *
     * @param ProductMetaProvider $provider
     * @param string $property
     * @return string|null
     */
    private function tag(ProductMetaProvider $provider, string $property): ?string
    {
        foreach ($provider->getMetaTags() as $tag) {
            if (($tag['property'] ?? '') === $property) {
                return (string) $tag['content'];
            }
        }

        return null;
    }
}
