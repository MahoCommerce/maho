<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Reports
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Reports_Model_Resource_Product_Index_Viewed extends Mage_Reports_Model_Resource_Product_Index_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('reports/viewed_product_index', 'index_id');
    }
}
