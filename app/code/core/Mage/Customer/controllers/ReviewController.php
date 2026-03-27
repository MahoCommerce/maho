<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Customer
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Customer_ReviewController extends Mage_Core_Controller_Front_Action
{
    #[\Override]
    public function preDispatch()
    {
        parent::preDispatch();
        if (!Mage::getStoreConfigFlag('customer/account/enabled_in_frontend')) {
            $this->norouteAction();
            $this->setFlag('', 'no-dispatch', true);
            return $this;
        }
        return $this;
    }

    public function indexAction(): void
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    public function viewAction(): void
    {
        $this->loadLayout();
        $this->renderLayout();
    }
}
