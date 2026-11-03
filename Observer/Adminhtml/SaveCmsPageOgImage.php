<?php

declare(strict_types=1);

namespace MageOS\Seo\Observer\Adminhtml;

use Magento\Cms\Model\Page;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use MageOS\Seo\Model\Cms\OgImagePath;

/**
 * Stores the CMS page form's Open Graph image as the page's og_image path.
 *
 * The gallery field posts a list of files, which cms_page cannot store. The file's `url`, a path from
 * the web root, is stored instead (Model\Cms\OgImagePath). A string value, as set by REST or another
 * module, is stored as is.
 *
 * A removed image is not posted at all. The page repository then refills it from the stored page.
 * So on the form's save, and only there, a post without og_image clears it. Mass actions, inline
 * edits and REST calls without og_image leave it alone.
 *
 * It observes cms_page_save_before, not cms_page_prepare_save. So it runs after any observer of the
 * controller's event has handled the payload.
 */
class SaveCmsPageOgImage implements ObserverInterface
{
    public const FIELD = 'og_image';

    /**
     * The page form's save action. Its post leaves out a removed image.
     */
    private const FORM_SAVE_ACTION = 'cms_page_save';

    /**
     * @param RequestInterface $request
     * @param OgImagePath $ogImagePath
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly OgImagePath      $ogImagePath
    ) {
    }

    /**
     * Store the page's Open Graph image as a path.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $page = $observer->getEvent()->getData('object') ?? $observer->getEvent()->getData('page');
        if (!$page instanceof Page) {
            return;
        }

        $value = $page->getData(self::FIELD);
        if (\is_array($value)) {
            $page->setData(self::FIELD, $this->ogImagePath->fromUploader($value));
            return;
        }

        if ($this->isFormSave()) {
            $page->setData(self::FIELD, null);
        }
    }

    /**
     * Whether this is the page form's save, posted without an image.
     *
     * @return bool
     */
    private function isFormSave(): bool
    {
        return $this->request instanceof HttpRequest
            && $this->request->getFullActionName() === self::FORM_SAVE_ACTION
            && $this->request->getPost(self::FIELD) === null;
    }
}
