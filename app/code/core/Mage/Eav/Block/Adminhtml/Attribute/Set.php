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
 * Adminhtml attribute sets block
 *
 * @category   Mage
 * @package    Mage_Eav
 */
class Mage_Eav_Block_Adminhtml_Attribute_Set extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'eav';
        $this->_controller = 'adminhtml_attribute_set';
        if ($entity_type = Mage::registry('entity_type')) {
            $this->_headerText = Mage::helper('eav')->__('Manage %s Attribute Sets', Mage::helper('eav')->formatTypeCode($entity_type));
        } else {
            $this->_headerText = Mage::helper('eav')->__('Manage Attribute Sets');
        }
        $this->_addButtonLabel = Mage::helper('eav')->__('Add New Set');
        parent::__construct();
    }

    #[\Override]
    public function getCreateUrl()
    {
        return $this->getUrl('*/*/add');
    }
}
