<?php

declare(strict_types=1);

namespace MageOS\Seo\Test\Unit\Plugin\Cms\Page;

use Magento\Cms\Model\Page\DataProvider;
use Magento\Framework\Filesystem\Io\File;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use MageOS\Seo\Model\Cms\OgImagePath;
use MageOS\Seo\Plugin\Cms\Page\AddOgImageToFormData;
use MageOS\Seo\Test\Unit\Model\Cms\MediaFileInfoStub;
use PHPUnit\Framework\TestCase;

/**
 * The CMS page form's gallery field gets the stored og_image as a one-file list, with a preview URL.
 */
class AddOgImageToFormDataTest extends TestCase
{
    use MediaFileInfoStub;

    public function testAStoredPathBecomesTheFieldsFile(): void
    {
        $result = $this->plugin()->afterGetData(
            $this->createStub(DataProvider::class),
            [5 => ['page_id' => 5, 'og_image' => '/media/wysiwyg/og/team.jpg']]
        );

        $this->assertSame([[
            'name' => 'team.jpg',
            'url'  => 'https://example.com/media/wysiwyg/og/team.jpg',
            'type' => 'image',
        ]], $result[5]['og_image']);
    }

    public function testAnAbsoluteUrlIsPreviewedAsIs(): void
    {
        $result = $this->plugin()->afterGetData(
            $this->createStub(DataProvider::class),
            [5 => ['page_id' => 5, 'og_image' => 'https://cdn.example.com/og/team.jpg']]
        );

        $this->assertSame('team.jpg', $result[5]['og_image'][0]['name']);
        $this->assertSame('https://cdn.example.com/og/team.jpg', $result[5]['og_image'][0]['url']);
    }

    public function testPagesWithoutAnImageAndListsAlreadyMadeAreLeftAlone(): void
    {
        $data = [
            5 => ['page_id' => 5, 'og_image' => null],
            6 => ['page_id' => 6],
            7 => ['page_id' => 7, 'og_image' => [['url' => '/media/wysiwyg/a.jpg']]],
            8 => ['page_id' => 8, 'og_image' => ' '],
        ];

        $this->assertSame($data, $this->plugin()->afterGetData($this->createStub(DataProvider::class), $data));
    }

    /**
     * @return AddOgImageToFormData
     */
    private function plugin(): AddOgImageToFormData
    {
        $store = $this->createStub(Store::class);
        $store->method('getBaseUrl')->willReturn('https://example.com/media/');
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return new AddOgImageToFormData(new OgImagePath($storeManager, $this->mediaFileInfo()), new File());
    }
}
