<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

/**
 * Search Order Model
 *
 * @package    Mage_Adminhtml
 *
 * @method bool hasLimit()
 * @method bool hasQuery()
 * @method bool hasStart()
 */
class Mage_Adminhtml_Model_Search_Order extends \Maho\DataObject
{
    /**
     * Load search results
     *
     * @return $this
     */
    public function load()
    {
        $arr = [];

        if (!$this->hasStart() || !$this->hasLimit() || !$this->hasQuery()) {
            $this->setResults($arr);
            return $this;
        }

        $query = $this->getQuery();
        //TODO: add full name logic
        $collection = Mage::getResourceModel('sales/order_collection')
            ->addAttributeToSelect('*')
            ->addAttributeToSearchFilter([
                ['attribute' => 'increment_id',       'like' => $query . '%'],
                ['attribute' => 'billing_firstname',  'like' => $query . '%'],
                ['attribute' => 'billing_lastname',   'like' => $query . '%'],
                ['attribute' => 'billing_telephone',  'like' => $query . '%'],

                ['attribute' => 'shipping_firstname', 'like' => $query . '%'],
                ['attribute' => 'shipping_lastname',  'like' => $query . '%'],
                ['attribute' => 'shipping_telephone', 'like' => $query . '%'],
            ])
            ->setCurPage($this->getStart())
            ->setPageSize($this->getLimit())
            ->load();

        foreach ($collection as $order) {
            // The collection does not select the billing name, so the name of the order customer is the fallback
            $customerName = trim($order->getBillingFirstname() . ' ' . $order->getBillingLastname());
            if ($customerName === '') {
                $customerName = trim($order->getCustomerFirstname() . ' ' . $order->getCustomerLastname());
            }
            $arr[] = [
                'id'                => 'order/1/' . $order->getId(),
                'type'              => Mage::helper('adminhtml')->__('Order'),
                'name'              => Mage::helper('adminhtml')->__('Order #%s', $order->getIncrementId()),
                'description'       => $customerName,
                'form_panel_title'  => Mage::helper('adminhtml')->__('Order #%s (%s)', $order->getIncrementId(), $customerName),
                'url' => Mage::helper('adminhtml')->getUrl('*/sales_order/view', ['order_id' => $order->getId()]),
            ];
        }

        $this->setResults($arr);

        return $this;
    }

    public function getLimit(): ?int
    {
        $value = $this->getData('limit');
        return $value === null ? null : (int) $value;
    }

    public function getQuery(): ?string
    {
        $value = $this->getData('query');
        return $value === null ? null : (string) $value;
    }

    public function setResults(?array $value): static
    {
        return $this->setData('results', $value);
    }

    public function getStart(): ?int
    {
        $value = $this->getData('start');
        return $value === null ? null : (int) $value;
    }

}
