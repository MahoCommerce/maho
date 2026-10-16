<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('creates the On Sale widget block from the widget type that CMS content stores', function () {
    $type = 'catalogrule/product_widget_onsale';

    expect(Mage::getSingleton('widget/widget')->getXmlElementByType($type))->toBeInstanceOf(Maho\Simplexml\Element::class);
    expect(Mage::app()->getLayout()->createBlock($type))->toBeInstanceOf(Mage_CatalogRule_Block_Product_Widget_Onsale::class);
});
