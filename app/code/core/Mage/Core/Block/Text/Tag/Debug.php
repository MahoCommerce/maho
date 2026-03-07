<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Core_Block_Text_Tag_Debug extends Mage_Core_Block_Text_Tag
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setAttribute([
            'tagName' => 'xmp',
        ]);
    }

    /**
     * @param mixed $value
     * @return $this
     */
    public function setValue($value)
    {
        return $this->setContents(print_r($value, true));
    }
}
