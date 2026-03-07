<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Checkout
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Checkout_IndexController extends Mage_Core_Controller_Front_Action
{
    public function indexAction(): void
    {
        $this->_redirect('checkout/onepage', ['_secure' => true]);
    }
}
