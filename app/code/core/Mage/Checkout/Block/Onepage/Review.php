<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Checkout
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * One page checkout status
 *
 * @category   Mage
 * @package    Mage_Checkout
 */
class Mage_Checkout_Block_Onepage_Review extends Mage_Checkout_Block_Onepage_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->getCheckout()->setStepData('review', [
            'label'     => Mage::helper('checkout')->__('Order Review'),
            'is_show'   => $this->isShow()
        ]);
        parent::_construct();

        $this->getQuote()->collectTotals()->save();
    }
}
