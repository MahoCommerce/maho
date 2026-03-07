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

class Mage_Paypal_Block_Payflow_Advanced_Form extends Mage_Paypal_Block_Payflow_Link_Form
{
    /**
     * Set payment method code
     */
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('paypal/payflowadvanced/info.phtml');
    }

    /**
     * Get frame action URL
     *
     * @return string
     */
    #[\Override]
    public function getFrameActionUrl()
    {
        return $this->getUrl('paypal/payflowadvanced/form', ['_secure' => true]);
    }
}
