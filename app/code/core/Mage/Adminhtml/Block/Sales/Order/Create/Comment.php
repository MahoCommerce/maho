<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Sales_Order_Create_Comment extends Mage_Adminhtml_Block_Sales_Order_Create_Abstract
{
    protected $_form;

    /**
     * @return string
     */
    public function getHeaderCssClass()
    {
        return 'head-comment';
    }

    /**
     * @return string
     */
    public function getHeaderText()
    {
        return Mage::helper('sales')->__('Order Comment');
    }

    /**
     * @return string
     */
    public function getCommentNote()
    {
        return $this->escapeHtml($this->getQuote()->getCustomerNote());
    }

    /**
     * @return bool
     */
    public function getNoteNotify()
    {
        $notify = $this->getQuote()->getCustomerNoteNotify();
        if (is_null($notify) || $notify) {
            return true;
        }
        return false;
    }
}
