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
 * Block to render postcode/zip attribute
 */
class Mage_Eav_Block_Widget_Form_Element_Postcode extends Mage_Eav_Block_Widget_Form_Element_Abstract
{
    #[\Override]
    public function _construct()
    {
        parent::_construct();
        $this->setFieldId('zip');
        $this->setTemplate('eav/widget/form/element/postcode.phtml');
    }
}
