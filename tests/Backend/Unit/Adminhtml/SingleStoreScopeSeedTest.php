<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/*
 * In single-store mode the admin forms below replace their scope selector with a
 * hidden field carrying the current scope. Two things have to hold for that to be
 * safe, and both were broken:
 *
 *  1. the value has to survive `$form->setValues($model->getData())`, which nulls
 *     every element the data doesn't mention - so a *new* record posted an empty
 *     scope (segments rejected with "select at least one website", blog records
 *     landed on store 0);
 *  2. it must not overwrite the scope an *existing* record was saved with. Single
 *     store mode means one store *view*, not one website, and a blog record may
 *     legitimately sit on store 0 ("All Store Views").
 */

/**
 * Force isSingleStoreMode() on, whatever the test install's store count is.
 * `_isSingleStore` is protected with no public setter for `true`.
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

        // Resolve the expectation inside the same context: getStore(true) answers
        // with the current store only while single-store mode is on.
        [$form, $websiteId] = withSingleStoreMode(fn() => [
            prepareScopeForm(
                Maho_CustomerSegmentation_Block_Adminhtml_Segment_Edit_Tab_General::class,
                'current_customer_segment',
                $segment,
            ),
            currentWebsiteId(),
        ]);

        // Empty here is the original bug: the field posts nothing and the save
        // fails with "Please select at least one website", with no field to fix.
        expect((string) $form->getElement('website_ids')->getValue())->toBe($websiteId);
    });

    it('keeps an existing segment on the website it was saved with', function () {
        $segment = Mage::getModel('customersegmentation/segment');
        // _afterLoad() hands the form an array; 7 is deliberately not the current
        // website - a website with no store view still counts as single-store.
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

        expect((string) $form->getElement('stores')->getValue())->toBe($storeId);
    });

    it('keeps an existing post on store 0 ("All Store Views")', function () {
        $post = Mage::getModel('blog/post');
        $post->setId(456)->setData('stores', [0]);

        $form = withSingleStoreMode(fn() => prepareScopeForm(
            Maho_Blog_Block_Adminhtml_Post_Edit_Tab_Content::class,
            'blog_post',
            $post,
        ));

        expect((string) $form->getElement('stores')->getValue())->toBe('0');
    });

    it('keeps an existing category on store 0 ("All Store Views")', function () {
        $category = Mage::getModel('blog/category');
        $category->setId(789)->setData('stores', [0]);

        $form = withSingleStoreMode(fn() => prepareScopeForm(
            Maho_Blog_Block_Adminhtml_Category_Edit_Tab_General::class,
            'blog_category',
            $category,
        ));

        expect((string) $form->getElement('stores')->getValue())->toBe('0');
    });
});
