<?php

declare(strict_types=1);

namespace MageOS\Seo\Plugin\View;

use Magento\Theme\Block\Html\Breadcrumbs;
use MageOS\Seo\Model\Config;
use MageOS\Seo\Model\StructuredData\Provider\BreadcrumbListProvider;

/**
 * Keeps the theme's breadcrumbs template from printing a second BreadcrumbList (issue #55).
 *
 * Hyvä's Magento_Theme::html/breadcrumbs.phtml prints its own BreadcrumbList JSON-LD unless the block
 * has `skip_ld_json_schema` set. While structured data is on, BreadcrumbListProvider describes the
 * same trail, so the flag is set before the block renders. Not on a page the provider leaves to
 * another module (its excludedHandles): the theme's copy is the only one there.
 *
 * A plugin rather than a layout argument: a module that recreates the breadcrumbs block after layout
 * generation drops its arguments, and the plugin also covers subclasses. Luma's template never
 * reads the flag.
 */
class SkipThemeBreadcrumbSchema
{
    /**
     * @param Config $seoConfig
     * @param BreadcrumbListProvider $breadcrumbListProvider
     */
    public function __construct(
        private readonly Config                 $seoConfig,
        private readonly BreadcrumbListProvider $breadcrumbListProvider
    ) {
    }

    /**
     * Set the theme's skip flag when this module outputs the BreadcrumbList.
     *
     * @param Breadcrumbs $subject
     * @return void
     */
    public function beforeToHtml(Breadcrumbs $subject): void
    {
        if ($this->seoConfig->isStructuredDataEnabled() && !$this->breadcrumbListProvider->isExcluded()) {
            $subject->setData('skip_ld_json_schema', true);
        }
    }
}
