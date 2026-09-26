<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

/**
 * Class Mage_Customer_Block_Widget_Taxvat
 *
 * @package    Mage_Customer
 */
class Mage_Customer_Block_Widget_Taxvat extends Mage_Customer_Block_Widget_Abstract
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('customer/widget/taxvat.phtml');
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return (bool) $this->_getAttribute('taxvat')->getIsVisible();
    }

    /**
     * @return bool
     */
    public function isRequired()
    {
        return (bool) $this->_getAttribute('taxvat')->getIsRequired();
    }

    /**
     * @return Mage_Customer_Model_Customer
     */
    public function getCustomer()
    {
        return Mage::getSingleton('customer/session')->getCustomer();
    }

    public function setFieldIdFormat(?string $value): static
    {
        return $this->setData('field_id_format', $value);
    }

    public function setFieldNameFormat(?string $value): static
    {
        return $this->setData('field_name_format', $value);
    }

    public function setTaxvat(?string $value): static
    {
        return $this->setData('taxvat', $value);
    }
}
