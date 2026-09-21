<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

/**
 * Issue #1305: the no-route page answers 410 Gone for a URL recorded in core_url_gone
 * and 404 Not Found for any other unknown URL.
 */
function noRoute410Dispatch(string $uri): Mage_Core_Controller_Response_Http
{
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create($uri));
    $response = new Mage_Core_Controller_Response_Http();
    $controller = new Mage_Cms_IndexController($request, $response);
    $controller->norouteAction();
    return $response;
}

describe('No-route page status code', function () {
    beforeEach(function () {
        Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
        $this->storeId = (int) Mage::app()->getStore()->getId();
        $this->path = 'deleted-product-' . uniqid() . '.html';
        $this->write = Mage::getSingleton('core/resource')->getConnection('core_write');
        $this->table = Mage::getSingleton('core/resource')->getTableName('core/url_gone');
    });

    afterEach(function () {
        $this->write->delete($this->table, ['request_path = ?' => $this->path]);
    });

    it('returns 404 for an unknown URL', function () {
        $response = noRoute410Dispatch('http://localhost/' . $this->path);

        expect($response->getHttpResponseCode())->toBe(404);
    });

    it('returns 410 for the URL of a deleted product', function () {
        $this->write->insert($this->table, [
            'store_id' => $this->storeId,
            'request_path' => $this->path,
            'entity_type' => Mage_Core_Model_Url_Gone::ENTITY_TYPE_PRODUCT,
            'deleted_at' => Mage::app()->getLocale()->formatDateForDb('now'),
        ]);

        $response = noRoute410Dispatch('http://localhost/' . $this->path);

        expect($response->getHttpResponseCode())->toBe(410);
    });

    it('returns 410 for the same URL with a trailing slash', function () {
        $this->write->insert($this->table, [
            'store_id' => $this->storeId,
            'request_path' => $this->path,
            'entity_type' => Mage_Core_Model_Url_Gone::ENTITY_TYPE_PRODUCT,
            'deleted_at' => Mage::app()->getLocale()->formatDateForDb('now'),
        ]);

        $response = noRoute410Dispatch('http://localhost/' . $this->path . '/');

        expect($response->getHttpResponseCode())->toBe(410);
    });

    it('returns 404 for a gone URL recorded for another store', function () {
        $otherStoreId = $this->storeId + 1000;
        $this->write->insert($this->table, [
            'store_id' => $this->storeId,
            'request_path' => $this->path,
            'entity_type' => Mage_Core_Model_Url_Gone::ENTITY_TYPE_PRODUCT,
            'deleted_at' => Mage::app()->getLocale()->formatDateForDb('now'),
        ]);

        $resource = Mage::getResourceSingleton('core/url_gone');

        expect($resource->isGone([$this->path], $otherStoreId))->toBeFalse();
    });
});
