<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

describe('the signed resize URL of earlier releases', function () {
    beforeEach(function (): void {
        $this->previousStore = Mage::app()->getStore()->getId();
        $this->previousAction = Mage::app()->getFrontController()->getAction();
        Mage::app()->setCurrentStore((int) Mage::app()->getDefaultStoreView()->getId());

        $this->resize = function (array $params): Mage_Core_Controller_Response_Http {
            parse_str(withoutDeprecations(fn() => Maho::signImageResizeRequest($params, Mage::getEncryptionKeyAsHex())), $query);
            $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('/core/index/resize', 'GET', $query));
            $response = new Mage_Core_Controller_Response_Http();
            new Mage_Core_IndexController($request, $response)->resizeAction();
            return $response;
        };
    });

    afterEach(function (): void {
        Mage::app()->getFrontController()->setAction($this->previousAction);
        Mage::app()->setCurrentStore($this->previousStore);
    });

    it('redirects with 301 to the product image file of the nearest width', function (): void {
        $response = ($this->resize)(['_sourceFile' => '/n/o/nofile.jpg', '_width' => 120, '_height' => null]);

        expect($response->getHttpResponseCode())->toBe(301)
            ->and($response->getSymfonyResponse()->headers->get('Location'))
            ->toBe(Mage::helper('core/image')->init('product', '/n/o/nofile.jpg')->getUrl(120));
    });

    it('answers 404 to signed params without a source file', function (): void {
        $response = ($this->resize)(['_width' => 120, '_height' => null]);

        expect($response->getHttpResponseCode())->toBe(404)
            ->and($response->getSymfonyResponse()->headers->has('Location'))->toBeFalse();
    });
});
