<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

/**
 * Mage_CatalogSearch_Service_Query::prepare() takes the text of a caller that has no search request, such as the
 * API, so no transport writes the text into the request (#1348).
 */

beforeEach(function (): void {
    $this->request = Mage::app()->getRequest();
    Mage::app()->setRequest(new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/', 'GET', ['q' => 'text of the request'])));
    Mage::unregister('_helper/catalogsearch');
});

afterEach(function (): void {
    Mage::unregister('_helper/catalogsearch');
    Mage::app()->setRequest($this->request);
});

it('searches for the text of the caller, and leaves the request as it is', function (): void {
    /** @var Mage_CatalogSearch_Service_Query $service */
    $service = Mage::getService('catalogsearch/query');

    $query = $service->prepare(1, '  text of the caller  ');

    expect($query->getQueryText())->toBe('text of the caller')
        ->and(Mage::helper('catalogsearch')->getQueryText())->toBe('text of the caller')
        ->and(Mage::app()->getRequest()->getParam('q'))->toBe('text of the request');
});

it('does not read the text of the request', function (): void {
    /** @var Mage_CatalogSearch_Service_Query $service */
    $service = Mage::getService('catalogsearch/query');

    expect($service->prepare(1, '')->getQueryText())->toBe('')
        ->and(Mage::helper('catalogsearch')->getQueryText())->toBe('');
});

it('does not keep the text of an earlier search', function (): void {
    /** @var Mage_CatalogSearch_Service_Query $service */
    $service = Mage::getService('catalogsearch/query');

    $service->prepare(1, 'first text');

    expect($service->prepare(1, 'second text')->getQueryText())->toBe('second text');
});
