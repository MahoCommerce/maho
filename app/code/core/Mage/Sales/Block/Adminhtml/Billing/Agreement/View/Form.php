<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Mage_Sales_Block_Adminhtml_Billing_Agreement_View_Form extends Mage_Adminhtml_Block_Template
{
    /**
     * Define custom template
     */
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('sales/billing/agreement/view/form.phtml');
    }
}
