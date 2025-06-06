<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Core_Model_Message_Success extends Mage_Core_Model_Message_Abstract
{
    /**
     * @param string $code
     */
    public function __construct($code)
    {
        parent::__construct(Mage_Core_Model_Message::SUCCESS, $code);
    }
}
