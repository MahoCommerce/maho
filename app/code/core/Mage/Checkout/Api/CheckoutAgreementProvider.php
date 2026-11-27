<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

namespace Mage\Checkout\Api;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Service\StoreContext;
use Mage_Checkout_Model_Agreement as Agreement;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class CheckoutAgreementProvider extends \Maho\ApiPlatform\Provider
{
    /**
     * @return list<Agreement>
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        StoreContext::ensureStore();
        $storeId = (int) StoreContext::getStoreId();
        $service = \Mage::getService('checkout/agreement');

        if ($operation->getName() === 'my_pending_checkout_agreements') {
            $customer = \Mage::getService('customer/customer')->getById($this->requireCustomerId());
            return $service->getPendingForCustomer($customer, $storeId);
        }

        $location = $this->stringFilter($context['filters'] ?? [], 'location') ?? Agreement::LOCATION_CHECKOUT;
        if (!in_array($location, [Agreement::LOCATION_CHECKOUT, Agreement::LOCATION_REGISTRATION], true)) {
            throw new BadRequestHttpException('location must be checkout or registration');
        }
        return $service->getActive($storeId, $location);
    }
}
