<?php

declare(strict_types=1);

namespace MageOS\Seo\Test\Unit\Model\MetaTag\Provider;

use Magento\Cms\Model\Page;
use Magento\Cms\Model\Template\Filter;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use MageOS\Seo\Model\Cms\CmsPageResolver;
use MageOS\Seo\Model\Cms\OgImagePath;
use MageOS\Seo\Model\Config;
use MageOS\Seo\Model\MetaTag\Provider\CmsPageMetaProvider;
use MageOS\Seo\Test\Unit\Model\Cms\MediaFileInfoStub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Review M10: a CMS page's og:image is its first image as a URL a crawler can fetch.
 *
 * Page Builder and the WYSIWYG editor store an image as a `{{media url=…}}` directive, so the src has
 * to go through the page filter. Only the src does: running the whole content through it rendered
 * every widget on the page a second time, on every uncached request, to find one image.
 */
class CmsPageMetaProviderTest extends TestCase
{
    use MediaFileInfoStub;

    private const BASE_URL = 'https://example.com/';

    /**
     * @return void
     */
    public function testADirectiveSrcIsFilteredOnItsOwn(): void
    {
        $filter = $this->createMock(Filter::class);
        $filter->expects($this->once())
            ->method('filter')
            ->with('{{media url=wysiwyg/home/hero.jpg}}')
            ->willReturn('https://example.com/media/wysiwyg/home/hero.jpg');

        $tags = $this->tags(
            '<p>{{widget type="Magento\CatalogWidget\Block\Product\ProductsList" products_count="10"}}</p>'
            . '<img src="{{media url=wysiwyg/home/hero.jpg}}" alt="Hero">'
            . '<img src="{{media url=wysiwyg/home/second.jpg}}" alt="Second">',
            $filter
        );

        $this->assertSame('https://example.com/media/wysiwyg/home/hero.jpg', $tags['og:image'] ?? null);
    }

    /**
     * @return void
     */
    public function testAnAbsoluteSrcIsKept(): void
    {
        $tags = $this->tags('<img src="https://cdn.example.com/a.jpg">', $this->passThrough());

        $this->assertSame('https://cdn.example.com/a.jpg', $tags['og:image'] ?? null);
    }

    /**
     * @return void
     */
    public function testARelativeSrcIsMadeAbsolute(): void
    {
        $tags = $this->tags('<img src="/media/wysiwyg/a.jpg">', $this->passThrough());

        $this->assertSame('https://example.com/media/wysiwyg/a.jpg', $tags['og:image'] ?? null);
    }

    /**
     * @return void
     */
    public function testAPageWithoutAnImageHasNoOgImage(): void
    {
        $filter = $this->createMock(Filter::class);
        $filter->expects($this->never())->method('filter');

        $tags = $this->tags('<p>{{widget type="Magento\Cms\Block\Widget\Block" block_id="1"}}</p>', $filter);

        $this->assertArrayNotHasKey('og:image', $tags);
        $this->assertSame('About us', $tags['og:title'] ?? null);
    }

    /**
     * The meta title is used before the page title.
     *
     * @return void
     */
    public function testTheMetaTitleIsTheTitle(): void
    {
        $tags = $this->tags('', $this->passThrough(), null, ['meta_title' => ' About Our Company ']);

        $this->assertSame('About Our Company', $tags['og:title'] ?? null);
    }

    /**
     * The Open Graph title and description come first. HTML is stripped. They are not truncated.
     *
     * @return void
     */
    public function testTheOpenGraphTitleAndDescriptionComeFirst(): void
    {
        $tags = $this->tags('', $this->passThrough(), null, [
            'og_title'         => '<b>Meet</b> the team',
            'meta_title'       => 'About Our Company',
            'og_description'   => str_repeat('b', 200),
            'meta_description' => 'Written for search results.',
        ]);

        $this->assertSame('Meet the team', $tags['og:title'] ?? null);
        $this->assertSame(str_repeat('b', 200), $tags['og:description'] ?? null);
    }

