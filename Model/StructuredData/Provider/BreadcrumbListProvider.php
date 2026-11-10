<?php

declare(strict_types=1);

namespace MageOS\Seo\Model\StructuredData\Provider;

use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\BlockInterface;
use Magento\Framework\View\LayoutInterface;
use Magento\Store\Model\StoreManagerInterface;
use MageOS\Seo\Api\StructuredDataProviderInterface;

/**
 * Emits BreadcrumbList schema from the page's breadcrumb trail.
 *
 * Reads the breadcrumbs block when it exposes getCrumbs() (Hyvä); on themes whose
 * breadcrumbs block keeps its crumbs protected (Luma), falls back to the catalog
 * breadcrumb path for product and category pages.
 *
 * The last crumb has no `item`: Google documents that for the last breadcrumb it is not
 * required, and uses the page's own URL.
 */
class BreadcrumbListProvider implements StructuredDataProviderInterface
{
    /**
     * @param LayoutInterface $layout
     * @param CatalogHelper $catalogHelper
     * @param StoreManagerInterface $storeManager
     * @param RequestInterface $request
     * @param string[] $excludedHandles Layout handles whose pages manage their own
     *                                  breadcrumb schema; bridge modules append via di.xml
     */
    public function __construct(
        private readonly LayoutInterface       $layout,
        private readonly CatalogHelper         $catalogHelper,
        private readonly StoreManagerInterface $storeManager,
        private readonly RequestInterface      $request,
        private readonly array                 $excludedHandles = []
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getHandles(): array
    {
        return ['*'];
    }

    /**
     * @inheritdoc
     */
    public function getSchemas(): array
    {
        if ($this->isExcluded()) {
            return [];
        }

        try {
            $crumbs = $this->getBlockCrumbs() ?: $this->getCatalogPathCrumbs();
            if (empty($crumbs)) {
                return [];
            }

            $items = [];
            $position = 1;
            foreach ($crumbs as $crumb) {
                $item = [
                    '@type'    => 'ListItem',
                    'position' => $position,
                    'name'     => (string) ($crumb['label'] ?? ''),
                ];
                if (!empty($crumb['link'])) {
                    $item['item'] = (string) $crumb['link'];
                }
                $items[]  = $item;
                $position++;
            }

            if (\count($items) < 1) {
                return [];
            }

            return [[
                '@context'        => 'https://schema.org',
                '@type'           => 'BreadcrumbList',
                'itemListElement' => $items,
            ]];
        } catch (\Exception) {
            return [];
        }
    }

    /**
     * Whether the current page is one of the excluded handles, which manage their own breadcrumb schema.
     *
     * @return bool
     */
    public function isExcluded(): bool
    {
        $activeHandles = $this->layout->getUpdate()->getHandles();
        foreach ($this->excludedHandles as $excluded) {
            if (\in_array((string) $excluded, $activeHandles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Read crumbs from the breadcrumbs block when it exposes them (Hyvä).
     *
     * @return array<int|string, array{label?: string, link?: string}>
     */
    private function getBlockCrumbs(): array
    {
        $breadcrumbBlock = $this->layout->getBlock('breadcrumbs');
        if (!$breadcrumbBlock instanceof BlockInterface) {
            return [];
        }

        if (!method_exists($breadcrumbBlock, 'getCrumbs')) {
            // Luma's breadcrumbs block keeps its crumbs protected; the catalog
            // path fallback covers product/category pages there.
            return [];
        }

        $crumbs = $breadcrumbBlock->getCrumbs();
        return \is_array($crumbs) ? $crumbs : [];
    }

    /**
     * Rebuild the crumb trail from the catalog breadcrumb path (product/category pages).
     *
     * On a product page the path's categories are kept only when the request carries the category,
     * as a category-path product URL does. Otherwise core took them from the category the visitor
     * last browsed (Catalog\Helper\Product::initProduct()): Luma's own trail shows Home › Product
     * there, and the page cache would serve that one visitor's trail to everyone after them.
     *
     * @return array<int, array{label: string, link?: string}>
     */
    private function getCatalogPathCrumbs(): array
    {
        $path = $this->catalogHelper->getBreadcrumbPath();
        if (empty($path)) {
            return [];
        }

        if (isset($path['product']) && !$this->request->getParam('category')) {
            $path = ['product' => $path['product']];
        }

        $baseUrl = rtrim((string) $this->storeManager->getStore()->getBaseUrl(), '/') . '/';
        $crumbs  = [['label' => (string) __('Home'), 'link' => $baseUrl]];

        foreach ($path as $crumb) {
            $entry = ['label' => (string) ($crumb['label'] ?? '')];
            if (!empty($crumb['link'])) {
                $entry['link'] = (string) $crumb['link'];
            }
            $crumbs[] = $entry;
        }

        return $crumbs;
    }
}
