<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Page
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
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
