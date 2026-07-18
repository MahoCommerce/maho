<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Ip extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    /**
     * Render the grid cell value
     *
     * @return string
     */
    #[\Override]
    public function render(\Maho\DataObject $row)
    {
        /**
         * The output of the "inet_ntop" function was disabled to prevent an error throwing
         * in case when the database value is not an ipv6 or an ipv4 binary representation (ex. NULL).
         */
        return @inet_ntop($row->getData($this->getColumn()->getIndex()));
    }
}
