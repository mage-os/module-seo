<?php

declare(strict_types=1);

namespace MageOS\Seo\Test\Unit\Model\StructuredData\Provider;

use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\BlockInterface;
use Magento\Framework\View\Layout\ProcessorInterface;
use Magento\Framework\View\LayoutInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use MageOS\Seo\Model\StructuredData\Provider\BreadcrumbListProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class BreadcrumbListProviderTest extends TestCase
{
    /**
     * @var LayoutInterface&Stub
     */
    private LayoutInterface&Stub $layout;

    /**
     * @var ProcessorInterface&Stub
     */
    private ProcessorInterface&Stub $layoutProcessor;

    /**
     * @var CatalogHelper&Stub
     */
    private CatalogHelper&Stub $catalogHelper;

    /**
     * @var StoreManagerInterface&Stub
     */
    private StoreManagerInterface&Stub $storeManager;

    protected function setUp(): void
    {
        $this->layoutProcessor = $this->createStub(ProcessorInterface::class);
        $this->layoutProcessor->method('getHandles')->willReturn([]);
        $this->layout = $this->createStub(LayoutInterface::class);
        $this->layout->method('getUpdate')->willReturn($this->layoutProcessor);

        $this->catalogHelper = $this->createStub(CatalogHelper::class);

        $store = $this->createStub(Store::class);
        $store->method('getBaseUrl')->willReturn('https://example.com/');
        $this->storeManager = $this->createStub(StoreManagerInterface::class);
        $this->storeManager->method('getStore')->willReturn($store);
    }

    /**
     * @param string[] $excludedHandles
     * @param string|null $requestCategory The request's `category` param: set by a category-path URL
     * @param LayoutInterface|null $layout Defaults to this test's stub
     */
    private function makeProvider(
        array $excludedHandles = [],
        ?string $requestCategory = null,
        ?LayoutInterface $layout = null
    ): BreadcrumbListProvider {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnMap([['category', null, $requestCategory]]);

        return new BreadcrumbListProvider(
            $layout ?? $this->layout,
            $this->catalogHelper,
            $this->storeManager,
            $request,
            $excludedHandles
        );
    }

    /**
     * A layout whose breadcrumbs block is the given one, as a mock that checks the block is asked
     * for by that name.
     *
     * @param BlockInterface|false $block
     * @return LayoutInterface&MockObject
     */
    private function layoutWithBreadcrumbs(BlockInterface|false $block): LayoutInterface&MockObject
    {
        $layout = $this->createMock(LayoutInterface::class);
        $layout->method('getUpdate')->willReturn($this->layoutProcessor);
        $layout->method('getBlock')->with('breadcrumbs')->willReturn($block);

        return $layout;
    }

    public function testGetHandlesReturnsWildcard(): void
    {
        $this->assertSame(['*'], $this->makeProvider()->getHandles());
    }

    public function testExcludedHandleSuppressesSchema(): void
    {
        $this->layoutProcessor = $this->createStub(ProcessorInterface::class);
        $this->layoutProcessor->method('getHandles')->willReturn(['catalog_product_view', 'makers_landing']);
        $this->layout = $this->createStub(LayoutInterface::class);
        $this->layout->method('getUpdate')->willReturn($this->layoutProcessor);

        $this->assertSame([], $this->makeProvider(['makers_landing'])->getSchemas());
        $this->assertTrue($this->makeProvider(['makers_landing'])->isExcluded());
    }

    public function testAPageOutsideTheExcludedHandlesIsNotExcluded(): void
    {
        $this->assertFalse($this->makeProvider(['makers_landing'])->isExcluded());
    }

    public function testHyvaBlockCrumbsBuildBreadcrumbList(): void
    {
        // A breadcrumbs block exposing getCrumbs() like Hyvä's does. An anonymous
        // class avoids the deprecated MockBuilder::addMethods() and a fixture file.
        $breadcrumbBlock = new class () implements BlockInterface {
            public function toHtml()
            {
                return '';
            }

            /**
             * @return array<int, array{label?: string, link?: string}>
             */
            public function getCrumbs(): array
            {
                return [
                    ['label' => 'Home', 'link' => 'https://example.com/'],
                    ['label' => 'Shoes', 'link' => 'https://example.com/shoes'],
                    ['label' => 'Sneaker'],
                ];
            }
        };
        $schemas = $this->makeProvider(layout: $this->layoutWithBreadcrumbs($breadcrumbBlock))->getSchemas();

        $this->assertCount(1, $schemas);
        $list = $schemas[0];
        $this->assertSame('BreadcrumbList', $list['@type']);
        $this->assertCount(3, $list['itemListElement']);
        $this->assertSame(1, $list['itemListElement'][0]['position']);
        $this->assertSame('Home', $list['itemListElement'][0]['name']);
        $this->assertSame('https://example.com/shoes', $list['itemListElement'][1]['item']);
        $this->assertSame(3, $list['itemListElement'][2]['position']);
        // The last crumb has no link, so no 'item' key.
        $this->assertArrayNotHasKey('item', $list['itemListElement'][2]);
    }

    public function testLumaFallbackPrependsHomeCrumbFromCatalogPath(): void
    {
        // No getCrumbs()-capable block (Luma): fall back to the catalog path. The product was
        // requested at a category-path URL, so the request carries its category.
        $this->catalogHelper->method('getBreadcrumbPath')->willReturn([
            'category-1' => ['label' => 'Shoes', 'link' => 'https://example.com/shoes'],
            'product'    => ['label' => 'Sneaker'],
        ]);

        $schemas = $this->makeProvider([], '1', $this->layoutWithBreadcrumbs(false))->getSchemas();

        $list = $schemas[0]['itemListElement'];
        $this->assertSame('Home', $list[0]['name']);
        $this->assertSame('https://example.com/', $list[0]['item']);
        $this->assertSame('Shoes', $list[1]['name']);
        $this->assertSame('Sneaker', $list[2]['name']);
        $this->assertArrayNotHasKey('item', $list[2]);
    }

    public function testAProductAtAPlainUrlIsHomeThenTheProduct(): void
    {
        // The category in the path came from the visitor's session, not the URL: left out, as
        // Luma's own trail leaves it out, and as the page cache must not keep one visitor's trail.
        $this->catalogHelper->method('getBreadcrumbPath')->willReturn([
            'category-1' => ['label' => 'Shoes', 'link' => 'https://example.com/shoes'],
            'product'    => ['label' => 'Sneaker'],
        ]);

        $list = $this->makeProvider(layout: $this->layoutWithBreadcrumbs(false))->getSchemas()[0]['itemListElement'];

        $this->assertSame(['Home', 'Sneaker'], array_column($list, 'name'));
        $this->assertSame([1, 2], array_column($list, 'position'));
    }

    public function testACategoryPageKeepsItsPath(): void
    {
        // On a category page the category is the page itself, not a guess.
        $this->catalogHelper->method('getBreadcrumbPath')->willReturn([
            'category-1' => ['label' => 'Shoes', 'link' => 'https://example.com/shoes'],
            'category-2' => ['label' => 'Sneakers'],
        ]);

        $list = $this->makeProvider(layout: $this->layoutWithBreadcrumbs(false))->getSchemas()[0]['itemListElement'];

        $this->assertSame(['Home', 'Shoes', 'Sneakers'], array_column($list, 'name'));
    }

    public function testEmptyWhenNoBlockAndNoCatalogPath(): void
    {
        $this->catalogHelper->method('getBreadcrumbPath')->willReturn([]);

        $this->assertSame([], $this->makeProvider(layout: $this->layoutWithBreadcrumbs(false))->getSchemas());
    }
}
