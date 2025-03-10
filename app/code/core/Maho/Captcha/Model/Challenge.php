<?php

/**
 * Maho
 *
 * @package    Maho_Captcha
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Captcha_Model_Challenge extends Mage_Core_Model_Abstract
{
    protected function _construct()
    {
        $this->_init('captcha/challenge');
    }
}
