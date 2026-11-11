<?php

/**
 * Subscribe a customer to the price alert or the stock alert of a product, and unsubscribe the customer, for the
 * storefront and the API.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ProductAlert
 */

declare(strict_types=1);

use Mage_ProductAlert_Model_Price as PriceAlert;
use Mage_ProductAlert_Model_Stock as StockAlert;
use Mage_ProductAlert_Model_Type as Type;

class Mage_ProductAlert_Service_Alert
{
    /**
     * Return whether the store $storeId lets a customer subscribe to the alerts of $type.
     */
    public function isEnabled(Type $type, int $storeId): bool
    {
        return Mage::getStoreConfigFlag(match ($type) {
            Type::Price => Mage_ProductAlert_Model_Observer::XML_PATH_PRICE_ALLOW,
            Type::Stock => Mage_ProductAlert_Model_Observer::XML_PATH_STOCK_ALLOW,
        }, $storeId);
    }

    /**
     * Return the alerts of the customer $customerId on the website of the store $storeId, of $type or of each type.
     *
     * @return list<PriceAlert|StockAlert>
     */
    public function getByCustomer(int $customerId, int $storeId, ?Type $type = null): array
    {
        $alerts = [];
        foreach ($type === null ? Type::cases() : [$type] as $case) {
            $collection = $this->createAlert($case)->getCollection()
                ->addFieldToFilter('customer_id', $customerId)
                ->addFieldToFilter('website_id', $this->getWebsiteId($storeId));
            foreach ($collection as $alert) {
                $alerts[] = $alert;
            }
        }
        return $alerts;
    }

    /**
     * Return the alert of $type that the customer $customerId has for the product $productId.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when the customer has no such alert
     */
    public function getByProduct(Type $type, int $customerId, int $productId, int $storeId): PriceAlert|StockAlert
    {
        $alert = $this->createAlert($type)
            ->setCustomerId($customerId)
            ->setProductId($productId)
            ->setWebsiteId($this->getWebsiteId($storeId))
            ->loadByParam();
        if (!$alert->getId()) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('productalert')->__('You have no alert for this product.'));
        }
        return $alert;
    }

    /**
     * Subscribe the customer $customerId to the alert of $type for the product $productId. A price alert keeps the
     * current price of the product for the group of the customer. A customer who has the alert already gets it
     * again, ready for a new email.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when the store has no alert of $type, or the customer does not exist
     * @throws Mage_Core_Exception when the store does not show the product
     */
    public function subscribe(Type $type, int $customerId, int $productId, int $storeId): PriceAlert|StockAlert
    {
        $helper = Mage::helper('productalert');
        if (!$this->isEnabled($type, $storeId)) {
            throw new Mage_Core_Exception_NoSuchEntity($helper->__('This product alert is not available.'));
        }
        $customer = Mage::getService('customer/customer')->getById($customerId)
            ?? throw new Mage_Core_Exception_NoSuchEntity($helper->__('Customer not found'));
        $product = Mage::getModel('catalog/product')->setStoreId($storeId)->load($productId);
        if (!Mage::helper('catalog/product')->isVisibleInStore($product, $storeId)) {
            throw new Mage_Core_Exception($helper->__('The product was not found.'));
        }

        $alert = $this->createAlert($type)
            ->setCustomerId($customerId)
            ->setProductId($productId)
            ->setWebsiteId($this->getWebsiteId($storeId));
        if ($alert instanceof PriceAlert) {
            $alert->setPrice((float) $product->setCustomerGroupId((int) $customer->getGroupId())->getFinalPrice());
        }
        $alert->save();
        return $alert;
    }

    /**
     * Delete the alert of $type that the customer $customerId has for the product $productId.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when the customer has no such alert
     */
    public function unsubscribe(Type $type, int $customerId, int $productId, int $storeId): void
    {
        $this->getByProduct($type, $customerId, $productId, $storeId)->delete();
    }

    /**
     * Delete each alert of $type that the customer $customerId has on the website of the store $storeId.
     */
    public function unsubscribeAll(Type $type, int $customerId, int $storeId): void
    {
        $this->createAlert($type)->deleteCustomer($customerId, $this->getWebsiteId($storeId));
    }

    private function createAlert(Type $type): PriceAlert|StockAlert
    {
        return match ($type) {
            Type::Price => Mage::getModel('productalert/price'),
            Type::Stock => Mage::getModel('productalert/stock'),
        };
    }

    private function getWebsiteId(int $storeId): int
    {
        return (int) Mage::app()->getStore($storeId)->getWebsiteId();
    }
}
