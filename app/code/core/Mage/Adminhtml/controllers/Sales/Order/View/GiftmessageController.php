<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Sales_Order_View_GiftmessageController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'sales/order';

    /**
     * Additional initialization
     */
    #[\Override]
    protected function _construct()
    {
        $this->setUsedModuleName('Mage_Sales');
    }

    #[Maho\Config\Route('/admin/sales_order_view_giftmessage/save')]
    public function saveAction(): void
    {
        try {
            $this->_getGiftmessageSaveModel()
                ->setGiftmessages($this->getRequest()->getParam('giftmessage'))
                ->saveAllInOrder();
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        } catch (Exception $e) {
            $this->_getSession()->addError(Mage::helper('giftmessage')->__('An error occurred while saving the gift message.'));
        }

        if ($this->getRequest()->getParam('type') == 'order_item') {
            $this->getResponse()->setBody(
                $this->_getGiftmessageSaveModel()->getSaved() ? 'YES' : 'NO',
            );
        } else {
            $this->getResponse()->setBody(
                Mage::helper('giftmessage')->__('The gift message has been saved.'),
            );
        }
    }

    /**
     * Retrieve gift message save model
     *
     * @return Mage_Adminhtml_Model_Giftmessage_Save
     */
    protected function _getGiftmessageSaveModel()
    {
        return Mage::getSingleton('adminhtml/giftmessage_save');
    }
}
