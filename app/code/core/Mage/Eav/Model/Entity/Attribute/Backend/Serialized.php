<?php

/**
 * Maho
 *
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Eav_Model_Entity_Attribute_Backend_Serialized extends Mage_Eav_Model_Entity_Attribute_Backend_Abstract
{
    /**
     * Serialize before saving
     *
     * @param \Maho\DataObject $object
     * @return $this
     */
    #[\Override]
    public function beforeSave($object)
    {
        // parent::beforeSave() is not called intentionally
        $attrCode = $this->getAttribute()->getAttributeCode();
        if ($object->hasData($attrCode)) {
            $object->setData($attrCode, Mage::helper('core')->jsonEncode($object->getData($attrCode)));
        }

        return $this;
    }

    /**
     * Unserialize after saving
     *
     * @param \Maho\DataObject $object
     * @return $this
     */
    #[\Override]
    public function afterSave($object)
    {
        parent::afterSave($object);
        $this->_unserialize($object);
        return $this;
    }

    /**
     * Unserialize after loading
     *
     * @param \Maho\DataObject $object
     * @return $this
     */
    #[\Override]
    public function afterLoad($object)
    {
        parent::afterLoad($object);
        $this->_unserialize($object);
        return $this;
    }

    /**
     * Try to unserialize the attribute value
     *
     * @return $this
     */
    protected function _unserialize(\Maho\DataObject $object)
    {
        $attrCode = $this->getAttribute()->getAttributeCode();
        if ($object->getData($attrCode)) {
            try {
                $unserialized = Mage::helper('core/string')
                    ->unserialize($object->getData($attrCode));
                $object->setData($attrCode, $unserialized);
            } catch (Exception $e) {
                $object->unsetData($attrCode);
            }
        }

        return $this;
    }
}
