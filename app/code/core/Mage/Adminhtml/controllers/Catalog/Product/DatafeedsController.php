<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2021-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @category   Mage
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Catalog_DatafeedsController extends Mage_Adminhtml_Controller_Action
{
    public function indexAction()
    {
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
