<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Payment
 */

declare(strict_types=1);

interface Mage_Payment_Model_Billing_Agreement_MethodInterface
{
    /**
     * Init billing agreement
     */
    public function initBillingAgreementToken(Mage_Payment_Model_Billing_AgreementAbstract $agreement);

    /**
     * Retrieve billing agreement details
     */
    public function getBillingAgreementTokenInfo(Mage_Payment_Model_Billing_AgreementAbstract $agreement);

    /**
     * Create billing agreement
     */
    public function placeBillingAgreement(Mage_Payment_Model_Billing_AgreementAbstract $agreement);

    /**
     * Update billing agreement status
     */
    public function updateBillingAgreementStatus(Mage_Payment_Model_Billing_AgreementAbstract $agreement);
}
