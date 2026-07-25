<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * WYSIWYG-enabled catalog attributes (product description/short_description, category
 * description) are rendered through the template processor, so they may hold template
 * directives. They are sanitized on save without those directives being mangled.
 */
describe('product description sanitization', function () {
    beforeEach(function () {
        $this->product = Mage::getModel('catalog/product');
        $this->product->setStoreId(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID)
            ->setSku('desc-sanitization-' . uniqid())
            ->setName('Description Sanitization')
            ->setPrice(10.00)
            ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
            ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
            ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
            ->setAttributeSetId(4)
            ->setWebsiteIds([1]);
    });

    afterEach(function () {
        if ($this->product->getId()) {
            $this->product->delete();
        }
    });

    it('preserves template directives through save', function () {
        $this->product->setDescription('<p><img src="{{media url="wysiwyg/a.webp"}}" alt=""></p>')
            ->setShortDescription('<p>{{store url="checkout/cart"}}</p>')
            ->save();

        $loaded = Mage::getModel('catalog/product')
            ->setStoreId(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID)
            ->load($this->product->getId());

        expect($loaded->getDescription())->toContain('{{media url="wysiwyg/a.webp"}}')
            ->and($loaded->getDescription())->not->toContain('%7B%7B')
            ->and($loaded->getShortDescription())->toContain('{{store url="checkout/cart"}}');
    });

    it('strips malicious markup on save', function () {
        $this->product->setDescription('<p onclick="alert(1)">hi</p><script>alert(document.cookie)</script>')
            ->setShortDescription('{{a<script>alert(2)</script>}}')
            ->save();

        $loaded = Mage::getModel('catalog/product')
            ->setStoreId(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID)
            ->load($this->product->getId());

        expect($loaded->getDescription())->not->toContain('<script')
            ->and($loaded->getDescription())->not->toContain('onclick')
            ->and($loaded->getShortDescription())->not->toContain('<script');
    });

    it('does not force description links into a new tab', function () {
        $this->product->setDescription('<a href="/checkout/cart">Cart</a>')->save();

        $loaded = Mage::getModel('catalog/product')
            ->setStoreId(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID)
            ->load($this->product->getId());

        expect($loaded->getDescription())->not->toContain('target="_blank"');
    });

    it('leaves non-WYSIWYG attributes untouched', function () {
        // Only attributes the frontend renders through the template processor are filtered;
        // purifying a plain-text attribute would entity-encode it and then double-escape on output.
        $this->product->setName('Tools & Hardware <3')->save();

        $loaded = Mage::getModel('catalog/product')
            ->setStoreId(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID)
            ->load($this->product->getId());

        expect($loaded->getName())->toBe('Tools & Hardware <3');
    });
});

describe('category description sanitization', function () {
    it('preserves directives and strips malicious markup on save', function () {
        $category = Mage::getModel('catalog/category');
        $category->setName('Description Sanitization ' . uniqid())
            ->setPath('1/2')
            ->setIsActive(1)
            ->setAttributeSetId($category->getDefaultAttributeSetId())
            ->setDescription('<p><img src="{{media url="wysiwyg/a.webp"}}" alt=""></p><script>alert(1)</script>')
            ->save();

        $loaded = Mage::getModel('catalog/category')->load($category->getId());

        expect($loaded->getDescription())->toContain('{{media url="wysiwyg/a.webp"}}')
            ->and($loaded->getDescription())->not->toContain('<script');

        $category->delete();
    });
});
