<?php

declare(strict_types=1);

namespace MageOS\Seo\Model\Cms;

use Magento\Catalog\Model\Category\FileInfo;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * A CMS page's og_image value: stored as the Media Gallery posts it, published at its URL.
 *
 * The gallery posts the image's path from the web root (`/media/wysiwyg/hero.jpg`). It is stored as
 * is, as core's category image backend stores a gallery pick. Core's FileInfo resolves it to a path
 * in the media directory, published under the store view's media URL. Other values, such as an
 * absolute URL set by REST, are published as is.
 */
class OgImagePath
{
    /**
     * @param StoreManagerInterface $storeManager
     * @param FileInfo $fileInfo
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly FileInfo $fileInfo
    ) {
    }

    /**
     * The value to store for the gallery field's post, or null for no image.
     *
     * The field posts a list of one file, with its `url`.
     *
     * @param mixed $posted
     * @return string|null
     */
    public function fromUploader(mixed $posted): ?string
    {
        $file = \is_array($posted) ? reset($posted) : null;
        $url  = \is_array($file) ? trim((string) ($file['url'] ?? '')) : '';

        return $url !== '' ? $url : null;
    }

    /**
     * The URL a stored value is published at: empty for no value.
     *
     * @param string $stored
     * @return string
     */
    public function getUrl(string $stored): string
    {
        $stored = trim($stored);
        if ($stored === '' || !$this->fileInfo->isBeginsWithMediaDirectoryPath($stored)) {
            return $stored;
        }

        return $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA)
            . $this->fileInfo->getRelativePathToMediaDirectory($stored);
    }
}
