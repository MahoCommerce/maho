<?php

/**
 * Maho
 *
 * @package    Varien_Convert
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Varien_Convert_Parser_Serialize extends Varien_Convert_Parser_Abstract
{
    #[\Override]
    public function parse()
    {
        $this->setData(unserialize($this->getData()));
        return $this;
    }

    #[\Override]
    public function unparse()
    {
        $this->setData(serialize($this->getData()));
        return $this;
    }
}
