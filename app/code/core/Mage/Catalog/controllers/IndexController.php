<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @package    Mage_Catalog
 */
class Mage_Catalog_IndexController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
        $this->_redirect('/');
    }
}
