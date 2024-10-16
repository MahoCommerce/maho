<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Block to render select with custom option attribute
 *
 * @category   Mage
 * @package    Mage_Eav
 */
class Mage_Eav_Block_Widget_Customselect extends Mage_Eav_Block_Widget_Abstract
{
    #[\Override]
    public function _construct()
    {
        parent::_construct();
        $this->setTemplate('eav/widget/customselect.phtml');
    }

    public function getOptions(): array
    {
        return $this->getAttribute()->getSource()->getAllOptions();
    }

    public function getDatalistIdFormat(): string
    {
        if (!$this->hasData('datalist_id_format')) {
            $this->setData('datalist_id_format', '%s__datalist');
        }
        return $this->getData('datalist_id_format');
    }

    public function getDatalistId(string $field): string
    {
        return sprintf($this->getDatalistIdFormat(), $field);
    }
}
