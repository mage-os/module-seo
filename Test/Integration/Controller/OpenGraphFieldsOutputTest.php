<?php

declare(strict_types=1);

namespace MageOS\Seo\Test\Integration\Controller;

use Magento\Catalog\Test\Fixture\Category as CategoryFixture;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Cms\Api\Data\PageInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Model\PageFactory;
use Magento\Framework\App\Cache\Manager as CacheManager;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * The Open Graph title, description and image of a product, category or CMS page become its og:*
 * tags and their X copies. Without them, the meta title and description are used.
 *
 * It renders real pages. The values come from the attributes and columns that
 * Setup\Patch\Data\AddOpenGraphAttributes and etc/db_schema.xml add. A unit test cannot check that
 * the product page loads them.
 *
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 * @magentoDbIsolation disabled
 */
class OpenGraphFieldsOutputTest extends AbstractController
{
    /**
     * IDs of the CMS pages created by the running test.
     *
     * @var int[]
     */
    private array $createdPageIds = [];

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->_objectManager->get(CacheManager::class)->clean(['full_page']);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $pageRepository = $this->_objectManager->get(PageRepositoryInterface::class);
        foreach ($this->createdPageIds as $pageId) {
            try {
                $pageRepository->deleteById($pageId);
            } catch (\Exception) {
                // Already gone.
            }
        }
        $this->createdPageIds = [];

