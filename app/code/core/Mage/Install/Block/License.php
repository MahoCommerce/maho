<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2018-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Install
 */

declare(strict_types=1);

class Mage_Install_Block_License extends Mage_Install_Block_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('page/license.phtml');
    }

    public function getPostUrl(): string
    {
        return Mage::getUrl('install/wizard/licensePost');
    }

    public function getLicenseHtml(): string
    {
        return nl2br(file_get_contents(Maho::findFile((string) Mage::getConfig()->getNode('install/eula_file'))));
    }
}
