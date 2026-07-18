<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

interface Mage_Customer_Block_Address_Renderer_Interface
{
    /**
     * Set format type object
     */
    public function setType(\Maho\DataObject $type);

    /**
     * Retrieve format type object
     *
     * @return \Maho\DataObject
     */
    public function getType();

    /**
     * Render address
     *
     * @return mixed
     */
    public function render(Mage_Customer_Model_Address_Abstract $address);
}
