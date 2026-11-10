<?php

declare(strict_types=1);

namespace MageOS\Seo\Test\Unit\Model\MetaTag\Provider;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use MageOS\Seo\Model\Config;
use MageOS\Seo\Model\MetaTag\Provider\CategoryMetaProvider;
use PHPUnit\Framework\TestCase;

/**
 * Issue #51: a category's og:description is its meta description, then its description.
 */
class CategoryMetaProviderTest extends TestCase
{
    public function testTheMetaDescriptionIsTheDescription(): void
    {
        $tags = $this->tags([
            'meta_description' => 'Food, litter and toys for cats.',
            'description'      => '<p>Banner</p>',
        ]);

        $this->assertSame('Food, litter and toys for cats.', $tags['og:description'] ?? null);
    }

    public function testTheDescriptionIsTheFallback(): void
    {
        $tags = $this->tags(['meta_description' => ' ', 'description' => '<style>.x{}</style><p>All  for cats.</p>']);

        $this->assertSame('All for cats.', $tags['og:description'] ?? null);
    }

    public function testNoDescriptionAtAllWritesNone(): void
    {
        $this->assertArrayNotHasKey('og:description', $this->tags([]));
    }

    /**
     * The provider's tags for a category with the given attribute values: property => content.
     *
     * @param array<string, string> $data
     * @return array<string, string>
     */
    private function tags(array $data): array
    {
        $category = $this->createStub(Category::class);
        $category->method('getName')->willReturn('Cats');
        $category->method('getUrl')->willReturn('https://example.com/cats.html');
        $category->method('getImageUrl')->willReturn('');
        $category->method('getData')->willReturnCallback(static fn ($key = '') => $data[$key] ?? null);

        $layer = $this->createStub(Layer::class);
        $layer->method('getCurrentCategory')->willReturn($category);
        $resolver = $this->createStub(LayerResolver::class);
        $resolver->method('get')->willReturn($layer);
        $config = $this->createStub(Config::class);
        $config->method('isOgTagsEnabled')->willReturn(true);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($this->createStub(Store::class));

        $tags = [];
        foreach ((new CategoryMetaProvider($resolver, $config, $storeManager))->getMetaTags() as $tag) {
            $tags[(string) $tag['property']] = (string) $tag['content'];
        }

        return $tags;
    }
}
