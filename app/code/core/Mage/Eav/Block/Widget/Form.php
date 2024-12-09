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
 * Frontend form widget
 *
 * @method ?string getFieldIdFormat()
 * @method ?string getFieldNameFormat()
 */
class Mage_Eav_Block_Widget_Form extends Mage_Core_Block_Template
{
    public const GROUP_MODE_ATTRIBUTE_SET = 'attribute_set';
    public const GROUP_MODE_FLAT          = 'flat';

    public const FIELDSET_TYPE_DIV        = 'div';
    public const FIELDSET_TYPE_LI         = 'li';
    public const FIELDSET_TYPE_LI_WIDE    = 'li.wide';

    /**
     * Form Objects
     */
    protected Mage_Eav_Model_Form $form;
    protected array $mergedForms   = [];
    protected array $excludedForms = [];

    protected ?array $attributes = null;
    protected array $attributeObjects = [];

    protected ?string $defaultLabel = null;
    protected string $groupMode     = self::GROUP_MODE_ATTRIBUTE_SET;
    protected string $fieldsetType  = self::FIELDSET_TYPE_LI;

    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('eav/widget/form.phtml');
    }

    public function setDefaultLabel(string $label): self
    {
        $this->defaultLabel = $label;
        return $this;
    }

    public function setGroupMode(string $mode): self
    {
        $this->groupMode = $mode;
        return $this;
    }

    public function setFieldsetType(string $type): self
    {
        $this->fieldsetType = $type;
        return $this;
    }

    public function setForm(Mage_Eav_Model_Form $form): self
    {
        $this->form = $form;
        return $this;
    }

    public function mergeFormAttributes(Mage_Eav_Model_Form $form): self
    {
        $this->mergedForms[] = $form;
        $this->attributes = null;
        return $this;
    }

    public function excludeFormAttributes(Mage_Eav_Model_Form $form): self
    {
        $this->excludedForms[] = $form;
        $this->attributes = null;
        return $this;
    }

    public function getAttributes(): array
    {
        if ($this->attributes !== null) {
            return $this->attributes;
        }

        $this->attributes = $this->form->getAttributes();

        foreach ($this->mergedForms as $form) {
            foreach ($form->getAttributes() as $code => $attribute) {
                if (!isset($this->attributes[$code])) {
                    $this->attributes[$code] = $attribute;
                    $this->attributeObjects[$code] = $form->getEntity();
                }
            }
        }

        foreach ($this->excludedForms as $form) {
            foreach (array_keys($form->getAttributes()) as $code) {
                unset($this->attributes[$code]);
            }
        }

        return $this->attributes;
    }

    public function getGroupedAttributes(): array
    {
        $groups = [];
        foreach ($this->getAttributes() as $code => $attribute) {
            $group = $attribute->getAttributeGroupName() ?? 'General';
            $groups[$group] ??= [];
            $groups[$group][$code] = $attribute;
        }
        return $groups;
    }

    public function hasAttribute(string $code): bool
    {
        $attributes = $this->getAttributes();
        return isset($attributes[$code]);
    }

    protected function getFieldsetRenderer(): Mage_Core_Block_Template|false
    {
        return $this->getLayout()->createBlock('eav/widget_form_element_fieldset');
    }

    protected function getFieldsetElementRenderer(Mage_Eav_Model_Attribute $attribute): Mage_Eav_Block_Widget_Form_Element_Abstract|false
    {
        if ($attribute->getAttributeCode() === 'country_id') {
            return $this->getLayout()->createBlock('eav/widget_form_element_country');
        }
        if ($attribute->getAttributeCode() === 'region' && $this->hasAttribute('region_id')) {
            return $this->getLayout()->createBlock('eav/widget_form_element_region');
        }
        if ($attribute->getAttributeCode() === 'region_id' && $this->hasAttribute('region')) {
            return false;
        }
        if ($attribute->getAttributeCode() === 'postcode') {
            return $this->getLayout()->createBlock('eav/widget_form_element_postcode');
        }
        return $this->getLayout()->createBlock('eav/widget_form_element_' . $attribute->getFrontendInput());
    }

    protected function showRequired(): bool
    {
        foreach ($this->getAttributes() as $attribute) {
            if ($attribute->getIsRequired()) {
                return true;
            }
        }
        return false;
    }

    #[\Override]
    protected function _beforeToHtml()
    {
        $groups = $this->getGroupedAttributes();

        if ($this->groupMode === self::GROUP_MODE_FLAT) {
            $groups = [
                array_key_first($groups) => array_merge(...array_values($groups))
            ];
        }

        foreach ($groups as $label => $attributes) {
            $fieldset = $this->getFieldsetRenderer();
            $fieldset->setType($this->fieldsetType);
            $fieldset->setLabel($label);
            $fieldset->setTranslationHelper($this->getTranslationHelper());

            if ($label === array_key_first($groups)) {
                $fieldset->setLabel($this->defaultLabel ?? $label);
                $fieldset->setIsRequired($this->showRequired());
            }
            $this->append($fieldset);

            foreach ($attributes as $code => $attribute) {
                if ($element = $this->getFieldsetElementRenderer($attribute)) {
                    $element->setData('attribute', $attribute);
                    $element->setObject($this->attributeObjects[$code] ?? $this->form->getEntity());
                    $element->setTranslationHelper($this->getTranslationHelper());
                    $element->setFieldIdFormat($this->getFieldIdFormat());
                    $element->setFieldNameFormat($this->getFieldNameFormat());

                    if ($element->isEnabled()) {
                        $fieldset->append($element);
                    }
                }
            }
        }
        return parent::_beforeToHtml();
    }
}
