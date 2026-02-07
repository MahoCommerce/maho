<?php

/**
 * Maho
 *
 * @package    Mage_Install
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Mage_Install_Block_Complete extends Mage_Install_Block_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('page/complete.phtml');
    }

    public function getLanguagePackCommand(): ?string
    {
        return Mage::getSingleton('install/session')->getLanguagePackCommand() ?: null;
    }
}
