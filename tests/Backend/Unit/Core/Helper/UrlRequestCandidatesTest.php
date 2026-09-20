<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

describe('Request path candidates for URL rewrite lookups', function () {
    it('tries the original trailing slash state first, then the alternate one', function () {
        $helper = Mage::helper('core/url');

        expect($helper->getRequestPathCandidates('/my-product.html'))->toBe([
            'my-product.html',
            'my-product.html/',
        ]);
        expect($helper->getRequestPathCandidates('/my-category/'))->toBe([
            'my-category/',
            'my-category',
        ]);
    });

    it('puts the query string variants before the plain ones', function () {
        $helper = Mage::helper('core/url');

        expect($helper->getRequestPathCandidates('/page', 'a=1'))->toBe([
            'page?a=1',
            'page/?a=1',
            'page',
            'page/',
        ]);
    });

    it('strips internal ___ parameters from the query string', function () {
        $request = new Mage_Core_Controller_Request_Http(
            SymfonyRequest::create('http://localhost/page?___store=default&a=1'),
        );
        $helper = Mage::helper('core/url');

        expect($helper->getRewriteQueryString($request))->toBe('a=1');
    });

    it('returns false when the request has no query string', function () {
        $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('http://localhost/page'));

        expect(Mage::helper('core/url')->getRewriteQueryString($request))->toBeFalse();
    });
});
