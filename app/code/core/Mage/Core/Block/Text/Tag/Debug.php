<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Base html block
 *
 * @category   Mage
 * @package    Mage_Core
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
     * @return Mage_Core_Block_Text_Tag_Debug
     */
    public function setValue($value)
    {
        return $this->setContents(print_r($value, 1));
    }
}
