<?php

declare(strict_types=1);

namespace MageOS\Seo\Setup\Patch\Data;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Category\Attribute\Backend\Image as CategoryImageBackend;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Frontend\Image as ProductImageFrontend;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;

/**
 * Adds the Open Graph title, description and image attributes to products and categories.
 *
 * The codes og_title, og_description and og_image are the ones Open Graph modules commonly use.
 * Adding an existing attribute updates its definition and keeps its values. Each is a varchar,
 * og_description too, so existing rows in catalog_*_entity_varchar are kept.
 *
 * They have store view scope, like the meta title and description, because they are translated.
 * The text attributes are in the Search Engine Optimization group. The product image is an image
 * role, chosen in Images and Videos like the base and small images. The category image is uploaded
 * in the category form's Search Engine Optimization section (view/adminhtml/ui_component).
 *
 * The patch is idempotent. Reverting it, on module:uninstall, removes the attributes and their
 * values.
 */
class AddOpenGraphAttributes implements DataPatchInterface, PatchRevertableInterface
{
    public const TITLE       = 'og_title';
    public const DESCRIPTION = 'og_description';
    public const IMAGE       = 'og_image';

    /**
     * The entity types that get the attributes.
     */
    private const ENTITY_TYPES = [Product::ENTITY, Category::ENTITY];

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param EavSetupFactory $eavSetupFactory
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory          $eavSetupFactory
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        foreach (self::ENTITY_TYPES as $entityType) {
            foreach ($this->definitions($entityType) as $code => $definition) {
                $eavSetup->addAttribute($entityType, $code, $definition);
            }
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function revert(): void
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        foreach (self::ENTITY_TYPES as $entityType) {
            foreach ([self::TITLE, self::DESCRIPTION, self::IMAGE] as $code) {
                $eavSetup->removeAttribute($entityType, $code);
            }
        }
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * The attributes of one entity type, by code.
     *
     * @param string $entityType
     * @return array<string, array<string, mixed>>
     */
    private function definitions(string $entityType): array
    {
        $isProduct = $entityType === Product::ENTITY;
        $common    = [
            'type'                     => 'varchar',
            'global'                   => ScopedAttributeInterface::SCOPE_STORE,
            'required'                 => false,
            'user_defined'             => false,
            'visible'                  => true,
            'visible_on_front'         => false,
            'is_html_allowed_on_front' => false,
            'used_in_product_listing'  => false,
            'searchable'               => false,
            'filterable'               => false,
            'comparable'               => false,
            'is_used_in_grid'          => false,
            'is_visible_in_grid'       => false,
            'is_filterable_in_grid'    => false,
        ];

        $title = $common + [
            'label'      => 'Open Graph Title',
            'input'      => 'text',
            'group'      => 'Search Engine Optimization',
            'sort_order' => 50,
            'note'       => $isProduct
                ? 'Title of link previews. Leave empty to use the meta title, then the product name.'
                : 'Title of link previews. Leave empty to use the meta title, then the category name.',
        ];
        $description = $common + [
            'label'      => 'Open Graph Description',
            'input'      => 'textarea',
            'group'      => 'Search Engine Optimization',
            'sort_order' => 60,
            'note'       => $isProduct
                ? 'Description of link previews. Leave empty to use the meta description, then the short'
                    . ' description, then the description.'
                : 'Description of link previews. Leave empty to use the meta description, then the description.',
        ];
        $image = $isProduct
            ? $common + [
                'label'      => 'Open Graph Image',
                'input'      => 'media_image',
                'frontend'   => ProductImageFrontend::class,
                'group'      => 'image-management',
                'sort_order' => 50,
                'note'       => 'Image of link previews, at least 1200 × 630 pixels. When no image has this role, the'
                    . ' base image is used.',
            ]
            : $common + [
                'label'      => 'Open Graph Image',
                'input'      => 'image',
                'backend'    => CategoryImageBackend::class,
                'group'      => 'Search Engine Optimization',
                'sort_order' => 70,
                'note'       => 'Image of link previews, at least 1200 × 630 pixels. Leave empty to use the category'
                    . ' image.',
            ];

        return [
            self::TITLE       => $title,
            self::DESCRIPTION => $description,
            self::IMAGE       => $image,
        ];
    }
}
