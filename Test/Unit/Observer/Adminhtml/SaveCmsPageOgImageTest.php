<?php

declare(strict_types=1);

namespace MageOS\Seo\Test\Unit\Observer\Adminhtml;

use Magento\Cms\Model\Page;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use MageOS\Seo\Model\Cms\OgImagePath;
use MageOS\Seo\Observer\Adminhtml\SaveCmsPageOgImage;
use MageOS\Seo\Test\Unit\Model\Cms\MediaFileInfoStub;
use PHPUnit\Framework\TestCase;

/**
 * The CMS page form's Open Graph image is stored on cms_page as the Media Gallery posts it: a path
 * from the web root, such as `/media/wysiwyg/a.jpg`. A removed image is cleared. Other saves without
 * an image leave the stored one alone.
 */
class SaveCmsPageOgImageTest extends TestCase
{
    use MediaFileInfoStub;

    public function testAnUnchangedImageIsStoredAsPosted(): void
    {
        // The form posts back the file list of Plugin\Cms\Page\AddOgImageToFormData.
        $url  = '/media/wysiwyg/og/a.jpg';
        $page = $this->page(['og_image' => [['name' => 'a.jpg', 'url' => $url, 'type' => 'image']]]);

        $this->observer('cms_page_save', ['og_image' => [['url' => $url]]])->execute($this->eventFor($page));

        $this->assertSame('/media/wysiwyg/og/a.jpg', $page->getData('og_image'));
    }

    public function testAGalleryFileIsStoredAsPosted(): void
    {
        // The Media Gallery posts the file's path from the web root.
        $url  = '/media/wysiwyg/a.jpg';
        $page = $this->page(['og_image' => [['name' => 'a.jpg', 'url' => $url]]]);

        $this->observer('cms_page_save', ['og_image' => [['url' => $url]]])->execute($this->eventFor($page));

        $this->assertSame('/media/wysiwyg/a.jpg', $page->getData('og_image'));
    }

    public function testAGalleryRenditionIsStoredAsPicked(): void
    {
        // The Media Gallery posts its rendition, by its path from the web root.
        $url  = '/media/.renditions/wysiwyg/a.jpg';
        $page = $this->page(['og_image' => [['name' => 'a.jpg', 'url' => $url]]]);

        $this->observer('cms_page_save', ['og_image' => [['url' => $url]]])->execute($this->eventFor($page));

        $this->assertSame('/media/.renditions/wysiwyg/a.jpg', $page->getData('og_image'));
    }

    public function testARemovedFileListIsNoImage(): void
    {
        $page = $this->page(['og_image' => []]);

        $this->observer('cms_page_save', ['og_image' => []])->execute($this->eventFor($page));

        $this->assertTrue($page->hasData('og_image'));
        $this->assertNull($page->getData('og_image'));
    }

    public function testAStringIsStoredAsItIs(): void
    {
        // As set by a REST call, or by a module that already turned the payload into a URL.
        $page = $this->page(['og_image' => 'http://localhost:8000/media/wysiwyg/a.jpg']);

        $this->observer('cms_page_save', ['og_image' => [['url' => 'http://localhost:8000/media/wysiwyg/a.jpg']]])
            ->execute($this->eventFor($page));

        $this->assertSame('http://localhost:8000/media/wysiwyg/a.jpg', $page->getData('og_image'));
    }

    public function testAnImageRemovedInTheFormIsCleared(): void
    {
        // The form posts no og_image. The repository has put the stored one back.
        $page = $this->page(['title' => 'About us', 'og_image' => '/media/wysiwyg/og/a.jpg']);

        $this->observer('cms_page_save', ['title' => 'About us'])->execute($this->eventFor($page));

        $this->assertTrue($page->hasData('og_image'), 'Set, so the resource model writes the column.');
        $this->assertNull($page->getData('og_image'));
    }

    public function testAFormSaveWithoutAStoredImageStoresNone(): void
    {
        $page = $this->page(['title' => 'About us']);

        $this->observer('cms_page_save', ['title' => 'About us'])->execute($this->eventFor($page));

        $this->assertTrue($page->hasData('og_image'));
        $this->assertNull($page->getData('og_image'));
    }

    public function testAnotherSaveWithoutAnImageLeavesTheStoredOneAlone(): void
    {
        // A mass action or inline edit, not the form: a missing value is not a removal.
        $page = $this->page(['title' => 'About us', 'og_image' => '/media/wysiwyg/og/a.jpg']);

        $this->observer('cms_page_inlineEdit', ['title' => 'About us'])->execute($this->eventFor($page));

        $this->assertSame('/media/wysiwyg/og/a.jpg', $page->getData('og_image'));
    }

    /**
     * The observer, in a request for the given admin action with the given post fields.
     *
     * @param string $fullActionName
     * @param array<string, mixed> $post
     * @return SaveCmsPageOgImage
     */
    private function observer(string $fullActionName, array $post): SaveCmsPageOgImage
    {
        $request = $this->createStub(HttpRequest::class);
        $request->method('getFullActionName')->willReturn($fullActionName);
        $request->method('getPost')->willReturnCallback(
            static fn (?string $key = null) => $key === null ? $post : ($post[$key] ?? null)
        );

        $store = $this->createStub(Store::class);
        $store->method('getBaseUrl')->willReturn('https://example.com/media/');
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return new SaveCmsPageOgImage($request, new OgImagePath($storeManager, $this->mediaFileInfo()));
    }

    /**
     * A page holding the given data, as the save controller sets it.
     *
     * @param array<string, mixed> $data
     * @return Page
     */
    private function page(array $data): Page
    {
        // The real model, without resources. Only its data is used.
        $page = (new \ReflectionClass(Page::class))->newInstanceWithoutConstructor();
        $page->setData($data);

        return $page;
    }

    /**
     * The cms_page_save_before event for a page.
     *
     * @param Page $page
     * @return Observer
     */
    private function eventFor(Page $page): Observer
    {
        return new Observer(['event' => new Event(['object' => $page])]);
    }
}
