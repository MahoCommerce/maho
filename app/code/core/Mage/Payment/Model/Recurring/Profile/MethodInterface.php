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

interface Mage_Payment_Model_Recurring_Profile_MethodInterface
{
    /**
     * Validate data
     *
     * @throws Mage_Core_Exception
     */
    public function validateRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile);

    /**
     * Submit to the gateway
     */
    public function submitRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile, Mage_Payment_Model_Info $paymentInfo);

    /**
     * Fetch details
     *
     * @param string $referenceId
     */
    public function getRecurringProfileDetails($referenceId, \Maho\DataObject $result);

    /**
     * Check whether can get recurring profile details
     *
     * @return bool
     */
    public function canGetRecurringProfileDetails();

    /**
     * Update data
     */
    public function updateRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile);

    /**
     * Manage status
     */
    public function updateRecurringProfileStatus(Mage_Payment_Model_Recurring_Profile $profile);
}
