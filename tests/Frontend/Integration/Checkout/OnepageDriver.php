<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

/**
 * Post $post to the action $action of the one page checkout, with $quote as the cart of the session. The action
 * runs as on a real request, but the HTML of the next step is empty: the tests compare what the step does, not
 * the page. Return the JSON of the response.
 *
 * @param array<string, mixed> $post
 * @return array<string, mixed>
 */
function checkoutOnepagePost(Mage_Sales_Model_Quote $quote, string $action, array $post): array
{
    $session = Mage::getSingleton('checkout/session');
    $session->replaceQuote(Mage::getModel('sales/quote')->loadByIdWithoutStore($quote->getId()));
    $session->setCartWasUpdated(false);
    // The controller takes the checkout model as a singleton, which keeps the cart of an earlier call
    Mage::unregister('_singleton/checkout/type_onepage');

    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create("/checkout/onepage/{$action}", 'POST', $post));
    $request->setRouteName('checkout')->setControllerName('onepage')->setActionName($action)->setDispatched(true);
    Mage::app()->setRequest($request);
    $controller = new class ($request, new Mage_Core_Controller_Response_Http()) extends Mage_Checkout_OnepageController {
        #[\Override]
        public function loadLayout($handles = null, $generateBlocks = true, $generateXml = true)
        {
            return $this;
        }

        #[\Override]
        protected function _getShippingMethodsHtml()
        {
            return '';
        }

        #[\Override]
        protected function _getPaymentMethodsHtml()
        {
            return '';
        }

        #[\Override]
        protected function _getAdditionalHtml()
        {
            return '';
        }

        #[\Override]
        protected function _getReviewHtml()
        {
            return '';
        }
    };
    $controller->{$action . 'Action'}();

    $body = (string) $controller->getResponse()->getBody();

    return $body === '' ? [] : Mage::helper('core')->jsonDecode($body);
}
