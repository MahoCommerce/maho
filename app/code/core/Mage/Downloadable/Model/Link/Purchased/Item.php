<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Downloadable
 */

/**
 * Downloadable links purchased item model
 *
 * @package    Mage_Downloadable
 *
 * @method Mage_Downloadable_Model_Resource_Link_Purchased_Item _getResource()
 * @method Mage_Downloadable_Model_Resource_Link_Purchased_Item getResource()
 * @method Mage_Downloadable_Model_Resource_Link_Purchased_Item_Collection getCollection()
 */
class Mage_Downloadable_Model_Link_Purchased_Item extends Mage_Core_Model_Abstract
{
    public const XML_PATH_ORDER_ITEM_STATUS = 'catalog/downloadable/order_item_status';

    public const LINK_STATUS_PENDING   = 'pending';
    public const LINK_STATUS_AVAILABLE = 'available';
    public const LINK_STATUS_EXPIRED   = 'expired';
    public const LINK_STATUS_PENDING_PAYMENT = 'pending_payment';
    public const LINK_STATUS_PAYMENT_REVIEW = 'payment_review';

    #[\Override]
    protected function _construct()
    {
        $this->_init('downloadable/link_purchased_item');
        parent::_construct();
    }

    /**
     * Check order item id
     *
     * @return Mage_Core_Model_Abstract
     */
    #[\Override]
    protected function _beforeSave()
    {
        if ($this->getOrderItemId() == null) {
            throw new Exception(
                Mage::helper('downloadable')->__('Order item id cannot be null'),
            );
        }
        $this->setUpdatedAt(Mage::app()->getLocale()->formatDateForDb('now'));
        return parent::_beforeSave();
    }

    public function getIsShareable(): ?int
    {
        $value = $this->getData('is_shareable');
        return $value === null ? null : (int) $value;
    }

    public function setIsShareable(?int $value): static
    {
        return $this->setData('is_shareable', $value);
    }

    public function getLinkFile(): ?string
    {
        $value = $this->getData('link_file');
        return $value === null ? null : (string) $value;
    }

    public function setLinkFile(?string $value): static
    {
        return $this->setData('link_file', $value);
    }

    public function getLinkHash(): ?string
    {
        $value = $this->getData('link_hash');
        return $value === null ? null : (string) $value;
    }

    public function setLinkHash(?string $value): static
    {
        return $this->setData('link_hash', $value);
    }

    public function getLinkId(): ?int
    {
        $value = $this->getData('link_id');
        return $value === null ? null : (int) $value;
    }

    public function setLinkId(?int $value): static
    {
        return $this->setData('link_id', $value);
    }

    public function getLinkTitle(): ?string
    {
        $value = $this->getData('link_title');
        return $value === null ? null : (string) $value;
    }

    public function setLinkTitle(?string $value): static
    {
        return $this->setData('link_title', $value);
    }

    public function getLinkType(): ?string
    {
        $value = $this->getData('link_type');
        return $value === null ? null : (string) $value;
    }

    public function setLinkType(?string $value): static
    {
        return $this->setData('link_type', $value);
    }

    public function getLinkUrl(): ?string
    {
        $value = $this->getData('link_url');
        return $value === null ? null : (string) $value;
    }

    public function setLinkUrl(?string $value): static
    {
        return $this->setData('link_url', $value);
    }

    public function getNumberOfDownloadsBought(): ?int
    {
        $value = $this->getData('number_of_downloads_bought');
        return $value === null ? null : (int) $value;
    }

    public function setNumberOfDownloadsBought(?int $value): static
    {
        return $this->setData('number_of_downloads_bought', $value);
    }

    public function getNumberOfDownloadsUsed(): ?int
    {
        $value = $this->getData('number_of_downloads_used');
        return $value === null ? null : (int) $value;
    }

    public function setNumberOfDownloadsUsed(?int $value): static
    {
        return $this->setData('number_of_downloads_used', $value);
    }

    public function getOrder(): ?Mage_Sales_Model_Order
    {
        return $this->getData('order');
    }

    public function getOrderItemId(): ?int
    {
        $value = $this->getData('order_item_id');
        return $value === null ? null : (int) $value;
    }

    public function setOrderItemId(?int $value): static
    {
        return $this->setData('order_item_id', $value);
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

    public function getPurchasedId(): ?int
    {
        $value = $this->getData('purchased_id');
        return $value === null ? null : (int) $value;
    }

    public function setPurchasedId(?int $value): static
    {
        return $this->setData('purchased_id', $value);
    }

    public function getStatus(): ?string
    {
        $value = $this->getData('status');
        return $value === null ? null : (string) $value;
    }

    public function setStatus(?string $value): static
    {
        return $this->setData('status', $value);
    }

    public function getUpdatedAt(): ?string
    {
        $value = $this->getData('updated_at');
        return $value === null ? null : (string) $value;
    }

}
