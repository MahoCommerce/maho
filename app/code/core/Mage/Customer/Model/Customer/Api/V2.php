<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

class Mage_Customer_Model_Customer_Api_V2 extends Mage_Customer_Model_Customer_Api
{
    /**
     * Prepare data to insert/update.
     * Creating array for stdClass Object
     *
     * @param stdClass $data
     * @return array
     */
    #[\Override]
    protected function _prepareData($data)
    {
        return parent::_prepareData(get_object_vars($data));
    }
}