        parent::tearDown();
    }

    /**
     * @return void
     */
    #[DataFixture(ProductFixture::class, [
        'name'             => 'OG Fields Tee',
        'meta_title'       => 'Buy the OG Fields Tee',
        'meta_description' => 'Written for search results.',
        'og_title'         => 'Share the OG Fields Tee',
        'og_description'   => 'Written for link previews.',
    ], 'product')]
    public function testAProductsOpenGraphFieldsAreItsTags(): void
    {
        $body = $this->rendered('catalog/product/view/id/' . $this->id('product'));

        $this->assertSame('Share the OG Fields Tee', $this->tag('og:title', $body));
        $this->assertSame('Written for link previews.', $this->tag('og:description', $body));
        $this->assertSame('Share the OG Fields Tee', $this->tag('twitter:title', $body));
        $this->assertSame('Written for link previews.', $this->tag('twitter:description', $body));
    }

    /**
     * @return void
     */
    #[DataFixture(ProductFixture::class, [
        'name'              => 'Meta Fallback Tee',
        'meta_title'        => 'Buy the Meta Fallback Tee',
        'meta_description'  => 'Written for search results.',
        'short_description' => 'A short description.',
    ], 'product')]
    public function testWithoutThemAProductsMetaTitleAndDescriptionAreUsed(): void
    {
        $body = $this->rendered('catalog/product/view/id/' . $this->id('product'));

        $this->assertSame('Buy the Meta Fallback Tee', $this->tag('og:title', $body));
        $this->assertSame('Written for search results.', $this->tag('og:description', $body));
    }

    /**
     * @return void
     */
    #[DataFixture(
        ProductFixture::class,
        [
            'name'              => 'Plain Tee',
            'description'       => '<p>A description.</p>',
            'short_description' => 'A short description.',
        ],
        'product'
    )]
    public function testWithoutAnyOfThemAProductKeepsItsNameAndShortDescription(): void
    {
        $body = $this->rendered('catalog/product/view/id/' . $this->id('product'));

        $this->assertSame('Plain Tee', $this->tag('og:title', $body));
        $this->assertSame('A short description.', $this->tag('og:description', $body));
    }

    /**
     * @return void
     */
    #[DataFixture(CategoryFixture::class, [
        'name'             => 'OG Fields Category',
        'meta_title'       => 'OG Fields Category for search',
        'og_title'         => 'OG Fields Category for sharing',
        'meta_description' => 'Category written for search results.',
    ], 'category')]
    public function testACategorysOpenGraphTitleAndMetaDescriptionAreItsTags(): void
    {
        $body = $this->rendered('catalog/category/view/id/' . $this->id('category'));

        $this->assertSame('OG Fields Category for sharing', $this->tag('og:title', $body));
        $this->assertSame('Category written for search results.', $this->tag('og:description', $body));
    }

    /**
     * A category image is published at its original URL, even with Publish Images at the Open Graph
     * Size on. Only product images are resized.
     *
     * @return void
     */
    #[Config('mageos_seo_general/og_tags/resize_image', '1', 'store', 'default')]
    #[DataFixture(CategoryFixture::class, [
        'name'     => 'OG Image Category',
        'og_image' => 'og-fields-category.jpg',
        'image'    => 'og-fields-banner.jpg',
    ], 'category')]
    public function testACategorysOpenGraphImageIsPublishedAtItsOriginalUrl(): void
    {
        $body = $this->rendered('catalog/category/view/id/' . $this->id('category'));

        $this->assertStringEndsWith('/media/catalog/category/og-fields-category.jpg', $this->tag('og:image', $body));
        $this->assertSame('', $this->tag('og:image:width', $body));
        $this->assertSame('', $this->tag('og:image:height', $body));
    }

    /**
     * An image picked in the Media Gallery is stored by core's backend as its /media/... path, here
     * a rendition. It is published as picked, under the media URL.
     *
     * @return void
     */
    #[DataFixture(CategoryFixture::class, [
        'name'     => 'OG Gallery Category',
        'og_image' => '/media/.renditions/wysiwyg/og-fields-category.jpg',
    ], 'category')]
    public function testACategorysGalleryImageIsPublishedUnderTheMediaUrl(): void
    {
        $body  = $this->rendered('catalog/category/view/id/' . $this->id('category'));
        $image = $this->tag('og:image', $body);

        $this->assertStringStartsWith('http', $image);
        $this->assertStringEndsWith('/media/.renditions/wysiwyg/og-fields-category.jpg', $image);
        $this->assertStringNotContainsString('catalog/category', $image);
    }

    /**
     * @return void
     */
    public function testACmsPagesOpenGraphFieldsAreItsTags(): void
    {
        $page = $this->page([
            PageInterface::META_TITLE => 'About us, for search',
            'og_title'                => 'About us, for sharing',
            'og_description'          => 'The page written for link previews.',
            'og_image'                => '/media/wysiwyg/og/team.jpg',
        ]);

        $body = $this->rendered('cms/page/view/page_id/' . $page->getId());

        $this->assertSame('About us, for sharing', $this->tag('og:title', $body));
        $this->assertSame('The page written for link previews.', $this->tag('og:description', $body));
        $this->assertStringEndsWith('/media/wysiwyg/og/team.jpg', $this->tag('og:image', $body));
        $this->assertSame('summary_large_image', $this->tag('twitter:card', $body));
    }

    /**
     * @return void
     */
    public function testACmsPagesAbsoluteOpenGraphImageIsPublishedAsIs(): void
    {
        $page = $this->page(['og_image' => 'https://cdn.example.com/og/team.jpg']);

        $body = $this->rendered('cms/page/view/page_id/' . $page->getId());

        $this->assertSame('https://cdn.example.com/og/team.jpg', $this->tag('og:image', $body));
    }

    /**
     * @return void
     */
    public function testACmsPagesMetaTitleIsItsTitleWithoutAnOpenGraphTitle(): void
    {
        $page = $this->page([PageInterface::META_TITLE => 'About us, for search']);

        $body = $this->rendered('cms/page/view/page_id/' . $page->getId());

        $this->assertSame('About us, for search', $this->tag('og:title', $body));
    }

    /**
     * An active CMS page in every store view, with the given fields.
     *
     * @param array<string, string> $data
     * @return PageInterface
     */
    private function page(array $data): PageInterface
    {
        $identifier = 'mageos-seo-og-' . uniqid();
        $page = $this->_objectManager->get(PageFactory::class)->create();
        $page->setData($data + [
            PageInterface::IDENTIFIER => $identifier,
            PageInterface::TITLE      => 'About us',
            PageInterface::CONTENT    => '<p>About us</p>',
            PageInterface::IS_ACTIVE  => 1,
            'stores'                  => [0],
        ]);
        $this->_objectManager->get(PageRepositoryInterface::class)->save($page);
        $this->createdPageIds[] = (int) $page->getId();

        return $page;
    }

    /**
     * Dispatch the URI and return the page. Fails with the status if it did not render.
     *
     * @param string $uri
     * @return string
     */
    private function rendered(string $uri): string
    {
        $this->dispatch($uri);

        /** @var \Magento\Framework\App\Response\Http $response */
        $response = $this->getResponse();
        $this->assertSame(200, $response->getHttpResponseCode(), $uri . ' did not render.');

        return (string) $response->getBody();
    }

    /**
     * The content of the meta tag with the given property or name, or '' if there is none.
     *
     * @param string $key
     * @param string $body
     * @return string
     */
    private function tag(string $key, string $body): string
    {
        $pattern = '#<meta (?:property|name)="' . preg_quote($key, '#') . '"\s+content="([^"]*)"#';

        return preg_match($pattern, $body, $match) === 1 ? html_entity_decode($match[1], ENT_QUOTES) : '';
    }

    /**
     * The ID of a fixture.
     *
     * @param string $name
     * @return int
     */
    private function id(string $name): int
    {
        return (int) DataFixtureStorageManager::getStorage()->get($name)->getId();
    }
}
