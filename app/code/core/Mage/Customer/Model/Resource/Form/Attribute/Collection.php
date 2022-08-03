<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Customer
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Customer Form Attribute Resource Collection
 *
 * @category   Mage
 * @package    Mage_Customer
 */
class Mage_Customer_Model_Resource_Form_Attribute_Collection extends Mage_Eav_Model_Resource_Form_Attribute_Collection
{
    /**
     * Current module pathname
     *
     * @var string
     */
    protected $_moduleName = 'customer';

    /**
     * Current EAV entity type code
     *
     * @var string
     */
    protected $_entityTypeCode = 'customer';

    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->_init('eav/attribute', 'customer/form_attribute');
    }

    /**
     * Get EAV website table
     *
     * Get table, where website-dependent attribute parameters are stored.
     * If realization doesn't demand this functionality, let this function just return null
     *
     * @return string|null
     */
    #[\Override]
    protected function _getEavWebsiteTable()
    {
        return $this->getTable('customer/eav_attribute_website');
    }

    public function filterAttributeSet($attributeSetId)
    {
        $this->getSelect()
             ->joinInner(
                 array('eea' => $this->getTable('eav/entity_attribute')),
                 'main_table.attribute_id = eea.attribute_id',
                 array()
             )
             ->joinLeft(
                 array('eag' => $this->getTable('eav/attribute_group')),
                 'eea.attribute_group_id = eag.attribute_group_id',
                 array('eag.attribute_group_name')
             )
             ->where('eea.attribute_set_id = ?', $attributeSetId)
             ->order(array('eag.sort_order', 'eea.sort_order'));

        return $this;
    }
}
