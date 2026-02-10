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
class Mage_Sales_Model_Resource_Quote_Item extends Mage_Sales_Model_Resource_Abstract
{
    /**
     * Main table and field initialization
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('sales/quote_item', 'item_id');
    }
}
