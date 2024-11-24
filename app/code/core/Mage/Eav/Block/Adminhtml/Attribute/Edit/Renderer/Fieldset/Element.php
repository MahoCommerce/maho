<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Fieldset element renderer
 *
 * @category   Mage
 * @package    Mage_Eav
 */
class Mage_Eav_Block_Adminhtml_Attribute_Edit_Renderer_Fieldset_Element extends Mage_Adminhtml_Block_Widget_Form_Renderer_Fieldset_Element
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('eav/attribute/edit/renderer/fieldset/element.phtml');
    }

    /**
     * Check "Use default" checkbox display availability
     */
    public function canDisplayUseDefault(): bool
    {
        $attributeObject = $this->getElement()->getForm()->getDataObject();
        if ($attributeObject->getWebsite() && (int)$attributeObject->getWebsite()->getId()) {
            return $this->getElement()->getScope() === Mage_Eav_Model_Entity_Attribute::SCOPE_WEBSITE;
        }
        return false;
    }

    /**
     * Check default value usage fact
     */
    public function usedDefault(): bool
    {
        $field = $this->getElement()->getId();
        if (str_starts_with($field, 'default_value')) {
            $field = 'default_value';
        }
        $attributeObject = $this->getElement()->getForm()->getDataObject();
        return is_null($attributeObject->getData('scope_' . $field));
    }

    /**
     * Disable field in default value using case
     */
    public function checkFieldDisable(): self
    {
        if ($this->canDisplayUseDefault() && $this->usedDefault()) {
            $this->getElement()->setDisabled(true);
        }
        return $this;
    }

    /**
     * Retrieve label of attribute scope
     *
     * GLOBAL | WEBSITE
     */
    public function getScopeLabel(): string
    {
        $html = '';
        if (Mage::app()->isSingleStoreMode()) {
            return $html;
        }
        if ($this->getElement()->getScope() === Mage_Eav_Model_Entity_Attribute::SCOPE_GLOBAL) {
            $html .= Mage::helper('adminhtml')->__('[GLOBAL]');
        } elseif ($this->getElement()->getScope() === Mage_Eav_Model_Entity_Attribute::SCOPE_WEBSITE) {
            $html .= Mage::helper('adminhtml')->__('[WEBSITE]');
        }
        return $html;
    }
}
