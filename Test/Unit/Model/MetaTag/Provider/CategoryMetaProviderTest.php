<?php

declare(strict_types=1);

namespace MageOS\Seo\Test\Unit\Model\MetaTag\Provider;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Category\Image as CategoryImage;
use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use MageOS\Seo\Model\Config;
use MageOS\Seo\Model\MetaTag\Provider\CategoryMetaProvider;
use PHPUnit\Framework\TestCase;

/**
 * A category's og:title, og:description and og:image come from its Open Graph attributes first.
 * The title and description then fall back to the meta title and description, then to the name and
 * description. The image falls back to the category image.
 */
class CategoryMetaProviderTest extends TestCase
{
    private const BASE_URL = 'https://example.com/';

    /**
     * @return void
     */
    public function testTheOpenGraphTitleAndDescriptionComeFirst(): void
    {
        $tags = $this->tags([
            'og_title'         => '<b>Women</b>  at Luma',
            'meta_title'       => 'Women\'s Clothing',
            'og_description'   => str_repeat('d', 200),
            'meta_description' => 'Dresses, tops and more.',
        ]);

        $this->assertSame('Women at Luma', $tags['og:title'] ?? null);
        $this->assertSame(str_repeat('d', 200), $tags['og:description'] ?? null, 'As written, not shortened.');
    }

    /**
     * @return void
     */
    public function testTheOpenGraphImageComesBeforeTheCategoryImage(): void
    {
        $tags = $this->tags([], [
            'og_image' => 'https://example.com/media/catalog/category/og.jpg',
            'image'    => 'https://example.com/media/catalog/category/banner.jpg',
        ]);

        $this->assertSame('https://example.com/media/catalog/category/og.jpg', $tags['og:image'] ?? null);
    }

    /**
     * @return void
     */
    public function testTheCategoryImageIsTheImageWithoutAnOpenGraphImage(): void
    {
        $tags = $this->tags([], ['image' => '/media/catalog/category/banner.jpg']);

        $this->assertSame('https://example.com/media/catalog/category/banner.jpg', $tags['og:image'] ?? null);
    }

    /**
     * A Media Gallery rendition is published as picked.
     *
     * @return void
     */
    public function testAGalleryRenditionIsPublishedAsPicked(): void
    {
        $url  = 'https://example.com/media/.renditions/wysiwyg/og.jpg';
        $tags = $this->tags([], ['og_image' => $url]);

        $this->assertSame($url, $tags['og:image'] ?? null);
    }

    /**
     * Category images are never resized, even with Publish Images at the Open Graph Size on.
     *
     * @return void
     */
    public function testTheImageIsPublishedAtItsOriginalUrlWithoutASize(): void
    {
        $tags = $this->tags([], ['og_image' => 'https://example.com/media/catalog/category/og.jpg'], true);

        $this->assertSame('https://example.com/media/catalog/category/og.jpg', $tags['og:image'] ?? null);
        $this->assertArrayNotHasKey('og:image:width', $tags);
        $this->assertArrayNotHasKey('og:image:height', $tags);
    }

    /**
     * @return void
     */
    public function testNoImageTagWithoutAnImage(): void
    {
        $this->assertArrayNotHasKey('og:image', $this->tags([]));
    }

    /**
     * @return void
     */
    public function testTheMetaTitleIsTheTitle(): void
    {
        $tags = $this->tags(['meta_title' => ' Women\'s Clothing ']);

        $this->assertSame('Women\'s Clothing', $tags['og:title'] ?? null);
    }

    /**
     * @return void
     */
    public function testTheNameIsTheTitleWithoutAMetaTitle(): void
    {
        $this->assertSame('Women', $this->tags([])['og:title'] ?? null);
    }

    /**
     * @return void
     */
    public function testTheMetaDescriptionComesFirst(): void
    {
        $tags = $this->tags([
            'meta_description' => 'Dresses, tops and more.',
            'description'      => '<p>Our women\'s range.</p>',
        ]);

        $this->assertSame('Dresses, tops and more.', $tags['og:description'] ?? null);
    }

    /**
     * A category with a meta description but no description gets an og:description.
     *
     * @return void
     */
    public function testTheMetaDescriptionIsUsedWithoutADescription(): void
    {
        $tags = $this->tags(['meta_description' => 'Dresses, tops and more.']);

        $this->assertSame('Dresses, tops and more.', $tags['og:description'] ?? null);
    }

    /**
     * @return void
     */
    public function testTheDescriptionFollowsAnEmptyMetaDescriptionWithoutItsStyles(): void
    {
        $tags = $this->tags([
            'meta_description' => '  ',
            'description'      => '<style>#x{color:red}</style><div>Our   women\'s
                range.</div>',
        ]);

        $this->assertSame('Our women\'s range.', $tags['og:description'] ?? null);
    }

    /**
     * @return void
     */
    public function testNoDescriptionTagWithoutAnyDescription(): void
    {
        $this->assertArrayNotHasKey('og:description', $this->tags([]));
    }

    /**
     * The provider's tags for a category with the given attribute values: property => content.
     *
     * @param array<string, string> $data
     * @param array<string, string> $imageUrls The URL of each image attribute's image
     * @param bool $resized Publish Images at the Open Graph Size
     * @return array<string, string>
     */
    private function tags(array $data, array $imageUrls = [], bool $resized = false): array
    {
        $category = $this->createStub(Category::class);
        $category->method('getName')->willReturn('Women');
        $category->method('getUrl')->willReturn(self::BASE_URL . 'women.html');
        $category->method('getData')->willReturnCallback(
            static fn (string $key = '') => $data[$key] ?? null
        );
        $layer = $this->createStub(Layer::class);
        $layer->method('getCurrentCategory')->willReturn($category);
        $layerResolver = $this->createStub(LayerResolver::class);
        $layerResolver->method('get')->willReturn($layer);
        $config = $this->createStub(Config::class);
        $config->method('isOgTagsEnabled')->willReturn(true);
        $config->method('isOgImageResized')->willReturn($resized);
        $categoryImage = $this->createStub(CategoryImage::class);
        $categoryImage->method('getUrl')->willReturnCallback(
            static fn (Category $category, string $attributeCode = 'image') => $imageUrls[$attributeCode] ?? ''
        );
        $store = $this->createStub(Store::class);
        $store->method('getBaseUrl')->willReturnCallback(
            static fn (string $type = UrlInterface::URL_TYPE_LINK) => $type === UrlInterface::URL_TYPE_MEDIA
                ? self::BASE_URL . 'media/'
                : self::BASE_URL
        );
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $provider = new CategoryMetaProvider(
            $layerResolver,
            $config,
            $storeManager,
            $categoryImage
        );

        $tags = [];
        foreach ($provider->getMetaTags() as $tag) {
            $tags[(string) $tag['property']] = (string) $tag['content'];
        }

        return $tags;
    }
}
