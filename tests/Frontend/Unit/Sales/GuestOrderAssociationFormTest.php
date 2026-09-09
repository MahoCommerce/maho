<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

/**
 * The guest order association action changes state, so its route accepts POST only.
 * A template that offers the action with a plain link produces a 405 page.
 */

function guestOrderAssociationTemplate(): string
{
    return (string) file_get_contents(
        Mage::getBaseDir('design') . '/frontend/base/default/template/sales/order/missing.phtml',
    );
}

describe('Guest order association is offered as a POST form', function () {
    it('rejects GET on the route', function () {
        $context = new \Symfony\Component\Routing\RequestContext();
        $context->setMethod('GET');
        $matcher = \Maho\Routing\RouteCollectionBuilder::createMatcher($context);

        expect(fn() => $matcher->match('/sales/order/associateGuestOrders'))
            ->toThrow(\Symfony\Component\Routing\Exception\MethodNotAllowedException::class);
    });

    it('accepts POST on the route', function () {
        $context = new \Symfony\Component\Routing\RequestContext();
        $context->setMethod('POST');
        $matcher = \Maho\Routing\RouteCollectionBuilder::createMatcher($context);

        expect($matcher->match('/sales/order/associateGuestOrders')['_route'] ?? null)
            ->toBe('sales.order.associateguestorders');
    });

    it('submits the action with a POST form and a form key', function () {
        $template = guestOrderAssociationTemplate();

        expect($template)->toMatch('/<form\b[^\n]*method="post"/');
        expect($template)->toContain("getBlockHtml('formkey')");
    });

    it('does not offer the action as a link', function () {
        $template = guestOrderAssociationTemplate();

        expect($template)->not->toMatch('/<a\b[^>]*associateGuestOrders/i');
    });
});
