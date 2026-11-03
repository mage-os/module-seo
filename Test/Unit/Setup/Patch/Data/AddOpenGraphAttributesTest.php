<?php

declare(strict_types=1);

namespace MageOS\Seo\Test\Unit\Setup\Patch\Data;

use Magento\Catalog\Model\Category\Attribute\Backend\Image as CategoryImageBackend;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use MageOS\Seo\Setup\Patch\Data\AddOpenGraphAttributes;
use PHPUnit\Framework\TestCase;

/**
 * The Open Graph attributes keep the codes and types a store may already use, so values carry over.
 * They have store view scope, like the meta fields.
 */
class AddOpenGraphAttributesTest extends TestCase
{
    public function testEachEntityGetsTheThreeAttributes(): void
    {
        $added = $this->applied();

        $this->assertSame(
            [
                'catalog_product.og_title', 'catalog_product.og_description', 'catalog_product.og_image',
                'catalog_category.og_title', 'catalog_category.og_description', 'catalog_category.og_image',
            ],
            array_keys($added)
        );
    }

    public function testEveryAttributeIsAStoreViewVarcharOfTheModule(): void
    {
        foreach ($this->applied() as $key => $definition) {
            $this->assertSame('varchar', $definition['type'], $key);
            $this->assertSame(ScopedAttributeInterface::SCOPE_STORE, $definition['global'], $key);
            $this->assertFalse($definition['user_defined'], $key . ': not deletable in the admin.');
            $this->assertFalse($definition['required'], $key);
        }
    }

    public function testTheImagesAreAProductImageRoleAndAnUploadedCategoryImage(): void
    {
        $added = $this->applied();

        $this->assertSame('media_image', $added['catalog_product.og_image']['input']);
        $this->assertSame('image-management', $added['catalog_product.og_image']['group']);
        $this->assertSame('image', $added['catalog_category.og_image']['input']);
        $this->assertSame(CategoryImageBackend::class, $added['catalog_category.og_image']['backend']);
    }

    public function testRevertRemovesTheAttributes(): void
    {
        $removed = [];
        $eavSetup = $this->createStub(EavSetup::class);
        $eavSetup->method('removeAttribute')->willReturnCallback(
            function (string $entityType, string $code) use (&$removed, $eavSetup) {
                $removed[] = $entityType . '.' . $code;
                return $eavSetup;
            }
        );

        $this->patch($eavSetup)->revert();

        $this->assertSame(
            [
                'catalog_product.og_title', 'catalog_product.og_description', 'catalog_product.og_image',
                'catalog_category.og_title', 'catalog_category.og_description', 'catalog_category.og_image',
            ],
            $removed
        );
        $this->assertSame([], AddOpenGraphAttributes::getDependencies());
    }

    /**
     * The definitions the patch adds, by entity type and code.
     *
     * @return array<string, array<string, mixed>>
     */
    private function applied(): array
    {
        $added = [];
        $eavSetup = $this->createStub(EavSetup::class);
        $eavSetup->method('addAttribute')->willReturnCallback(
            function (string $entityType, string $code, array $definition) use (&$added, $eavSetup) {
                $added[$entityType . '.' . $code] = $definition;
                return $eavSetup;
            }
        );

        $patch = $this->patch($eavSetup);
        $this->assertSame($patch, $patch->apply());

        return $added;
    }

    /**
     * @param EavSetup $eavSetup
     * @return AddOpenGraphAttributes
     */
    private function patch(EavSetup $eavSetup): AddOpenGraphAttributes
    {
        $factory = $this->createStub(EavSetupFactory::class);
        $factory->method('create')->willReturn($eavSetup);

        return new AddOpenGraphAttributes($this->createStub(ModuleDataSetupInterface::class), $factory);
    }
}
