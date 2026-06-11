<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Revocation
 */

declare(strict_types=1);

class Maho_Revocation_Block_Form extends Mage_Core_Block_Template
{
    public function getFormActionUrl(): string
    {
        return Mage::getUrl('revocation/index/submit');
    }

    /**
     * Encrypted render timestamp for the submit-time bot check. Note that under full
     * page cache a stale token only ever inflates the measured elapsed time, so the
     * timing gate degrades open (never blocks a legitimate user); the form key stays
     * dynamic via getBlockHtml('formkey') in the template.
     */
    public function getRenderToken(): string
    {
        return (string) Mage::helper('core')->encrypt((string) time());
    }

    public function getPrefillOrder(): ?Mage_Sales_Model_Order
    {
        $order = Mage::registry('revocation_prefill_order');
        return $order instanceof Mage_Sales_Model_Order ? $order : null;
    }

    public function isPrefilled(): bool
    {
        return $this->getPrefillOrder() !== null;
    }

    /**
     * Previously typed values (after a validation error), my-account prefill,
     * or logged-in customer defaults, in that priority order.
     */
    public function getFormData(): \Maho\DataObject
    {
        $sessionData = Mage::getSingleton('core/session')->getRevocationFormData(true);
        if (is_array($sessionData) && $sessionData !== []) {
            return new \Maho\DataObject($sessionData);
        }

        $data = new \Maho\DataObject();
        if ($order = $this->getPrefillOrder()) {
            $data->setCustomerName(trim($order->getCustomerFirstname() . ' ' . $order->getCustomerLastname()));
            $data->setEmail($order->getCustomerEmail());
            $data->setOrderReference($order->getIncrementId());
            $data->setSessionOrderId((int) $order->getId());
            return $data;
        }

        $customerSession = Mage::getSingleton('customer/session');
        if ($customerSession->isLoggedIn()) {
            $customer = $customerSession->getCustomer();
            $data->setCustomerName(trim($customer->getFirstname() . ' ' . $customer->getLastname()));
            $data->setEmail($customer->getEmail());
        }

        return $data;
    }
}
