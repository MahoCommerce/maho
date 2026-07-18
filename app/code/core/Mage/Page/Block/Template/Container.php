<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Page
 */

declare(strict_types=1);

class Mage_Page_Block_Template_Container extends Mage_Core_Block_Template
{
    /**
     * Set default template
     */
    #[\Override]
    protected function _construct()
    {
        $this->setTemplate('page/template/container.phtml');
    }
}
