<?php

declare(strict_types=1);

namespace MageOS\Seo\Plugin\Cms\Page;

use Magento\Cms\Model\Page\DataProvider;
use Magento\Framework\Filesystem\Io\File;
use MageOS\Seo\Model\Cms\OgImagePath;
use MageOS\Seo\Observer\Adminhtml\SaveCmsPageOgImage;

/**
 * Loads the stored Open Graph image into the CMS page form's uploader.
 *
 * cms_page stores the path the Media Gallery posted (Model\Cms\OgImagePath). The gallery field
 * needs a list of files, each with its name and URL. A value another plugin already turned into a
 * list is left as is.
 */
class AddOgImageToFormData
{
    /**
     * @param OgImagePath $ogImagePath
     * @param File $file
     */
    public function __construct(
        private readonly OgImagePath $ogImagePath,
        private readonly File $file
    ) {
    }

    /**
     * Turn each page's stored Open Graph image into a one-file list.
     *
     * @param DataProvider $subject
     * @param mixed $result
     * @return mixed
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function afterGetData(DataProvider $subject, mixed $result): mixed
    {
        if (!\is_array($result) || $result === []) {
            return $result;
        }

        foreach ($result as $pageId => $data) {
            $stored = \is_array($data) ? ($data[SaveCmsPageOgImage::FIELD] ?? null) : null;
            if (!\is_string($stored) || trim($stored) === '') {
                continue;
            }

            $stored = trim($stored);
            $url    = $this->ogImagePath->getUrl($stored);
            $result[$pageId][SaveCmsPageOgImage::FIELD] = [[
                'name' => (string) ($this->file->getPathInfo($url)['basename'] ?? ''),
                'url'  => $url,
                'type' => 'image',
            ]];
        }

        return $result;
    }
}
