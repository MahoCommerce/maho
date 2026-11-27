<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

namespace Mage\Checkout\Api;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Exception\ValidationException;
use Maho\ApiPlatform\Http\RequestInput;
use Maho\ApiPlatform\Service\StoreContext;
use Mage_Checkout_Model_Agreement_Acceptance as Acceptance;

final class AgreementAcceptanceProcessor extends \Maho\ApiPlatform\Processor
{
    private const array FIELDS = ['agreementId'];

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Acceptance
    {
        StoreContext::ensureStore();
        $this->normalizeGraphQlInput($context);
        $body = $context['args']['input'] ?? [];
        self::rejectUnknownFields($body, self::FIELDS);
        $agreementId = RequestInput::of($body)->int('agreementId') ?? throw ValidationException::requiredField('agreementId');

        $customer = \Mage::getService('customer/customer')->getById($this->requireCustomerId());
        $acceptances = \Mage::getService('checkout/agreement')->acceptForCustomer($customer, (int) StoreContext::getStoreId(), [$agreementId], \Mage::helper('checkout')->getAcceptanceClient());

        $acceptance = \Mage::getService('checkout/agreement')->getAcceptances((int) $customer->getId())
            ->addFieldToFilter('main_table.acceptance_id', (int) $acceptances[0]->getId())
            ->getFirstItem();
        return $acceptance instanceof Acceptance ? $acceptance : $acceptances[0];
    }
}
