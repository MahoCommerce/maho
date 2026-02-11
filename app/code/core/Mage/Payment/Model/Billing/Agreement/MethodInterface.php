<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Payment
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

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
