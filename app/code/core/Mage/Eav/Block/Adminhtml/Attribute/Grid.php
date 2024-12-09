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
 * Adminhtml generic EAV attribute grid
 *
 * @category   Mage
 * @package    Mage_Eav
 */
class Mage_Eav_Block_Adminhtml_Attribute_Grid extends Mage_Eav_Block_Adminhtml_Attribute_Grid_Abstract
{
    #[\Override]
    protected function _prepareCollection()
    {
        // Get global/eav_attributes/$entityType/$attributeCode/hidden config.xml nodes
        $hiddenAttributes = Mage::helper('eav')->getHiddenAttributes($this->entityType->getEntityTypeCode());

        /** @var Mage_Eav_Model_Resource_Entity_Attribute_Collection $collection */
        $collection = Mage::getResourceModel($this->entityType->getEntityAttributeCollection());
        $collection->setEntityTypeFilter($this->entityType->getEntityTypeId())
            ->setNotCodeFilter($hiddenAttributes)
            ->addVisibleFilter();

        $this->setCollection($collection);
        return parent::_prepareCollection();
    }
}
