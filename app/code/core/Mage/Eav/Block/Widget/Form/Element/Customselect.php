<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Block to render select with custom option attribute
 */
class Mage_Eav_Block_Widget_Form_Element_Customselect extends Mage_Eav_Block_Widget_Form_Element_Abstract
{
    #[\Override]
    public function _construct()
    {
        parent::_construct();
        $this->setTemplate('eav/widget/form/element/customselect.phtml');
    }

    public function getOptions(): array
    {
        return $this->getAttribute()->getSource()->getAllOptions(false);
    }

    public function getDatalistIdFormat(): string
    {
        if (!$this->hasData('datalist_id_format')) {
            $this->setData('datalist_id_format', '%s__datalist');
        }
        return $this->getData('datalist_id_format');
    }

    public function getDatalistId(?string $attributeCode = null): string
    {
        $fieldName = $attributeCode ?? $this->fieldName ?? $this->getAttribute()->getAttributeCode();
        return sprintf($this->getDatalistIdFormat(), $fieldName);
    }
}
