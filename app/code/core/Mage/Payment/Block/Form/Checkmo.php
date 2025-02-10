<?php

/**
 * Maho
 *
 * @package    Mage_Payment
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @package    Mage_Payment
 */
class Mage_Payment_Block_Form_Checkmo extends Mage_Payment_Block_Form
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('payment/form/checkmo.phtml');
    }
}
