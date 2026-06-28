<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tax
 */

declare(strict_types=1);

namespace Mage\Tax\Api;

use Maho\ApiPlatform\CrudProcessor;
use Maho\ApiPlatform\CrudResource;
use Maho\ApiPlatform\Security\ApiUser;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class TaxRuleProcessor extends CrudProcessor
{
    #[\Override]
    protected function validate(CrudResource $data, object $model, bool $isNew): void
    {
        /** @var TaxRule $data */

        // Code is required on create; on update an omitted code leaves the existing value untouched.
        if ($isNew && trim($data->code) === '') {
            throw new BadRequestHttpException('Tax rule code is required.');
        }

        // A rule needs at least one customer tax class, product tax class and rate.
        // On update an omitted (empty) association array preserves the existing links
        // (see beforeSave), so it's only mandatory on create.
        if ($isNew) {
            if ($data->customerTaxClassIds === []) {
                throw new BadRequestHttpException('At least one customer tax class is required.');
            }
            if ($data->productTaxClassIds === []) {
                throw new BadRequestHttpException('At least one product tax class is required.');
            }
            if ($data->taxRateIds === []) {
                throw new BadRequestHttpException('At least one tax rate is required.');
            }
        }
    }

    /**
     * The rule's associations (customer/product tax classes, rates) are persisted
     * by Mage_Tax_Model_Calculation_Rule::saveCalculationData() in _afterSave(),
     * which reads them from the tax_customer_class / tax_product_class / tax_rate
     * model fields — exactly as the admin RuleController sets them from POST data.
     *
     * Every save re-writes the link table from these three fields, so on update we
     * must always populate them: use the submitted arrays when present, otherwise
     * fall back to the rule's existing links to preserve them.
     */
    #[\Override]
    protected function beforeSave(object $model, CrudResource $data, ApiUser $user): void
    {
        /** @var TaxRule $data */
        /** @var \Mage_Tax_Model_Calculation_Rule $model */
        $isUpdate = (bool) $model->getId();

        $customer = $data->customerTaxClassIds;
        $product  = $data->productTaxClassIds;
        $rates    = $data->taxRateIds;

        if ($isUpdate) {
            $customer = $customer !== [] ? $customer : $model->getCustomerTaxClasses();
            $product  = $product !== [] ? $product : $model->getProductTaxClasses();
            $rates    = $rates !== [] ? $rates : $model->getRates();
        }

        $model->setData('tax_customer_class', array_values(array_map('intval', $customer)));
        $model->setData('tax_product_class', array_values(array_map('intval', $product)));
        $model->setData('tax_rate', array_values(array_map('intval', $rates)));
    }
}
