<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

class Mage_Sales_Block_Payment_Info_Billing_Agreement extends Mage_Payment_Block_Info
{
    /**
     * Add reference id to payment method information
     *
     * @param \Maho\DataObject|array $transport
     * @return \Maho\DataObject|null
     */
    #[\Override]
    protected function _prepareSpecificInformation($transport = null)
    {
        if ($this->_paymentSpecificInformation !== null) {
            return $this->_paymentSpecificInformation;
        }
        $info = $this->getInfo();
        $referenceID = $info->getAdditionalInformation(
            Mage_Sales_Model_Payment_Method_Billing_AgreementAbstract::PAYMENT_INFO_REFERENCE_ID,
        );
        $transport = new \Maho\DataObject([$this->__('Reference ID') => $referenceID,]);
        $transport = parent::_prepareSpecificInformation($transport);

        return $transport;
    }
}
