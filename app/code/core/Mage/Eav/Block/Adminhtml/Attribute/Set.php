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
 * EAV attribute sets grid container
 */
class Mage_Eav_Block_Adminhtml_Attribute_Set extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    protected Mage_Eav_Model_Entity_Type $entityType;

    public function __construct()
    {
        $this->entityType = Mage::registry('entity_type');

        $this->_blockGroup = 'eav';
        $this->_controller = 'adminhtml_attribute_set';

        $this->_headerText = $this->__(
            'Manage %s Attribute Sets',
            Mage::helper('eav')->formatTypeCode($this->entityType->getEntityTypeCode())
        );

        $this->_addButtonLabel = Mage::helper('eav')->__('Add New Set');
        parent::__construct();
    }

    #[\Override]
    public function getCreateUrl()
    {
        return $this->getUrl('*/*/add');
    }
}
