<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoFrontendTestCase::class);

function notFoundObserverHits(string $path, int $storeId): int
{
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');
    return (int) $adapter->fetchOne(
        $adapter->select()
            ->from($resource->getTableName('reports/not_found'), ['hits'])
            ->where('request_path = ?', $path)
            ->where('store_id = ?', $storeId),
    );
}

function notFoundObserverSend(string $path, int $status): void
{
    Mage::app()->setRequest(new Mage_Core_Controller_Request_Http(SymfonyRequest::create('http://localhost/' . $path)));
    $response = new Mage_Core_Controller_Response_Http();
    $response->setHttpResponseCode($status);
    Mage::app()->setResponse($response);
    Mage::dispatchEvent('controller_front_send_response_before', ['front' => Mage::app()->getFrontController()]);
}

describe('404 Errors report observer', function () {
    beforeEach(function () {
        Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
        $this->store = Mage::app()->getStore();
        $this->store->setConfig(Mage_Reports_Model_NotFound::XML_PATH_ENABLED, '1');
        $this->store->setConfig(Mage_Reports_Model_NotFound::XML_PATH_LOG_RATE_LIMIT, '0');
        $this->path = 'missing-page-' . uniqid();
        Mage::app()->addEventArea(Mage_Core_Model_App_Area::AREA_FRONTEND);
    });

    afterEach(function () {
        $resource = Mage::getSingleton('core/resource');
        $resource->getConnection('core_write')->delete(
            $resource->getTableName('reports/not_found'),
            ['request_path = ?' => $this->path],
        );
    });

    it('counts a 404 when the storefront sends its response', function () {
        notFoundObserverSend($this->path, 404);

        expect(notFoundObserverHits($this->path, (int) $this->store->getId()))->toBe(1);
    });

    it('ignores a response that is not 404', function () {
        notFoundObserverSend($this->path, 200);

        expect(notFoundObserverHits($this->path, (int) $this->store->getId()))->toBe(0);
    });
});
