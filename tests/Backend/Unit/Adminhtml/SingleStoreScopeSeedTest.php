<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/*
 * In single-store mode these forms swap their scope selector for a hidden field.
 * That value must survive setValues(), and must not overwrite an existing record's own scope.
 */

/**
 * Force isSingleStoreMode() on: `_isSingleStore` is protected with no public setter for `true`.
 */
function withSingleStoreMode(callable $fn): mixed
{
    $property = new ReflectionProperty(Mage_Core_Model_App::class, '_isSingleStore');
    $original = $property->getValue(Mage::app());
    $property->setValue(Mage::app(), true);

    try {
        return $fn();
    } finally {
        $property->setValue(Mage::app(), $original);
    }
}

/**
 * Build the tab's form from the registered model, and hand it back.
 *
 * Instantiated directly rather than through the layout: createBlock() runs
 * _prepareLayout(), and the post tab's reaches for the 'head' block that only
 * the real edit page provides. _prepareForm() is the subject here.
 *
 * @param class-string<Mage_Adminhtml_Block_Widget_Form> $blockClass
 */
function prepareScopeForm(string $blockClass, string $registryKey, Maho\DataObject $model): Maho\Data\Form
{
    Mage::unregister($registryKey);
    Mage::register($registryKey, $model);

    try {
        $block = new $blockClass();
        (new ReflectionMethod($block, '_prepareForm'))->invoke($block);
        return $block->getForm();
    } finally {
        Mage::unregister($registryKey);
    }
}

function currentWebsiteId(): string
{
    return (string) Mage::app()->getStore(true)->getWebsiteId();
}

function currentStoreId(): string
{
    return (string) Mage::app()->getStore(true)->getId();
}

describe('customer segment website scope in single-store mode', function () {
    it('gives a new segment the current website', function () {
        $segment = Mage::getModel('customersegmentation/segment');

        // getStore(true) only answers with the current store while single-store mode is on
        [$form, $websiteId] = withSingleStoreMode(fn() => [
            prepareScopeForm(
                Maho_CustomerSegmentation_Block_Adminhtml_Segment_Edit_Tab_General::class,
                'current_customer_segment',
                $segment,
            ),
            currentWebsiteId(),
        ]);

        // Empty here is the original bug: nothing posts and the save fails
        expect((string) $form->getElement('website_ids')->getValue())->toBe($websiteId);
    });

    it('keeps an existing segment on the website it was saved with', function () {
        $segment = Mage::getModel('customersegmentation/segment');
        // _afterLoad() hands the form an array; 7 is deliberately not the current website
        $segment->setId(123)->setData('website_ids', ['7']);

        $form = withSingleStoreMode(fn() => prepareScopeForm(
            Maho_CustomerSegmentation_Block_Adminhtml_Segment_Edit_Tab_General::class,
            'current_customer_segment',
            $segment,
        ));

        expect($form->getElement('website_ids')->getValue())->toBe('7');
    });
});

describe('blog store scope in single-store mode', function () {
    it('gives a new post the current store view', function () {
        $post = Mage::getModel('blog/post');

        [$form, $storeId] = withSingleStoreMode(fn() => [
            prepareScopeForm(
                Maho_Blog_Block_Adminhtml_Post_Edit_Tab_Content::class,
                'blog_post',
                $post,
            ),
            currentStoreId(),
        ]);

        expect((string) $form->getElement('stores_0')->getValue())->toBe($storeId);
    });

    it('keeps an existing post on store 0 ("All Store Views")', function () {
        $post = Mage::getModel('blog/post');
        $post->setId(456)->setData('stores', [0]);

        $form = withSingleStoreMode(fn() => prepareScopeForm(
            Maho_Blog_Block_Adminhtml_Post_Edit_Tab_Content::class,
            'blog_post',
            $post,
        ));

        expect((string) $form->getElement('stores_0')->getValue())->toBe('0');
    });

    it('keeps every store an existing post is assigned to', function () {
        // Dropping any assigned store makes _saveStoreRelations() DELETE its row
        $post = Mage::getModel('blog/post');
        $post->setId(456)->setData('stores', [0, 1]);

        $form = withSingleStoreMode(fn() => prepareScopeForm(
            Maho_Blog_Block_Adminhtml_Post_Edit_Tab_Content::class,
            'blog_post',
            $post,
        ));

        $posted = [];
        foreach ($form->getElements() as $fieldset) {
            foreach ($fieldset->getElements() as $element) {
                if ($element->getName() === 'stores[]') {
                    $posted[] = (string) $element->getValue();
                }
            }
        }

        expect($posted)->toBe(['0', '1']);
        // Seeding must not overwrite 'stores': getStores() returns [] for a non-array value
        expect($post->getStores())->toBe([0, 1]);
    });

    it('keeps an existing category on store 0 ("All Store Views")', function () {
        $category = Mage::getModel('blog/category');
        $category->setId(789)->setData('stores', [0]);

        $form = withSingleStoreMode(fn() => prepareScopeForm(
            Maho_Blog_Block_Adminhtml_Category_Edit_Tab_General::class,
            'blog_category',
            $category,
        ));

        expect((string) $form->getElement('stores_0')->getValue())->toBe('0');
    });
});
