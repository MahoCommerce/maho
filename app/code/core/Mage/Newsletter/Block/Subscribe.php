<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Newsletter
 */

class Mage_Newsletter_Block_Subscribe extends Mage_Core_Block_Template
{
    /**
     * This method returns plain text. The template must escape it.
     *
     * @return string
     */
    #[\Deprecated(message: 'render the messages with a core/messages block instead', since: '26.9')]
    public function getSuccessMessage()
    {
        return Mage::getSingleton('newsletter/session')->getSuccess();
    }

    /**
     * This method returns plain text. The template must escape it.
     *
     * @return string
     */
    #[\Deprecated(message: 'render the messages with a core/messages block instead', since: '26.9')]
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
