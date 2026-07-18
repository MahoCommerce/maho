<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Payment
 */

declare(strict_types=1);

class Mage_Payment_Block_Info_Purchaseorder extends Mage_Payment_Block_Info
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('payment/info/purchaseorder.phtml');
    }

    /**
     * @return string
     */
    #[\Override]
    public function toPdf()
    {
        $this->setTemplate('payment/info/pdf/purchaseorder.phtml');
        return $this->toHtml();
    }
}
