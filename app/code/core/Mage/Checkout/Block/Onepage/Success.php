<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

/**
 * One page checkout success page
 *
 * @package    Mage_Checkout
 */
class Mage_Checkout_Block_Onepage_Success extends Mage_Core_Block_Template
{
    /**
     * Get url for view order details
     *
     * @return string
     */
    public function getViewOrderUrl()
    {
        return $this->_getData('view_order_id');
    }

    /**
     * See if the order has state, visible on frontend
     *
     * @return bool
     */
    public function isOrderVisible()
    {
        return (bool) $this->_getData('is_order_visible');
    }

    /**
     * Getter for recurring profile view page
     *
     * @return string
     */
    public function getProfileUrl(\Maho\DataObject $profile)
    {
        return $this->getUrl('sales/recurring_profile/view', ['profile' => $profile->getId()]);
    }

    /**
     * Initialize data and prepare it for output
     */
    #[\Override]
    protected function _beforeToHtml()
    {
        $this->_prepareLastOrder();
        $this->_prepareLastBillingAgreement();
        $this->_prepareLastRecurringProfiles();
        return parent::_beforeToHtml();
    }

    /**
     * Get last order ID from session, fetch it and check whether it can be viewed, printed etc
     */
    protected function _prepareLastOrder()
    {
        $orderId = Mage::getSingleton('checkout/session')->getLastOrderId();
        if ($orderId) {
            $order = Mage::getModel('sales/order')->load($orderId);
            if ($order->getId()) {
                $isVisible = !in_array(
                    $order->getState(),
                    Mage::getSingleton('sales/order_config')->getInvisibleOnFrontStates(),
                );
                $this->addData([
                    'is_order_visible' => $isVisible,
                    'view_order_id' => $this->getUrl('sales/order/view/', ['order_id' => $orderId]),
                    'print_url' => $this->getUrl('sales/order/print', ['order_id' => $orderId]),
                    'can_print_order' => $isVisible,
                    'can_view_order'  => Mage::getSingleton('customer/session')->isLoggedIn() && $isVisible,
                    'order_id'  => $order->getIncrementId(),
                    'order' => $order,
                ]);
            }
        }
    }

    /**
     * Prepare billing agreement data from an identifier in the session
     */
    protected function _prepareLastBillingAgreement()
    {
        $agreementId = Mage::getSingleton('checkout/session')->getLastBillingAgreementId();
        $customerId = Mage::getSingleton('customer/session')->getCustomerId();
        if ($agreementId && $customerId) {
            $agreement = Mage::getModel('sales/billing_agreement')->load($agreementId);
            if ($agreement->getId() && $customerId == $agreement->getCustomerId()) {
                $this->addData([
                    'agreement_ref_id' => $agreement->getReferenceId(),
                    'agreement_url' => $this->getUrl(
                        'sales/billing_agreement/view',
                        ['agreement' => $agreementId],
                    ),
                    'agreement' => $agreement,
                ]);
            }
        }
    }

    /**
     * Prepare recurring payment profiles from the session
     */
    protected function _prepareLastRecurringProfiles()
    {
        $profileIds = Mage::getSingleton('checkout/session')->getLastRecurringProfileIds();
        if ($profileIds && is_array($profileIds)) {
            $collection = Mage::getModel('sales/recurring_profile')->getCollection()
                ->addFieldToFilter('profile_id', ['in' => $profileIds])
            ;
            $profiles = [];
            foreach ($collection as $profile) {
                $profiles[] = $profile;
            }
            if ($profiles) {
                $this->setRecurringProfiles($profiles);
                if (Mage::getSingleton('customer/session')->isLoggedIn()) {
                    $this->setCanViewProfiles(true);
                }
            }
        }
    }

    public function setCanViewProfiles(?bool $value = true): static
    {
        return $this->setData('can_view_profiles', $value);
    }

    public function setRecurringProfiles(?array $value): static
    {
        return $this->setData('recurring_profiles', $value);
    }

    public function getOrderId(): ?string
    {
        $value = $this->getData('order_id');
        return $value === null ? null : (string) $value;
    }

    public function getPrintUrl(): ?string
    {
        $value = $this->getData('print_url');
        return $value === null ? null : (string) $value;
    }

    public function getCanPrintOrder(): ?bool
    {
        $value = $this->getData('can_print_order');
        return $value === null ? null : (bool) $value;
    }

    public function getCanViewOrder(): ?bool
    {
        $value = $this->getData('can_view_order');
        return $value === null ? null : (bool) $value;
    }

    public function getOrder(): ?Mage_Sales_Model_Order
    {
        return $this->getData('order');
    }
}
