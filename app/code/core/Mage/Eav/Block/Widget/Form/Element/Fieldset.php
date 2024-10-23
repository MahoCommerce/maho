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
 * Block to render fieldset
 *
 * @method $this setLabel(string $label)
 */
class Mage_Eav_Block_Widget_Form_Element_Fieldset extends Mage_Core_Block_Template
{
    protected string $type  = Mage_Eav_Block_Widget_Form::FIELDSET_TYPE_LI;

    #[\Override]
    public function _construct()
    {
        parent::_construct();
        $this->setTemplate('eav/widget/form/element/fieldset.phtml');
    }

    public function getLabel(): string
    {
        return $this->__($this->getData('label'));
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getWrapperTags(): array
    {
        switch ($this->type) {
            case Mage_Eav_Block_Widget_Form::FIELDSET_TYPE_DIV:
                return [
                    ['<div class="form-list">', '</div>'],
                    ['<div>', '</div>'],
                ];
            case Mage_Eav_Block_Widget_Form::FIELDSET_TYPE_LI_WIDE:
                return [
                    ['<ul class="form-list">', '</ul>'],
                    ['<li class="wide">', '</li>'],
                ];
            case Mage_Eav_Block_Widget_Form::FIELDSET_TYPE_LI:
            default:
                return [
                    ['<ul class="form-list">', '</ul>'],
                    ['<li>', '</li>'],
                ];
        }
    }
}
