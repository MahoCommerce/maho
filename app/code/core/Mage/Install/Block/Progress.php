<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Install
 */

declare(strict_types=1);

class Mage_Install_Block_Progress extends Mage_Core_Block_Template
{
    public function __construct()
    {
        $this->setTemplate('page/progress.phtml');
        $this->assign('steps', Mage::getSingleton('install/wizard')->getSteps());
    }
}
