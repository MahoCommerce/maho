<?php

/**
 * Maho
 *
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Entity attribute backend interface
 * Backend is responsible for saving the values of the attribute
 * and performing pre and post actions
 */
interface Mage_Eav_Model_Entity_Attribute_Backend_Interface
{
    public function getTable();
    public function isStatic();
    public function getType();
    public function getEntityIdField();

    /**
     * @param int $valueId
     * @return $this
     */
    public function setValueId($valueId);

    public function getValueId();

    /**
     * @param object $object
     * @return mixed
     */
    public function afterLoad($object);

    /**
     * @param object $object
     * @return mixed
     */
    public function beforeSave($object);

    /**
     * @param object $object
     * @return mixed
     */
    public function afterSave($object);

    /**
     * @param object $object
     * @return mixed
     */
    public function beforeDelete($object);

    /**
     * @param object $object
     * @return mixed
     */
    public function afterDelete($object);

    /**
     * Get entity value id
     *
     * @param Varien_Object $entity
     */
    public function getEntityValueId($entity);

    /**
     * Set entity value id
     *
     * @param Varien_Object $entity
     * @param int $valueId
     */
    public function setEntityValueId($entity, $valueId);
}
