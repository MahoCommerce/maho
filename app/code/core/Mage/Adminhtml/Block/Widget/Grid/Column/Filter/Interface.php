<?php

/**
 * SPDX-FileCopyrightText: 2021-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

interface Mage_Adminhtml_Block_Widget_Grid_Column_Filter_Interface
{
    public function getColumn();
    public function setColumn($column);
    public function getHtml();
}
