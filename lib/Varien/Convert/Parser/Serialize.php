<?php
/**
 * Maho
 *
 * @category   Varien
 * @package    Varien_Convert
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Convert php serialize parser
 *
 * @category   Varien
 * @package    Varien_Convert
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
