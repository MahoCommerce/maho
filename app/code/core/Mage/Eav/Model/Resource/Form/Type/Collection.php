<?php

/**
 * Maho
 *
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Eav_Model_Resource_Form_Type_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Initialize collection model
     *
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('eav/form_type');
    }

    /**
     * Convert items array to array for select options
     *
     * @return array
     */
    #[\Override]
    public function toOptionArray()
    {
        return $this->_toOptionArray('type_id', 'label');
    }

    /**
     * Add Entity type filter to collection
     *
     * @param Mage_Eav_Model_Entity_Type|int $entity
     * @return $this
     */
    public function addEntityTypeFilter($entity)
    {
        if ($entity instanceof Mage_Eav_Model_Entity_Type) {
            $entity = $entity->getId();
        }

        $this->getSelect()
            ->join(
                ['form_type_entity' => $this->getTable('eav/form_type_entity')],
                'main_table.type_id = form_type_entity.type_id',
                [],
            )
            ->where('form_type_entity.entity_type_id = ?', $entity);

        return $this;
    }
}
