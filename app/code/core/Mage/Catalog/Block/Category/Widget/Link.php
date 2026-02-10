<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Mage_Catalog_Block_Category_Widget_Link extends Mage_Catalog_Block_Widget_Link
{
    /**
     * Initialize entity model
     */
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->_entityResource = Mage::getResourceSingleton('catalog/category');
    }
}
