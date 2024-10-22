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
 * Class Mage_Eav_Block_Widget_Form_Element_Abstract
 *
 * @method Mage_Core_Model_Abstract getAttribute()
 * @method $this setAttribute(Mage_Eav_Model_Entity_Attribute $value)
 * @method Mage_Core_Model_Abstract getObject()
 * @method $this setObject(Mage_Core_Model_Abstract $value)
 * @method $this setFieldIdFormat(?string $format)
 * @method $this setFieldNameFormat(?string $format)
 */
class Mage_Eav_Block_Widget_Form_Element_Abstract extends Mage_Core_Block_Template
{
    protected ?string $fieldId;
    protected ?string $fieldName;

    public function isEnabled(): bool
    {
        return (bool)$this->getAttribute()->getIsVisible();
    }

    public function isRequired(): bool
    {
        return (bool)$this->getAttribute()->getIsRequired();
    }

    public function getValue(): mixed
    {
        return $this->getObject()->getData($this->getAttribute()->getAttributeCode());
    }

    public function getClass(): string
    {
        return $this->getAttribute()->getFrontend()->getClass();
    }

    public function getLabel(): string
    {
        return $this->__($this->getAttribute()->getStoreLabel());
    }

    public function getLabelClass(): string
    {
        return $this->isRequired() ? 'required' : '';
    }

    public function getFieldIdFormat(): string
    {
        if (empty($this->getData('field_id_format'))) {
            $this->setData('field_id_format', '%s');
        }
        return $this->getData('field_id_format');
    }

    public function setFieldId(string $value): self
    {
        $this->fieldId = $value;
        return $this;
    }

    public function getFieldId(?string $attributeCode = null): string
    {
        $fieldId = $attributeCode ?? $this->fieldId ?? $this->getAttribute()->getAttributeCode();
        return sprintf($this->getFieldIdFormat(), $fieldId);
    }

    public function getFieldNameFormat(): string
    {
        if (empty($this->getData('field_name_format'))) {
            $this->setData('field_name_format', '%s');
        }
        return $this->getData('field_name_format');
    }

    public function setFieldName(string $value): self
    {
        $this->fieldName = $value;
        return $this;
    }

    public function getFieldName(?string $attributeCode = null): string
    {
        $fieldName = $attributeCode ?? $this->fieldName ?? $this->getAttribute()->getAttributeCode();
        return sprintf($this->getFieldNameFormat(), $fieldName);
    }
}
