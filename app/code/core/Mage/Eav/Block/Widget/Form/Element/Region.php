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
 * Block to render region / region_id attribute
 */
class Mage_Eav_Block_Widget_Form_Element_Region extends Mage_Eav_Block_Widget_Form_Element_Abstract
{
    #[\Override]
    public function _construct()
    {
        parent::_construct();
        $this->setTemplate('eav/widget/form/element/region.phtml');
    }

    public function getRegionId(): int
    {
        return $this->getObject()->getRegionId();
    }
}
