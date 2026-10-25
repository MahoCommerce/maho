<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Data\Form;
use Maho\Data\Form\Element\Image;

uses(Tests\MahoBackendTestCase::class);

describe('Maho\Data\Form\Element\Image preview', function () {
    it('shows the smallest stored size when the field sets image_entity, and links the original', function () {
        $form = new Form();
        $element = $form->addField('image', 'image', [
            'name' => 'image',
            'base_url' => Mage::getBaseUrl('media') . 'blog/',
            'image_entity' => Maho_Blog_Model_Post_Image::ENTITY,
        ]);
        $element->setValue('sports/preview-test.webp');

        $storeId = (int) Mage::app()->getDefaultStoreView()?->getId();
        $preview = Mage::helper('core/image')
            ->init(Maho_Blog_Model_Post_Image::ENTITY, 'sports/preview-test.webp', $storeId)
            ->getUrl(Image::PREVIEW_WIDTH);
        $html = $element->getElementHtml();

        expect($preview)->toContain('/cache/')
            ->and($html)->toContain('<img src="' . $preview . '"')
            ->and($html)->toContain('<a href="' . Mage::getBaseUrl('media') . 'blog/sports/preview-test.webp"');
    });

    it('shows the original file when the field sets no image_entity', function () {
        $form = new Form();
        $element = $form->addField('image', 'image', [
            'name' => 'image',
            'base_url' => Mage::getBaseUrl('media') . 'blog/',
        ]);
        $element->setValue('sports/preview-test.webp');

        expect($element->getElementHtml())
            ->toContain('<img src="' . Mage::getBaseUrl('media') . 'blog/sports/preview-test.webp"');
    });
});
