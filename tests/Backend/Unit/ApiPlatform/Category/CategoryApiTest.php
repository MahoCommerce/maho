<?php

declare(strict_types=1);
/**
 * Maho
 *
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

uses(Tests\MahoBackendTestCase::class);

describe('Category DTO', function () {
    it('has correct default values', function () {
        $category = new \Maho\ApiPlatform\ApiResource\Category();

        expect($category->id)->toBeNull()
            ->and($category->parentId)->toBeNull()
            ->and($category->name)->toBe('')
            ->and($category->description)->toBeNull()
            ->and($category->urlKey)->toBeNull()
            ->and($category->urlPath)->toBeNull()
            ->and($category->image)->toBeNull()
            ->and($category->level)->toBe(0)
            ->and($category->position)->toBe(0)
            ->and($category->isActive)->toBeTrue()
            ->and($category->includeInMenu)->toBeTrue()
            ->and($category->productCount)->toBe(0)
            ->and($category->children)->toBe([])
            ->and($category->childrenIds)->toBe([])
            ->and($category->path)->toBeNull()
            ->and($category->metaTitle)->toBeNull()
            ->and($category->metaKeywords)->toBeNull()
            ->and($category->metaDescription)->toBeNull()
            ->and($category->createdAt)->toBeNull()
            ->and($category->updatedAt)->toBeNull();
    });
});

describe('Category DTO - property assignment', function () {
    it('can set all properties', function () {
        $category = new \Maho\ApiPlatform\ApiResource\Category();

        $category->id = 123;
        $category->parentId = 2;
        $category->name = 'Test Category';
        $category->description = 'Category description';
        $category->urlKey = 'test-category';
        $category->urlPath = 'root/test-category';
        $category->image = 'category.jpg';
        $category->level = 2;
        $category->position = 5;
        $category->isActive = false;
        $category->includeInMenu = false;
        $category->productCount = 42;
        $category->children = [];
        $category->childrenIds = [124, 125];
        $category->path = '1/2/123';
        $category->metaTitle = 'Meta Title';
        $category->metaKeywords = 'keyword1, keyword2';
        $category->metaDescription = 'Meta description text';
        $category->createdAt = '2025-01-15 10:00:00';
        $category->updatedAt = '2025-01-20 15:30:00';

        expect($category->id)->toBe(123)
            ->and($category->parentId)->toBe(2)
            ->and($category->name)->toBe('Test Category')
            ->and($category->description)->toBe('Category description')
            ->and($category->urlKey)->toBe('test-category')
            ->and($category->urlPath)->toBe('root/test-category')
            ->and($category->image)->toBe('category.jpg')
            ->and($category->level)->toBe(2)
            ->and($category->position)->toBe(5)
            ->and($category->isActive)->toBeFalse()
            ->and($category->includeInMenu)->toBeFalse()
            ->and($category->productCount)->toBe(42)
            ->and($category->children)->toBe([])
            ->and($category->childrenIds)->toBe([124, 125])
            ->and($category->path)->toBe('1/2/123')
            ->and($category->metaTitle)->toBe('Meta Title')
            ->and($category->metaKeywords)->toBe('keyword1, keyword2')
            ->and($category->metaDescription)->toBe('Meta description text')
            ->and($category->createdAt)->toBe('2025-01-15 10:00:00')
            ->and($category->updatedAt)->toBe('2025-01-20 15:30:00');
    });
});

describe('Category - tree building', function () {
    it('can build nested category tree structure', function () {
        $parent = new \Maho\ApiPlatform\ApiResource\Category();
        $parent->id = 10;
        $parent->name = 'Parent Category';
        $parent->level = 1;
        $parent->path = '1/10';

        $child1 = new \Maho\ApiPlatform\ApiResource\Category();
        $child1->id = 20;
        $child1->parentId = 10;
        $child1->name = 'Child Category 1';
        $child1->level = 2;
        $child1->path = '1/10/20';

        $child2 = new \Maho\ApiPlatform\ApiResource\Category();
        $child2->id = 21;
        $child2->parentId = 10;
        $child2->name = 'Child Category 2';
        $child2->level = 2;
        $child2->path = '1/10/21';

        $grandchild = new \Maho\ApiPlatform\ApiResource\Category();
        $grandchild->id = 30;
        $grandchild->parentId = 20;
        $grandchild->name = 'Grandchild Category';
        $grandchild->level = 3;
        $grandchild->path = '1/10/20/30';

        $child1->children = [$grandchild];
        $child1->childrenIds = [30];

        $parent->children = [$child1, $child2];
        $parent->childrenIds = [20, 21];

        expect($parent->children)->toHaveCount(2)
            ->and($parent->children[0]->name)->toBe('Child Category 1')
            ->and($parent->children[1]->name)->toBe('Child Category 2')
            ->and($parent->childrenIds)->toBe([20, 21])
            ->and($parent->children[0]->children)->toHaveCount(1)
            ->and($parent->children[0]->children[0]->name)->toBe('Grandchild Category')
            ->and($parent->children[0]->children[0]->parentId)->toBe(20)
            ->and($parent->children[0]->childrenIds)->toBe([30]);
    });

    it('can handle empty children array', function () {
        $category = new \Maho\ApiPlatform\ApiResource\Category();
        $category->id = 50;
        $category->name = 'Leaf Category';
        $category->children = [];
        $category->childrenIds = [];

        expect($category->children)->toBeArray()
            ->and($category->children)->toHaveCount(0)
            ->and($category->childrenIds)->toBeArray()
            ->and($category->childrenIds)->toHaveCount(0);
    });
});

describe('Category - database integration', function () {
    it('can load root category from database', function () {
        $category = \Mage::getModel('catalog/category')->load(2);

        expect($category->getId())->toBe(2)
            ->and($category->getName())->not->toBeEmpty()
            ->and($category->getName())->toBeString()
            ->and($category->getLevel())->toBeGreaterThanOrEqual(1);
    });

    it('root category has expected properties', function () {
        $category = \Mage::getModel('catalog/category')->load(2);

        expect((int) $category->getId())->toBe(2)
            ->and($category->getPath())->toContain('1/2')
            ->and((int) $category->getChildrenCount())->toBeGreaterThanOrEqual(0)
            ->and($category->getIsActive())->toBeIn([0, 1, '0', '1', true, false]);
    });

    it('can verify root category path structure', function () {
        $category = \Mage::getModel('catalog/category')->load(2);
        $pathIds = explode('/', $category->getPath());

        expect($pathIds)->toBeArray()
            ->and($pathIds)->toContain('1')
            ->and($pathIds)->toContain('2')
            ->and((int) $pathIds[0])->toBe(1)
            ->and((int) $pathIds[1])->toBe(2);
    });
});
