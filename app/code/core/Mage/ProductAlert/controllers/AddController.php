<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ProductAlert
 */

class Mage_ProductAlert_AddController extends Mage_Core_Controller_Front_Action
{
    /**
     * @return $this
     */
    #[\Override]
    public function preDispatch()
    {
        parent::preDispatch();

        if (!Mage::getSingleton('customer/session')->authenticate($this)) {
            $this->setFlag('', 'no-dispatch', true);
            if (!Mage::getSingleton('customer/session')->getBeforeUrl()) {
                Mage::getSingleton('customer/session')->setBeforeUrl($this->_getRefererUrl());
            }
        }
        return $this;
    }

    #[Maho\Config\Route('/productalert/add/price', name: 'productalert.add.price', methods: ['POST'])]
    public function priceAction(): void
    {
        $this->subscribe(Mage_ProductAlert_Model_Type::Price, $this->__('The alert subscription has been saved.'));
    }

    #[Maho\Config\Route('/productalert/add/stock', name: 'productalert.add.stock', methods: ['POST'])]
    public function stockAction(): void
    {
        $this->subscribe(Mage_ProductAlert_Model_Type::Stock, $this->__('Alert subscription has been saved.'));
    }

    private function subscribe(Mage_ProductAlert_Model_Type $type, string $success): void
    {
        $session = Mage::getSingleton('catalog/session');
        $backUrl    = $this->getRequest()->getParam(Mage_Core_Controller_Front_Action::PARAM_NAME_URL_ENCODED);
        $productId  = (int) $this->getRequest()->getParam('product_id');
        if (!$backUrl || !$productId) {
            $this->_redirect('/');
            return;
        }

        try {
            Mage::getService('productalert/alert')->subscribe(
                $type,
                (int) Mage::getSingleton('customer/session')->getCustomerId(),
                $productId,
                (int) Mage::app()->getStore()->getId(),
            );
            $session->addSuccess($success);
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        } catch (Exception $e) {
            $session->addException($e, $this->__('Unable to update the alert subscription.'));
        }
        $this->_redirectReferer();
    }
}
