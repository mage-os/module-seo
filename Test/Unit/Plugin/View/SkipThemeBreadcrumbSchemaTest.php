<?php

declare(strict_types=1);

namespace MageOS\Seo\Test\Unit\Plugin\View;

use Magento\Theme\Block\Html\Breadcrumbs;
use MageOS\Seo\Model\Config;
use MageOS\Seo\Model\StructuredData\Provider\BreadcrumbListProvider;
use MageOS\Seo\Plugin\View\SkipThemeBreadcrumbSchema;
use PHPUnit\Framework\TestCase;

/**
 * Issue #55: Hyvä's breadcrumbs template and this module both printed a BreadcrumbList.
 */
class SkipThemeBreadcrumbSchemaTest extends TestCase
{
    public function testTheThemeSkipsItsSchemaWhileThisModuleOutputsOne(): void
    {
        $block = $this->block();

        $this->plugin(true, false)->beforeToHtml($block);

        $this->assertTrue($block->getData('skip_ld_json_schema'));
    }

    public function testTheThemeKeepsItsSchemaWhenStructuredDataIsOff(): void
    {
        $block = $this->block();

        $this->plugin(false, false)->beforeToHtml($block);

        $this->assertNull($block->getData('skip_ld_json_schema'));
    }

    public function testTheThemeKeepsItsSchemaOnAnExcludedPage(): void
    {
        $block = $this->block();

        $this->plugin(true, true)->beforeToHtml($block);

        $this->assertNull($block->getData('skip_ld_json_schema'));
    }

    /**
     * @param bool $structuredData
     * @param bool $excluded
     * @return SkipThemeBreadcrumbSchema
     */
    private function plugin(bool $structuredData, bool $excluded): SkipThemeBreadcrumbSchema
    {
        $config = $this->createStub(Config::class);
        $config->method('isStructuredDataEnabled')->willReturn($structuredData);
        $provider = $this->createStub(BreadcrumbListProvider::class);
        $provider->method('isExcluded')->willReturn($excluded);

        return new SkipThemeBreadcrumbSchema($config, $provider);
    }

    /**
     * A breadcrumbs block with real data storage and no constructor.
     *
     * @return Breadcrumbs
     */
    private function block(): Breadcrumbs
    {
        return $this->getMockBuilder(Breadcrumbs::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
    }
}
