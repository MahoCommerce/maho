<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Payment
 */

declare(strict_types=1);

class Mage_Payment_Block_Form_Banktransfer extends Mage_Payment_Block_Form
{
    /**
     * Instructions text
     *
     * @var string|null
     */
    protected $_instructions;

    /**
     * Block construction. Set block template.
     */
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('payment/form/banktransfer.phtml');
    }

    /**
     * Get instructions text from config
     *
     * @return string
     */
    public function getInstructions()
    {
        if (is_null($this->_instructions)) {
            $this->_instructions = $this->getMethod()->getInstructions();
        }
        return $this->_instructions;
    }
}
