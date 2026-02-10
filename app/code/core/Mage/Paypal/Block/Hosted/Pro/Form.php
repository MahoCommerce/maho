<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Paypal
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Mage_Paypal_Block_Hosted_Pro_Form extends Mage_Payment_Block_Form
{
    /**
     * Set info template for payment step
     */
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('paypal/hss/info.phtml');
    }
}
