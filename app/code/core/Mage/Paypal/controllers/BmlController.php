<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Paypal
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Mage_Paypal_BmlController extends Mage_Core_Controller_Front_Action
{
    /**
     * Action for Bill Me Later checkout button (product view and shopping cart pages)
     */
    public function startAction(): void
    {
        $this->_forward('start', 'express', 'paypal', [
            'bml' => 1,
            'button' => $this->getRequest()->getParam('button'),
        ]);
    }
}