    /**
     * @return void
     */
    public function testTheMetaDescriptionFollowsAnEmptyOpenGraphDescription(): void
    {
        $tags = $this->tags('', $this->passThrough(), null, [
            'og_description'   => ' <p> </p> ',
            'meta_description' => 'Written for search results.',
        ]);

        $this->assertSame('Written for search results.', $tags['og:description'] ?? null);
    }

    /**
     * The Open Graph image, stored as the Media Gallery posts it, is published under the media URL.
     * It wins over the first content image, which is then not filtered.
     *
     * @return void
     */
    public function testTheOpenGraphImageComesBeforeTheContentsFirstImage(): void
    {
        $filter = $this->createMock(Filter::class);
        $filter->expects($this->never())->method('filter');

        $tags = $this->tags(
            '<img src="{{media url=wysiwyg/a.jpg}}">',
            $filter,
            null,
            ['og_image' => '/media/wysiwyg/og/team.jpg']
        );

        $this->assertSame('https://example.com/media/wysiwyg/og/team.jpg', $tags['og:image'] ?? null);
    }

    /**
     * An absolute URL, as set by REST, is published as is.
     *
     * @return void
     */
    public function testAnAbsoluteOpenGraphImageIsPublishedAsIs(): void
    {
        $tags = $this->tags('', $this->passThrough(), null, ['og_image' => 'https://cdn.example.com/team.jpg']);

        $this->assertSame('https://cdn.example.com/team.jpg', $tags['og:image'] ?? null);
    }

    /**
     * A failure is logged rather than swallowed: the page then has no Open Graph tags, and the log
     * says why.
     *
     * @return void
     */
    public function testAFailureIsLogged(): void
    {
        $filter = $this->createStub(Filter::class);
        $filter->method('filter')->willThrowException(new \RuntimeException('media directive failed'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error')
            ->with($this->stringContains('media directive failed'));

        $this->assertSame([], $this->tags('<img src="{{media url=wysiwyg/a.jpg}}">', $filter, $logger));
    }

    /**
     * The provider's tags for a CMS page with the given content: property => content.
     *
     * @param string $content
     * @param Filter $filter
     * @param LoggerInterface|null $logger
     * @param array<string, string> $data The page's other fields, read through their getters
     * @return array<string, string>
     */
    private function tags(
        string $content,
        Filter $filter,
        ?LoggerInterface $logger = null,
        array $data = []
    ): array {
        $page = $this->createStub(Page::class);
        $page->method('getTitle')->willReturn('About us');
        $page->method('getContent')->willReturn($content);
        $page->method('getMetaTitle')->willReturn($data['meta_title'] ?? '');
        $page->method('getMetaDescription')->willReturn($data['meta_description'] ?? '');
        $page->method('getData')->willReturnCallback(
            static fn (string $key = '') => $data[$key] ?? null
        );
        $resolver = $this->createStub(CmsPageResolver::class);
        $resolver->method('resolve')->willReturn($page);
        $resolver->method('currentUrl')->willReturn(self::BASE_URL . 'about-us');
        $config = $this->createStub(Config::class);
        $config->method('isOgTagsEnabled')->willReturn(true);
        $store = $this->createStub(Store::class);
        $store->method('getBaseUrl')->willReturnCallback(
            static fn (string $type = UrlInterface::URL_TYPE_LINK) => $type === UrlInterface::URL_TYPE_MEDIA
                ? self::BASE_URL . 'media/'
                : self::BASE_URL
        );
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $filterProvider = $this->createStub(FilterProvider::class);
        $filterProvider->method('getPageFilter')->willReturn($filter);

        $provider = new CmsPageMetaProvider(
            $resolver,
            $config,
            $storeManager,
            $filterProvider,
            $logger ?? $this->createStub(LoggerInterface::class),
            new OgImagePath($storeManager, $this->mediaFileInfo())
        );

        $tags = [];
        foreach ($provider->getMetaTags() as $tag) {
            $tags[(string) $tag['property']] = (string) $tag['content'];
        }

        return $tags;
    }

    /**
     * A page filter that returns what it is given.
     *
     * @return Filter
     */
    private function passThrough(): Filter
    {
        $filter = $this->createStub(Filter::class);
        $filter->method('filter')->willReturnArgument(0);

        return $filter;
    }
}
