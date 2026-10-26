<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

/**
 * A forwarded request keeps its path. A router that matches the path, such as the CMS router
 * at the URL of the No Route Page, must not get the request again, or the forward loops.
 */
function forwardedRequestFront(): array
{
    $pathRouter = new class extends Mage_Core_Controller_Varien_Router_Abstract {
        public int $calls = 0;

        #[\Override]
        public function match(Mage_Core_Controller_Request_Http $request): bool
        {
            $this->calls++;
            return false;
        }
    };
    $defaultRouter = new class extends Mage_Core_Controller_Varien_Router_Abstract {
        #[\Override]
        public function match(Mage_Core_Controller_Request_Http $request): bool
        {
            $request->setDispatched(true);
            return true;
        }
    };
    $front = new Mage_Core_Controller_Varien_Front();
    $front->addRouter('cms', $pathRouter)->addRouter('default', $defaultRouter);
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create('http://localhost/no-route'));
    return [$front, $pathRouter, $request];
}

describe('Front controller dispatch loop', function () {
    it('gives a forwarded request to the default router only', function () {
        [$front, $pathRouter, $request] = forwardedRequestFront();
        $request->setModuleName('cms')->setControllerName('page')->setActionName('view')->initForward();
        $request->setActionName('noRoute')->setDispatched(false);

        (fn() => $this->_runDispatchLoop($request))->call($front);

        expect($pathRouter->calls)->toBe(0)
            ->and($request->getActionName())->toBe('noRoute')
            ->and($request->isDispatched())->toBeTrue();
    });

    it('gives a request that is not forwarded to the other routers first', function () {
        [$front, $pathRouter, $request] = forwardedRequestFront();

        (fn() => $this->_runDispatchLoop($request))->call($front);

        expect($pathRouter->calls)->toBe(1)
            ->and($request->isDispatched())->toBeTrue();
    });
});
