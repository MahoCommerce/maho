<?php

/**
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Newsletter
 */

class Mage_Newsletter_Block_Subscribe extends Mage_Core_Block_Template
{
    /**
     * @return string
     */
    public function getSuccessMessage()
    {
        return Mage::getSingleton('newsletter/session')->getSuccess();
    }

    /**
     * @return string
     */
    public function getErrorMessage()
    {
        return Mage::getSingleton('newsletter/session')->getError();
    }

    /**
     * Retrieve form action url and set "secure" param to avoid confirm
     * message when we submit form from secure page to unsecure
     *
     * @return string
     */
    public function getFormActionUrl()
    {
        return $this->getUrl('newsletter/subscriber/new', ['_secure' => true]);
    }
}
