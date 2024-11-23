<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2021-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * EAV attribute edit page tabs
 *
 * @category   Mage
 * @package    Mage_Eav
 */
class Mage_Eav_Block_Adminhtml_Attribute_Edit_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{
    protected Mage_Eav_Model_Entity_Type $entityType;
    protected Mage_Eav_Model_Entity_Attribute $entityAttribute;

    public function __construct()
    {
        $this->entityType = Mage::registry('entity_type');
        $this->entityAttribute = Mage::registry('entity_attribute');

        parent::__construct();

        $this->setId('eav_attribute_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle(Mage::helper('eav')->__('Attribute Information'));
    }
}
