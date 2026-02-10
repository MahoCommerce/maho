<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Mage_Sales_Model_Resource_Quote_Address_Attribute_Frontend extends Mage_Eav_Model_Entity_Attribute_Frontend_Abstract
{
    /**
     * Fetch totals
     *
     * @return $this|array
     */
    public function fetchTotals(Mage_Sales_Model_Quote_Address $address)
    {
        return [];
    }
}
