<?php

/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Product tax admin controller
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_TaxController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Set tax ignore notification flag and redirect back
     */
    public function ignoreTaxNotificationAction()
    {
        $section = $this->getRequest()->getParam('section');
        if ($section) {
            Mage::helper('tax')->setIsIgnored('tax/ignore_notification/' . $section, true);
        }
        $this->_redirectReferer();
    }

    /**
     * Check is allowed access to action
     *
     * @return true
     */
    #[\Override]
    protected function _isAllowed()
    {
        return true;
    }
}
