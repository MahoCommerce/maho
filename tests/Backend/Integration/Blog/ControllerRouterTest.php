<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

/**
 * A blog action that forwards to noRoute keeps the path of the blog. The blog router must
 * not match that path again, or the front controller loops until its forward limit.
 */
describe('Blog router', function () {
    beforeEach(function () {
        Mage::app()->setCurrentStore(Mage::app()->getDefaultStoreView());
    });

    it('leaves the forward to the no-route action to the default router', function () {
        $request = new Mage_Core_Controller_Request_Http(
            SymfonyRequest::create('http://localhost/' . Mage::helper('blog')->getBlogUrlPrefix()),
        );
        $request->setModuleName('blog')->setControllerName('index')->setActionName('index')->initForward();
        $request->setActionName('noRoute')->setDispatched(false);

        expect(new Maho_Blog_Controller_Router()->match($request))->toBeFalse()
            ->and($request->getControllerName())->toBe('index')
            ->and($request->getActionName())->toBe('noRoute')
            ->and($request->isDispatched())->toBeFalse();
    });
});
