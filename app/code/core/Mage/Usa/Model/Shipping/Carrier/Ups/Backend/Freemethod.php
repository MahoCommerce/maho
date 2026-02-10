<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Usa
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Mage_Usa_Model_Shipping_Carrier_Ups_Backend_Freemethod extends Mage_Usa_Model_Shipping_Carrier_Abstract_Backend_Abstract
{
    /**
     * Set source model to get allowed values
     */
    #[\Override]
    protected function _setSourceModelData()
    {
        $this->_sourceModel = 'usa/shipping_carrier_ups_source_freemethod';
    }

    /**
     * Set field name to display in error block
     */
    #[\Override]
    protected function _setNameErrorField()
    {
        $this->_nameErrorField = 'Ups Free Method';
    }
}
