<?php

/**
 * Maho
 *
 * @package    Mage_Usa
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

abstract class Mage_Usa_Model_Shipping_Carrier_Dhl_International_Source_Method_Abstract
{
    /**
     * Carrier Product Type Indicator
     *
     * @var string $_contentType
     */
    protected $_contentType;

    /**
     * Show 'none' in methods list or not;
     *
     * @var bool
     */
    protected $_noneMethod = false;

    /**
     * Returns array to be used in multiselect on back-end
     */
    public function toOptionArray(): array
    {
        /** @var Mage_Usa_Model_Shipping_Carrier_Dhl_International $carrierModel */
        $carrierModel   = Mage::getSingleton('usa/shipping_carrier_dhl_international');
        $dhlProducts    = $carrierModel->getDhlProducts($this->_contentType);

        $options = [];
        foreach ($dhlProducts as $code => $title) {
            $options[] = ['value' => $code, 'label' => $title];
        }

        if ($this->_noneMethod) {
            array_unshift($options, ['value' => '', 'label' => Mage::helper('usa')->__('None')]);
        }

        return $options;
    }
}
