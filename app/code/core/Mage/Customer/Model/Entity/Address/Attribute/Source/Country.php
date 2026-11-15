<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2018-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

class Mage_Customer_Model_Entity_Address_Attribute_Source_Country extends Mage_Customer_Model_Resource_Address_Attribute_Source_Country
{
    /**
     * Factory instance
     *
     * @var Mage_Core_Model_Abstract
     */
    protected $_factory;

    public function __construct(array $args = [])
    {
        $this->_factory = empty($args['factory']) ? Mage::getSingleton('core/factory') : $args['factory'];
    }
    #[\Override]
    public function getAllOptions(bool $withEmpty = true, bool $defaultValues = false): array
    {
        if (!$this->_options) {
            $this->_options = $this->_factory->getResourceModel('directory/country_collection')
                ->loadByStore($this->getAttribute()->getStoreId())->toOptionArray();
        }
        return $this->_options;
    }
}
