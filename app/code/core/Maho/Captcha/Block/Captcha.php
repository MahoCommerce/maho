<?php

/**
 * Maho
 *
 * @package    Maho_Captcha
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Captcha_Block_Captcha extends Mage_Core_Block_Template
{
    #[\Override]
    public function _construct()
    {
        parent::_construct();
        $this->setTemplate('maho/captcha/captcha.phtml');
    }

    public function isEnabled()
    {
        return Mage::helper('maho_captcha')->isEnabled();
    }
}
