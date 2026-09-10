<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Newsletter
 */

class Mage_Newsletter_Model_Session extends Mage_Core_Model_Session_Abstract
{
    public function __construct()
    {
        $this->init('newsletter');
    }

    #[\Override]
    public function addError(string $text, string|\Maho\Message\Link|null ...$args): self
    {
        $this->setErrorMessage(Mage::getSingleton('core/message')->error($text)->setTextArgs($args)->getText());
        return $this;
    }

    #[\Override]
    public function addSuccess(string $text, string|\Maho\Message\Link|null ...$args): self
    {
        $this->setSuccessMessage(Mage::getSingleton('core/message')->success($text)->setTextArgs($args)->getText());
        return $this;
    }

    public function getError(): string
    {
        $message = $this->getErrorMessage();
        $this->unsErrorMessage();
        return $message;
    }

    public function getSuccess(): string
    {
        $message = $this->getSuccessMessage();
        $this->unsSuccessMessage();
        return $message;
    }
}
