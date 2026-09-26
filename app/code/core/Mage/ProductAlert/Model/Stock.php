<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ProductAlert
 */

declare(strict_types=1);

/**
 * ProductAlert for back in stock model
 *
 * @package    Mage_ProductAlert
 *
 * @method Mage_ProductAlert_Model_Resource_Stock _getResource()
 * @method Mage_ProductAlert_Model_Resource_Stock getResource()
 * @method Mage_ProductAlert_Model_Resource_Stock_Collection getCollection()
 */
class Mage_ProductAlert_Model_Stock extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('productalert/stock');
    }

    /**
     * @return Mage_ProductAlert_Model_Resource_Stock_Customer_Collection
     */
    public function getCustomerCollection()
    {
        return Mage::getResourceModel('productalert/stock_customer_collection');
    }

    /**
     * @return $this
     */
    public function loadByParam()
    {
        if (!is_null($this->getProductId()) && !is_null($this->getCustomerId()) && !is_null($this->getWebsiteId())) {
            $this->getResource()->loadByParam($this);
        }
        return $this;
    }

    /**
     * @param int $customerId
     * @param int $websiteId
     * @return $this
     */
    public function deleteCustomer($customerId, $websiteId = 0)
    {
        $this->getResource()->deleteCustomer($this, $customerId, $websiteId);
        return $this;
    }

    public function getAddDate(): ?string
    {
        $value = $this->getData('add_date');
        return $value === null ? null : (string) $value;
    }

    public function setAddDate(?string $value): static
    {
        return $this->setData('add_date', $value);
    }

    public function getCustomerId(): ?int
    {
        $value = $this->getData('customer_id');
        return $value === null ? null : (int) $value;
    }

    public function setCustomerId(?int $value): static
    {
        return $this->setData('customer_id', $value);
    }

    public function getProductId(): ?int
    {
        $value = $this->getData('product_id');
        return $value === null ? null : (int) $value;
    }

    public function setProductId(?int $value): static
    {
        return $this->setData('product_id', $value);
    }

    public function getSendCount(): ?int
    {
        $value = $this->getData('send_count');
        return $value === null ? null : (int) $value;
    }

    public function setSendCount(?int $value): static
    {
        return $this->setData('send_count', $value);
    }

    public function getSendDate(): ?string
    {
        $value = $this->getData('send_date');
        return $value === null ? null : (string) $value;
    }

    public function setSendDate(?string $value): static
    {
        return $this->setData('send_date', $value);
    }

    public function getStatus(): ?int
    {
        $value = $this->getData('status');
        return $value === null ? null : (int) $value;
    }

    public function setStatus(?int $value): static
    {
        return $this->setData('status', $value);
    }

    public function getWebsiteId(): ?int
    {
        $value = $this->getData('website_id');
        return $value === null ? null : (int) $value;
    }

    public function setWebsiteId(?int $value): static
    {
        return $this->setData('website_id', $value);
    }

}
