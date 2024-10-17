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
 * Class Mage_Eav_Block_Widget_Abstract
 *
 * @method Mage_Core_Model_Abstract getAttribute()
 * @method $this setAttribute(Mage_Eav_Model_Entity_Attribute $value)
 * @method Mage_Core_Model_Abstract getObject()
 * @method $this setObject(Mage_Core_Model_Abstract $value)
 */
class Mage_Eav_Block_Widget_Abstract extends Mage_Core_Block_Template
{
    /**
     * Check if attribute enabled in system
     *
     * @return bool
     */
    public function isEnabled()
    {
        return (bool)$this->getAttribute()->getIsVisible();
    }

    /**
     * Check if attribute marked as required
     *
     * @return bool
     */
    public function isRequired()
    {
        return (bool)$this->getAttribute()->getIsRequired();
    }

    /**
     * @return string
     */
    public function getFieldIdFormat()
    {
        if (!$this->hasData('field_id_format')) {
            $this->setData('field_id_format', '%s');
        }
        return $this->getData('field_id_format');
    }

    /**
     * @return string
     */
    public function getFieldNameFormat()
    {
        if (!$this->hasData('field_name_format')) {
            $this->setData('field_name_format', '%s');
        }
        return $this->getData('field_name_format');
    }

    /**
     * @param string $field
     * @return string
     */
    public function getFieldId($field)
    {
        return sprintf($this->getFieldIdFormat(), $field);
    }

    /**
     * @param string $field
     * @return string
     */
    public function getFieldName($field)
    {
        return sprintf($this->getFieldNameFormat(), $field);
    }
}
