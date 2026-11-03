<?php

declare(strict_types=1);

namespace MageOS\Seo\Test\Unit\Model\MetaTag\Provider;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type\AbstractType;
use Magento\Framework\Config\View;
use Magento\Framework\Pricing\Amount\AmountInterface;
use Magento\Framework\Pricing\Price\PriceInterface;
use Magento\Framework\Pricing\PriceInfoInterface;
use Magento\Framework\View\ConfigInterface as ViewConfig;
use MageOS\Seo\Model\Catalog\CurrentEntity;
use MageOS\Seo\Model\Config;
use MageOS\Seo\Model\MetaTag\Provider\ProductMetaProvider;
use MageOS\Seo\Model\Product\AvailabilityResolver;
use MageOS\Seo\Model\Product\FinalPrice;
use MageOS\Seo\Test\Unit\Model\Price\SchemaPrices;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Issue #38: Open Graph's product price is the price the structured data would publish, or none.
 *
 * The title, description and image come from the Open Graph attributes first. The title and
 * description then fall back to the meta title and description, then to the name, the description
 * and the short description. The image falls back to the base image.
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

    public function testTheOpenGraphTitleAndDescriptionComeFirst(): void
    {
        $tags = $this->textTags($this->provider($this->product('simple', 10.0, [
            'og_title'          => ' The <em>Tee</em> ',
            'meta_title'        => 'Buy the Tee',
            'og_description'    => str_repeat('c', 200),
            'meta_description'  => 'Written for search results.',
        ])));

        $this->assertSame('The Tee', $tags['og:title'] ?? null);
        $this->assertSame(str_repeat('c', 200), $tags['og:description'] ?? null, 'As written, not shortened.');
    }

    public function testTheImageWithTheOpenGraphRoleIsTheImage(): void
    {
        $imageHelper = $this->createMock(ImageHelper::class);
        $imageHelper->expects($this->once())->method('init')
            ->with($this->anything(), 'product_page_image_large', ['type' => 'og_image'])
            ->willReturnSelf();
        $imageHelper->method('getUrl')->willReturn('https://example.com/media/og.jpg');

        $provider = $this->provider(
            $this->product('simple', 10.0, ['og_image' => '/t/e/tee-og.jpg']),
            imageHelper: $imageHelper
        );

        $this->assertContains(
            ['property' => 'og:image', 'content' => 'https://example.com/media/og.jpg'],
            $provider->getMetaTags()
        );
    }

    /**
     * @return array<string, array{0: array<string, string>}>
     */
    public static function noOpenGraphImage(): array
    {
        return [
            'never set'    => [[]],
            'no selection' => [['og_image' => 'no_selection']],
        ];
    }

    /**
     * @param array<string, string> $data
     */
    #[DataProvider('noOpenGraphImage')]
    public function testTheBaseImageIsTheImageWithoutAnOpenGraphImage(array $data): void
    {
        $imageHelper = $this->createMock(ImageHelper::class);
        $imageHelper->expects($this->once())->method('init')
            ->with($this->anything(), 'product_page_image_large', [])
            ->willReturnSelf();
        $imageHelper->method('getUrl')->willReturn('https://example.com/media/base.jpg');

        $tags = $this->provider($this->product('simple', 10.0, $data), imageHelper: $imageHelper)->getMetaTags();

        $this->assertContains(['property' => 'og:image', 'content' => 'https://example.com/media/base.jpg'], $tags);
    }

    public function testAtTheOpenGraphSizeTheImageIsResizedAndItsSizePublished(): void
    {
        $url         = 'https://example.com/media/catalog/product/cache/abc/t/e/tee.jpg';
        $imageHelper = $this->createMock(ImageHelper::class);
        $imageHelper->expects($this->once())->method('init')
            ->with($this->anything(), 'mageos_seo_og_image', ['type' => 'og_image'])
            ->willReturnSelf();
        $imageHelper->method('getUrl')->willReturn($url);

        $tags = $this->imageTags($this->provider(
            $this->product('simple', 10.0, ['og_image' => '/t/e/tee.jpg']),
            imageHelper: $imageHelper,
            resized: true
        ));

        $this->assertSame(['og:image' => $url, 'og:image:width' => '1200', 'og:image:height' => '630'], $tags);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function unknownSizes(): array
    {
        return [
            'no frame'    => [['width' => '1200', 'height' => '630', 'frame' => 'false']],
            'no height'   => [['width' => '1200', 'frame' => 'true']],
            'no settings' => [[]],
        ];
    }

    /**
     * Without a frame or a size in view.xml, the resized image's size is unknown.
     *
     * @param array<string, mixed> $attributes
     * @return void
     */
    #[DataProvider('unknownSizes')]
    public function testAResizedImageOfUnknownSizeIsPublishedWithoutASize(array $attributes): void
    {
        $url         = 'https://example.com/media/catalog/product/cache/abc/t/e/tee.jpg';
        $imageHelper = $this->createStub(ImageHelper::class);
        $imageHelper->method('init')->willReturnSelf();
        $imageHelper->method('getUrl')->willReturn($url);

        $tags = $this->imageTags($this->provider(
            $this->product('simple', 10.0),
            imageHelper: $imageHelper,
            resized: true,
            ogImageAttributes: $attributes
        ));

        $this->assertSame(['og:image' => $url], $tags);
    }

    public function testThePlaceholderIsPublishedWithoutASize(): void
    {
        // No image, or a missing file: the helper gives the placeholder, which is not resized.
        $url         = 'https://example.com/static/frontend/Magento_Catalog/images/product/placeholder/image.jpg';
        $imageHelper = $this->createStub(ImageHelper::class);
        $imageHelper->method('init')->willReturnSelf();
        $imageHelper->method('getUrl')->willReturn($url);

        $tags = $this->imageTags($this->provider(
            $this->product('simple', 10.0, ['image' => '/t/e/missing.jpg']),
            imageHelper: $imageHelper,
            resized: true
        ));

        $this->assertSame(['og:image' => $url], $tags);
    }

    public function testWithoutTheOpenGraphSizeTheImageIsPublishedAsBeforeWithoutASize(): void
    {
        $url         = 'https://example.com/media/catalog/product/cache/def/t/e/tee.jpg';
        $imageHelper = $this->createMock(ImageHelper::class);
        $imageHelper->expects($this->once())->method('init')
            ->with($this->anything(), 'product_page_image_large', [])
            ->willReturnSelf();
        $imageHelper->method('getUrl')->willReturn($url);

        $tags = $this->imageTags($this->provider($this->product('simple', 10.0), imageHelper: $imageHelper));

        $this->assertSame(['og:image' => $url], $tags);
    }

    public function testTheMetaTitleIsTheTitle(): void
    {
        $tags = $this->textTags($this->provider($this->product('simple', 10.0, ['meta_title' => ' Buy the Tee '])));

        $this->assertSame('Buy the Tee', $tags['og:title'] ?? null);
    }

    public function testTheNameIsTheTitleWithoutAMetaTitle(): void
    {
        $tags = $this->textTags($this->provider($this->product('simple', 10.0, ['meta_title' => '  '])));

        $this->assertSame('Tee', $tags['og:title'] ?? null);
    }

    public function testTheMetaDescriptionComesFirst(): void
    {
        $tags = $this->textTags($this->provider($this->product('simple', 10.0, [
            'meta_description'  => 'Written for previews.',
            'short_description' => '<ul><li>100% cotton</li></ul>',
            'description'       => '<p>The long story.</p>',
        ])));

        $this->assertSame('Written for previews.', $tags['og:description'] ?? null);
    }

    public function testTheShortDescriptionFollowsAnEmptyMetaDescription(): void
    {
        $tags = $this->textTags($this->provider($this->product('simple', 10.0, [
            'meta_description'  => ' ',
            'short_description' => '<p>Soft   cotton
                tee.</p>',
            'description'       => '<p>The long story.</p>',
        ])));

        $this->assertSame('Soft cotton tee.', $tags['og:description'] ?? null);
    }

    public function testTheDescriptionIsTheLastFallback(): void
    {
        $tags = $this->textTags($this->provider($this->product('simple', 10.0, [
            'short_description' => '<style>#x { color: red; }</style>',
            'description'       => '<style>#x { color: red; }</style><p>' . str_repeat('a', 200) . '</p>',
        ])));

        $this->assertSame(str_repeat('a', 160), $tags['og:description'] ?? null);
    }

    public function testNoDescriptionTagWithoutAnyDescription(): void
    {
        $tags = $this->textTags($this->provider($this->product('simple', 10.0)));

        $this->assertArrayNotHasKey('og:description', $tags);
    }

    /**
     * The provider over a product page showing the given product.
     *
     * @param Product $product
     * @param LoggerInterface|null $logger
     * @param bool $publishZero Publish a Price of Zero
     * @param ImageHelper|null $imageHelper
     * @param bool $resized Publish Images at the Open Graph Size
     * @param array<string, mixed> $ogImageAttributes The mageos_seo_og_image image in view.xml
     * @return ProductMetaProvider
     */
    private function provider(
        Product $product,
        ?LoggerInterface $logger = null,
        bool $publishZero = false,
        ?ImageHelper $imageHelper = null,
        bool $resized = false,
        array $ogImageAttributes = ['width' => '1200', 'height' => '630', 'frame' => 'true']
    ): ProductMetaProvider {
        $currentEntity = $this->createStub(CurrentEntity::class);
        $currentEntity->method('getProduct')->willReturn($product);
        if ($imageHelper === null) {
            $imageHelper = $this->createStub(ImageHelper::class);
            $imageHelper->method('init')->willReturnSelf();
            $imageHelper->method('getUrl')->willReturn('');
        }
        $config = $this->createStub(Config::class);
        $config->method('isOgTagsEnabled')->willReturn(true);
        $config->method('isZeroPricePublished')->willReturn($publishZero);
        $config->method('isOgImageResized')->willReturn($resized);
        $view = $this->createStub(View::class);
        $view->method('getMediaAttributes')->willReturnCallback(
            static fn (string $module, string $mediaType, string $mediaId) =>
                [$module, $mediaType, $mediaId] === ['Magento_Catalog', 'images', 'mageos_seo_og_image']
                    ? $ogImageAttributes
                    : []
        );
        $viewConfig = $this->createStub(ViewConfig::class);
        $viewConfig->method('getViewConfig')->willReturn($view);

        $availability = $this->createStub(AvailabilityResolver::class);
        $availability->method('resolve')->willReturn(AvailabilityResolver::IN_STOCK);

        return new ProductMetaProvider(
            $currentEntity,
            $this->schemaPrice('GBP'),
            $imageHelper,
            $config,
            new FinalPrice($logger ?? $this->createStub(LoggerInterface::class), $config),
            $availability,
            $viewConfig
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
            static fn (string $key = '') => $data[$key] ?? null
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
     * The og:title and og:description among the provider's tags: property => content.
     *
     * @param ProductMetaProvider $provider
     * @return array<string, string>
     */
    private function textTags(ProductMetaProvider $provider): array
    {
        $tags = [];
        foreach ($provider->getMetaTags() as $tag) {
            if (\in_array($tag['property'] ?? null, ['og:title', 'og:description'], true)) {
                $tags[(string) $tag['property']] = (string) $tag['content'];
            }
        }

        return $tags;
    }

    /**
     * The og:image tags among the provider's tags: property => content.
     *
     * @param ProductMetaProvider $provider
     * @return array<string, string>
     */
    private function imageTags(ProductMetaProvider $provider): array
    {
        $tags = [];
        foreach ($provider->getMetaTags() as $tag) {
            if (str_starts_with((string) ($tag['property'] ?? ''), 'og:image')) {
                $tags[(string) $tag['property']] = (string) $tag['content'];
            }
        }

        return $tags;
    }
}
