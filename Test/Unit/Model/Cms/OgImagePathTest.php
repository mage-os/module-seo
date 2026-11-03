<?php

declare(strict_types=1);

namespace MageOS\Seo\Test\Unit\Model\Cms;

use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use MageOS\Seo\Model\Cms\OgImagePath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A CMS page's og_image is stored as the Media Gallery posts it: a path from the web root. Core's
 * FileInfo resolves it to the store view's media URL. Other values are published as is.
 */
class OgImagePathTest extends TestCase
{
    use MediaFileInfoStub;

    private const MEDIA_URL = 'https://shop.example.com/media/';

    /**
     * @return array<string, array{0: string}>
     */
    public static function galleryUrls(): array
    {
        return [
            'a path from the root'   => ['/media/wysiwyg/a.jpg'],
            'a rendition'            => ['/media/.renditions/wysiwyg/a.jpg'],
            'a gallery cache-buster' => ['/media/wysiwyg/a.jpg?rand=1712'],
            'a URL outside media'    => ['https://cdn.example.com/a.jpg'],
        ];
    }

    /**
     * The gallery field's `url` is stored as posted, with no conversion.
     *
     * @param string $url
     * @return void
     */
    #[DataProvider('galleryUrls')]
    public function testAGalleryFileIsStoredAsPosted(string $url): void
    {
        $this->assertSame($url, $this->ogImagePath()->fromUploader([['name' => 'a.jpg', 'url' => $url]]));
    }

    /**
     * @return void
     */
    public function testThePostedUrlIsTrimmed(): void
    {
        $this->assertSame(
            '/media/wysiwyg/a.jpg',
            $this->ogImagePath()->fromUploader([['url' => ' /media/wysiwyg/a.jpg ']])
        );
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function noImage(): array
    {
        return [
            'nothing'     => [null],
            'a string'    => ['a.jpg'],
            'no file'     => [[]],
            'a bare list' => [['a.jpg']],
            'empty url'   => [[['name' => 'a.jpg', 'url' => ' ']]],
            'no url'      => [[['name' => 'a.jpg']]],
        ];
    }

    /**
     * @param mixed $posted
     * @return void
     */
    #[DataProvider('noImage')]
    public function testAnUploaderPayloadWithoutAFileIsNoImage(mixed $posted): void
    {
        $this->assertNull($this->ogImagePath()->fromUploader($posted));
    }

    /**
     * @return void
     */
    public function testAStoredMediaPathIsPublishedUnderTheMediaUrl(): void
    {
        $path = $this->ogImagePath();

        $this->assertSame(self::MEDIA_URL . 'wysiwyg/og/a.jpg', $path->getUrl('/media/wysiwyg/og/a.jpg'));
        $this->assertSame(
            self::MEDIA_URL . '.renditions/wysiwyg/a.jpg',
            $path->getUrl(' /media/.renditions/wysiwyg/a.jpg ')
        );
        $this->assertSame('', $path->getUrl(' '));
    }

    /**
     * @return void
     */
    public function testAValueOutsideTheMediaDirectoryIsPublishedAsIs(): void
    {
        $path = $this->ogImagePath();

        $this->assertSame('https://cdn.example.com/a.jpg', $path->getUrl('https://cdn.example.com/a.jpg'));
        $this->assertSame('http://cdn.example.com/a.jpg', $path->getUrl('http://cdn.example.com/a.jpg'));
        $this->assertSame('/static/a.jpg', $path->getUrl('/static/a.jpg'));
    }

    /**
     * @return OgImagePath
     */
    private function ogImagePath(): OgImagePath
    {
        $store = $this->createStub(Store::class);
        $store->method('getBaseUrl')->willReturnCallback(
            static fn (string $type = UrlInterface::URL_TYPE_LINK) => $type === UrlInterface::URL_TYPE_MEDIA
                ? self::MEDIA_URL
                : 'https://shop.example.com/'
        );
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return new OgImagePath($storeManager, $this->mediaFileInfo());
    }
}
