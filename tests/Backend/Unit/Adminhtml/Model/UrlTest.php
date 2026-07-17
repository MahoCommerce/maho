<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Regression coverage for secret key validation across `_forward()` (#1091).
 *
 * Bug: `getSecretKey()` with no arguments preferred the *dispatched* controller/action
 * names. After `indexAction()` calls `_forward('edit')` (Catalog > Categories,
 * System > Configuration), preDispatch re-runs `_validateSecretKey()` with the request
 * now pointing at `edit`, while the URL's key was minted for `index`: mismatch,
 * silent 302 to the dashboard.
 *
 * Fix: prefer the before-forward snapshot (`getBeforeForwardInfo()`), i.e. validate
 * against what the user actually requested.
 */

describe('Mage_Adminhtml_Model_Url::getSecretKey()', function () {
    beforeEach(function () {
        $this->url = Mage::getModel('adminhtml/url');
        $this->request = Mage::app()->getRequest();
        $this->request
            ->setControllerName('catalog_category')
            ->setActionName('index');
    });

    it('derives the key from the dispatched request when no forward happened', function () {
        expect($this->url->getSecretKey())
            ->toBe($this->url->getSecretKey('catalog_category', 'index'));
    });

    it('keeps validating against the originally requested action after _forward (regression #1091)', function () {
        $keyInUrl = $this->url->getSecretKey('catalog_category', 'index');

        // What Mage_Core_Controller_Varien_Action::_forward('edit') does to the request
        $this->request->initForward();
        $this->request->setActionName('edit');

        expect($this->url->getSecretKey())->toBe($keyInUrl);
    });

    it('keeps validating against the original controller and action when _forward changes both', function () {
        $keyInUrl = $this->url->getSecretKey('catalog_category', 'index');

        // e.g. the ACL _forward('denied') re-dispatch
        $this->request->initForward();
        $this->request->setControllerName('index');
        $this->request->setActionName('denied');

        expect($this->url->getSecretKey())->toBe($keyInUrl);
    });

    it('lets explicit arguments override any request state', function () {
        $this->request->initForward();
        $this->request->setActionName('edit');

        expect($this->url->getSecretKey('cms_page', 'save'))
            ->toBe(Mage::getModel('adminhtml/url')->getSecretKey('cms_page', 'save'));
    });
});

/**
 * Regression coverage for #1089: routes migrated by legacy:migrate-routes carry an
 * extra frontName segment (/admin/<frontName>/<controller>/<action>), so positional
 * path parsing read the frontName as the controller and the controller as the action.
 * The dispatched request names must win over path slicing.
 */
describe('Mage_Adminhtml_Model_Url::getSecretKey() path handling', function () {
    beforeEach(function () {
        $this->url = Mage::getModel('adminhtml/url');
        $this->request = Mage::app()->getRequest();
    });

    it('prefers dispatched names over positional path segments for migrated routes (regression #1089)', function () {
        // legacy:migrate-routes shape: admin/<frontName>/<controller>/<action>
        $this->request->setOriginalPathInfo('/admin/feedmanager/feed/index/key/abc123/');
        $this->request
            ->setControllerName('feedmanager_feed')
            ->setActionName('index');

        // Positional parsing would hash 'feedmanager' + 'feed' here, the wrong pair.
        expect($this->url->getSecretKey())
            ->toBe($this->url->getSecretKey('feedmanager_feed', 'index'));
    });

    it('agrees with the path segments on classic admin/<controller>/<action> routes', function () {
        $this->request->setOriginalPathInfo('/admin/catalog_product/edit/id/5/key/abc123/');
        $this->request
            ->setControllerName('catalog_product')
            ->setActionName('edit');

        expect($this->url->getSecretKey())
            ->toBe($this->url->getSecretKey('catalog_product', 'edit'));
    });

    it('falls back to positional path segments before dispatch populates the request names', function () {
        $this->request->setOriginalPathInfo('/admin/cms_page/edit/page_id/3/key/abc123/');

        expect($this->url->getSecretKey())
            ->toBe($this->url->getSecretKey('cms_page', 'edit'));
    });
});
