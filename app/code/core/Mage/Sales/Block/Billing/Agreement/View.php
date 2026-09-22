<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

class Mage_Sales_Block_Billing_Agreement_View extends Mage_Core_Block_Template
{
    /**
     * Payment methods array
     *
     * @var array
     */
    protected $_paymentMethods = [];

    /**
     * Billing Agreement instance
     *
     * @var Mage_Sales_Model_Billing_Agreement|null
     */
    protected $_billingAgreementInstance = null;

    /**
     * Related orders collection
     *
     * @var Mage_Sales_Model_Resource_Order_Collection|null
     */
    protected $_relatedOrders = null;

    /**
     * Retrieve related orders collection
     *
     * @return Mage_Sales_Model_Resource_Order_Collection
     */
    public function getRelatedOrders()
    {
        $this->_relatedOrders ??= Mage::getResourceModel('sales/order_collection')
            ->addFieldToSelect('*')
            ->addFieldToFilter('customer_id', Mage::getSingleton('customer/session')->getCustomer()->getId())
            ->addFieldToFilter(
                'state',
                ['in' => Mage::getSingleton('sales/order_config')->getVisibleOnFrontStates()],
            )
            ->addBillingAgreementsFilter($this->_billingAgreementInstance->getAgreementId())
            ->setOrder('created_at', 'desc');
        return $this->_relatedOrders;
    }

    /**
     * Retrieve order item value by key
     *
     * @param string $key
     * @return string
     */
    public function getOrderItemValue(Mage_Sales_Model_Order $order, $key)
    {
        $escape = true;
        switch ($key) {
            case 'order_increment_id':
                $value = $order->getIncrementId();
                break;
            case 'created_at':
                $value = $this->formatDate($order->getCreatedAt(), 'short', true);
                break;
            case 'shipping_address':
                $value = $order->getShippingAddress()
                    ? $this->escapeHtml($order->getShippingAddress()->getName()) : $this->__('N/A');
                break;
            case 'order_total':
                $value = $order->formatPrice($order->getGrandTotal());
                $escape = false;
                break;
            case 'status_label':
                $value = $order->getStatusLabel();
                break;
            case 'view_url':
                $value = $this->getUrl('*/order/view', ['order_id' => $order->getId()]);
                break;
            default:
                $value = ($order->getData($key)) ?: $this->__('N/A');
        }
        return ($escape) ? $this->escapeHtml($value) : $value;
    }

    /**
     * Set pager
     *
     * @return Mage_Core_Block_Abstract
     */
    #[\Override]
    protected function _prepareLayout()
    {
        $this->_billingAgreementInstance ??= Mage::registry('current_billing_agreement');
        parent::_prepareLayout();

        $pager = $this->getLayout()->createBlock('page/html_pager')
            ->setCollection($this->getRelatedOrders())->setIsOutputRequired(false);
        $this->setChild('pager', $pager);
        $this->getRelatedOrders()->load();

        return $this;
    }

    /**
     * Load available billing agreement methods
     *
     * @return array
     */
    protected function _loadPaymentMethods()
    {
        if (!$this->_paymentMethods) {
            $helper = $this->helper('payment');
            foreach ($helper->getBillingAgreementMethods() as $paymentMethod) {
                $this->_paymentMethods[$paymentMethod->getCode()] = $paymentMethod->getTitle();
            }
        }
        return $this->_paymentMethods;
    }

    /**
     * Set data to block
     *
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        $this->_loadPaymentMethods();
        $this->setBackUrl($this->getUrl('*/billing_agreement/'));
        if ($this->_billingAgreementInstance) {
            $this->setReferenceId($this->_billingAgreementInstance->getReferenceId());
            $this->setCanCancel($this->_billingAgreementInstance->canCancel());
            $this->setCancelUrl(
                $this->getUrl('*/billing_agreement/cancel', [
                    '_current' => true,
                    'payment_method' => $this->_billingAgreementInstance->getMethodCode()]),
            );

            $paymentMethodTitle = $this->_billingAgreementInstance->getAgreementLabel();
            $this->setPaymentMethodTitle($paymentMethodTitle);

            $createdAt = $this->_billingAgreementInstance->getCreatedAt();
            $updatedAt = $this->_billingAgreementInstance->getUpdatedAt();
            $this->setAgreementCreatedAt(
                ($createdAt) ? $this->formatDate($createdAt, 'short', true) : $this->__('N/A'),
            );
            if ($updatedAt) {
                $this->setAgreementUpdatedAt($this->formatDate($updatedAt, 'short', true));
            }
            $this->setAgreementStatus($this->_billingAgreementInstance->getStatusLabel());
        }

        return parent::_toHtml();
    }

    public function setAgreementCreatedAt(?string $value): static
    {
        return $this->setData('agreement_created_at', $value);
    }

    public function setAgreementUpdatedAt(?string $value): static
    {
        return $this->setData('agreement_updated_at', $value);
    }

    public function setAgreementStatus(?string $value): static
    {
        return $this->setData('agreement_status', $value);
    }

    public function setBackUrl(?string $value): static
    {
        return $this->setData('back_url', $value);
    }

    public function setCanCancel(?bool $value = true): static
    {
        return $this->setData('can_cancel', $value);
    }

    public function setCancelUrl(?string $value): static
    {
        return $this->setData('cancel_url', $value);
    }

    public function setPaymentMethodTitle(?string $value): static
    {
        return $this->setData('payment_method_title', $value);
    }

    public function setReferenceId(?string $value): static
    {
        return $this->setData('reference_id', $value);
    }
}
