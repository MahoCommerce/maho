<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

class Mage_Catalog_Model_Product_Configuration_Item_Option extends \Maho\DataObject implements Mage_Catalog_Model_Product_Configuration_Item_Option_Interface
{
    /**
     * @return mixed
     */
    #[\Override]
    public function getValue()
    {
        return $this->_getData('value');
    }
}
