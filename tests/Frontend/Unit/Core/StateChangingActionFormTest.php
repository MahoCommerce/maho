<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

/**
 * A storefront action that changes state accepts POST only, and every template that
 * offers it submits a form carrying the form key instead of navigating with a link.
 */

function stateChangingTemplate(string $path): string
{
    return (string) file_get_contents(
        Mage::getBaseDir('design') . '/frontend/base/default/template/' . $path,
    );
}

function stateChangingMatcher(string $method): \Symfony\Component\Routing\Matcher\UrlMatcherInterface
{
    $context = new \Symfony\Component\Routing\RequestContext();
    $context->setMethod($method);
    return \Maho\Routing\RouteCollectionBuilder::createMatcher($context);
}

dataset('post only routes', [
    'recurring profile state' => ['/sales/recurring_profile/updateState', 'updateStateAction'],
    'recurring profile update' => ['/sales/recurring_profile/updateProfile', 'updateProfileAction'],
    'billing agreement cancel' => ['/sales/billing_agreement/cancel', 'cancelAction'],
    'customer reorder' => ['/sales/order/reorder', 'reorderAction'],
    'guest reorder' => ['/sales/guest/reorder', 'reorderAction'],
    'tag save' => ['/tag/index/save', 'saveAction'],
    'price alert signup' => ['/productalert/add/price', 'priceAction'],
    'stock alert signup' => ['/productalert/add/stock', 'stockAction'],
    'partial authorization cancel' => ['/paygate/authorizenet_payment/cancel', 'cancelAction'],
    'shared wishlist add all to cart' => ['/wishlist/shared/allcart', 'allcartAction'],
]);

dataset('state changing templates', [
    'recurring profile view' => [
        'sales/recurring/profile/view.phtml',
        ['method="post"', "getBlockHtml('formkey')"],
        '/window\.location/',
    ],
    'billing agreement view' => [
        'sales/billing/agreement/view.phtml',
        ['method="post"', "getBlockHtml('formkey')"],
        '/window\.location/',
    ],
    'order history' => [
        'sales/order/history.phtml',
        ['method="post"', "getBlockHtml('formkey')"],
        '/<a\b[^>]*getReorderUrl/',
    ],
    'recent orders' => [
        'sales/order/recent.phtml',
        ['method="post"', "getBlockHtml('formkey')"],
        '/<a\b[^>]*getReorderUrl/',
    ],
    'order info buttons' => [
        'sales/order/info/buttons.phtml',
        ['method="post"', "getBlockHtml('formkey')"],
        '/<a\b[^>]*getReorderUrl/',
    ],
    'compare sidebar' => [
        'catalog/product/compare/sidebar.phtml',
        ['method="post"', "getBlockHtml('formkey')"],
        '/<a\b[^>]*(getRemoveUrl|getClearListUrl)/',
    ],
    'compare list' => [
        'catalog/product/compare/list.phtml',
        ['form_key'],
        '/body:\s*\'isAjax=1\'/',
    ],
    'customer tag view' => [
        'tag/customer/view.phtml',
        ['method="post"', "getBlockHtml('formkey')"],
        '/window\.location/',
    ],
    'product tag form' => [
        'tag/list.phtml',
        ['method="post"', "getBlockHtml('formkey')"],
        '/method="get"/',
    ],
    'product alert signup' => [
        'productalert/product/view.phtml',
        ['method="post"', "getBlockHtml('formkey')"],
        '/<a\b[^>]*getSignupUrl/',
    ],
    'shipping estimate' => [
        'checkout/cart/shipping.phtml',
        ["getBlockHtml('formkey')"],
        null,
    ],
    'cart item' => [
        'checkout/cart/item/default.phtml',
        ['getMoveFromCartUrl'],
        '/<a\b[^>]*href="<\?= \$this->helper\(\'wishlist\'\)->getMoveFromCartUrl/',
    ],
    'downloadable cart item' => [
        'downloadable/checkout/cart/item/default.phtml',
        ['getMoveFromCartUrl'],
        '/<a\b[^>]*href="<\?= \$this->helper\(\'wishlist\'\)->getMoveFromCartUrl/',
    ],
    'shared wishlist' => [
        'wishlist/shared.phtml',
        ['getSharedItemAddToCartUrl', 'customFormSubmit', "getUrl('*/*/allcart'"],
        '/setLocation\(/',
    ],
    'partial authorization form' => [
        'paygate/form/cc.phtml',
        ['getFormKey()'],
        '/typeof FORM_KEY/',
    ],
]);

describe('Storefront state-changing routes accept POST only', function () {
    it('rejects GET', function (string $path, string $action) {
        expect(fn() => stateChangingMatcher('GET')->match($path))
            ->toThrow(\Symfony\Component\Routing\Exception\MethodNotAllowedException::class);
    })->with('post only routes');

    it('accepts POST', function (string $path, string $action) {
        expect(stateChangingMatcher('POST')->match($path)['_maho_action'] ?? null)->toBe($action);
    })->with('post only routes');
});

describe('Storefront templates submit state changes with a form key', function () {
    it('offers the action as a POST form', function (string $path, array $required, ?string $forbidden) {
        $template = stateChangingTemplate($path);

        foreach ($required as $needle) {
            expect($template)->toContain($needle);
        }
        if ($forbidden !== null) {
            expect($template)->not->toMatch($forbidden);
        }
    })->with('state changing templates');
});
