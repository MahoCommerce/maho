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
 * Block to render boolean attribute
 */
class Mage_Eav_Block_Widget_Form_Element_Boolean extends Mage_Eav_Block_Widget_Form_Element_Abstract
{
    #[\Override]
    public function _construct()
    {
        parent::_construct();
        $this->setTemplate('eav/widget/form/element/boolean.phtml');
    }

    public function getOptions(): array
    {
        $options = [
            ['value' => '',  'label' => $this->__('')],
            ['value' => '1', 'label' => $this->__('Yes')],
            ['value' => '0', 'label' => $this->__('No')]
        ];

        return $options;
    }
}
