<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Helper functions to read EAV data from config.xml and observers
 */
class Mage_Eav_Helper_Data extends Mage_Core_Helper_Abstract
{
    protected $_moduleName = 'Mage_Eav';

    /**
     * Cache objects grouped by entity type to avoid multiple config.xml reads and event dispatches
     */
    protected array $cacheAttributeDisplayInfo = [];
    protected array $cacheFrontendClasses      = [];
    protected array $cacheInputTypes           = [];
    protected array $cacheForms                = [];

    /** @deprecated */
    protected $_attributesLockedFields         = [];
    /** @deprecated */
    protected $_entityTypeFrontendClasses      = [];

    /**
     * XML paths for various EAV config grouped by entity types
     */
    public const XML_PATH_ATTRIBUTES           = 'global/eav_attributes';
    public const XML_PATH_FRONTEND_CLASSES     = 'global/eav_frontendclasses';
    public const XML_PATH_INPUT_TYPES          = 'global/eav_inputtypes';
    public const XML_PATH_FORMS                = 'global/eav_forms';

    /** @deprecated */
    public const XML_PATH_VALIDATOR_DATA_INPUT_TYPES = 'general/validator_data/input_types';

    /**
     * Return default input validation classes option array
     *
     * @deprecated Instead use Mage::helper('eav')->getFrontendClasses('default')
     * @see Mage_Eav_Helper_Data::getFrontendClasses()
     * @return array
     */
    protected function _getDefaultFrontendClasses()
    {
        return $this->getFrontendClasses('default');
    }

    /**
     * Return merged default and entity type input validation classes option array
     *
     * @param string $entityTypeCode
     * @return array
     */
    public function getFrontendClasses($entityTypeCode)
    {
        if (!isset($this->cacheFrontendClasses[$entityTypeCode])) {
            $options = [];
            $config = Mage::app()->getConfig()->getNode(self::XML_PATH_FRONTEND_CLASSES . '/default');
            if ($entityTypeCode !== 'default') {
                $config->extend(Mage::app()->getConfig()->getNode(self::XML_PATH_FRONTEND_CLASSES . "/$entityTypeCode"), true);
            }
            foreach ($config->children() as $key => $child) {
                $option = $child->asCanonicalArray();
                if (empty($option['value']) || empty($option['label'])) {
                    continue;
                }
                $module = $child->getAttribute('module') ?? 'eav';
                $option['label'] = Mage::helper($module)->__($option['label']);
                $options[$key] = $option;
            }

            $response = new Varien_Object(['options' => $options]);
            Mage::dispatchEvent("adminhtml_{$entityTypeCode}_attribute_frontendclasses", ['response' => $response]);
            $this->cacheFrontendClasses[$entityTypeCode] = $response->getOptions();

            // Update old property for backwards compatibility
            $this->_entityTypeFrontendClasses[$entityTypeCode] = array_values($response->getOptions());
        }
        return $this->cacheFrontendClasses[$entityTypeCode];
    }

    /**
     * Return attribute adminhtml display info per entity type as defined in config.xml or set by observers
     *
     * Not all attributes will be defined, only those with locked_fields or hidden values
     * See <eav_attributes> nodes in various config.xml files for examples
     */
    public function getAttributeDisplayInfo(string $entityTypeCode): array
    {
        if (empty($entityTypeCode)) {
            return [];
        }
        if (!isset($this->cacheAttributeDisplayInfo[$entityTypeCode])) {
            $attributes = [];
            if ($config = Mage::app()->getConfig()->getNode(self::XML_PATH_ATTRIBUTES . "/$entityTypeCode")) {
                foreach ($config->children() as $key => $child) {
                    $attribute = $child->asCanonicalArray();
                    if (empty($attribute['code'])) {
                        continue;
                    }
                    if (isset($child->hidden)) {
                        $attribute['hidden'] = $child->is('hidden');
                    }
                    if (isset($child->locked_fields)) {
                        $attribute['locked_fields'] = array_keys($child->locked_fields->asArray());
                    }
                    $attributes[$key] = $attribute;
                }
            }
            $response = new Varien_Object(['attributes' => $attributes]);
            Mage::dispatchEvent("adminhtml_{$entityTypeCode}_attribute_displayinfo", ['response' => $response]);
            $this->cacheAttributeDisplayInfo[$entityTypeCode] = $response->getAttributes();
        }
        return $this->cacheAttributeDisplayInfo[$entityTypeCode];
    }

    /**
     * Return attributes for entity type that should be hidden from grids
     */
    public function getHiddenAttributes(string $entityTypeCode): array
    {
        $hiddenAttributes = [];
        foreach ($this->getAttributeDisplayInfo($entityTypeCode) as $code => $attribute) {
            if (!empty($attribute['hidden'])) {
                $hiddenAttributes[] = $code;
            }
        }
        return $hiddenAttributes;
    }

    /**
     * Return locked fields per entity type when editing attribute
     *
     * @param string $entityTypeCode
     * @return array
     */
    public function getAttributeLockedFields($entityTypeCode)
    {
        $lockedFields = [];
        foreach ($this->getAttributeDisplayInfo($entityTypeCode) as $code => $attribute) {
            if (!empty($attribute['locked_fields'])) {
                $lockedFields[$code] = $attribute['locked_fields'];
            }
        }
        // Update old property for backwards compatibility
        $this->_attributesLockedFields[$entityTypeCode] = $lockedFields;
        return $lockedFields;
    }

    /**
     * Get input types validator data for all entity types
     *
     * @deprecated Instead use Mage::helper('eav')->getInputTypes()
     * @see Mage_Eav_Helper_Data::getInputTypes()
     * @return array
     */
    public function getInputTypesValidatorData()
    {
        $validatorData = [];
        $config = Mage::app()->getConfig()->getNode(self::XML_PATH_INPUT_TYPES);
        foreach (array_keys($config->asCanonicalArray()) as $entityTypeCode) {
            $inputTypes = $this->getInputTypes($entityTypeCode);
            foreach ($inputTypes as $type) {
                $validatorData[$type['value']] = $type['value'];
            }
        }
        return $validatorData;
    }

    /**
     * Return input types per entity type as defined in config.xml or set by observers
     *
     * Types can define the following fields:
     * - label: (string, required) label to display on the attribute edit form
     * - value: (string, required) value for the `eav_attribute.frontend_input` column
     * - backend_type: (string) value for the `eav_attribute.backend_type` column
     * - backend_model: (string) value for the `eav_attribute.backend_model` column
     * - frontend_model: (string) value for the `eav_attribute.frontend_model` column
     * - source_model: (string) value for the `eav_attribute.source_model` column
     * - default_value_field: (string) optional default value input type on the attribute edit form, examples:
     *     - 'default_value_text'
     *     - 'default_value_textarea'
     *     - 'default_value_date'
     *     - 'default_value_yesno'
     * - hide_fields: (array) fields to hide on the attribute edit form, examples:
     *     - 'is_required': the "Values Required" input
     *     - 'frontend_class': the "Input Validation" input
     *     - '_default_value': the various "Default Value" inputs
     *     - '_front_fieldset': the entire "Frontend Properties" fieldset
     *     - '_scope': the saving scope dropdown
     * - disabled_types: (array) product types to remove from the "Apply To" dropdown, examples:
     *     - 'simple'
     *     - 'bundle'
     *     - 'configurable'
     *     - 'grouped'
     *     - 'virtual'
     * - options_panel: (object) configuration options for the "Manage Options" panel
     *     - 'intype': (string) the HTML input type to use for "Is Default" boxes, can be 'radio' or 'checkbox'
     *
     * See <eav_inputtypes> nodes in various config.xml files for examples
     */
    public function getInputTypes(string $entityTypeCode): array
    {
        if (!isset($this->cacheInputTypes[$entityTypeCode])) {
            $inputTypes = [];
            $config = Mage::app()->getConfig()->getNode(self::XML_PATH_INPUT_TYPES . '/default');
            if ($entityTypeCode !== 'default') {
                $config->extend(Mage::app()->getConfig()->getNode(self::XML_PATH_INPUT_TYPES . "/$entityTypeCode"), true);
            }
            foreach ($config->children() as $key => $child) {
                $type = $child->asCanonicalArray();
                if (empty($type['value']) || empty($type['label'])) {
                    continue;
                }
                $module = $child->getAttribute('module') ?? 'eav';
                $type['label'] = Mage::helper($module)->__($type['label']);

                if (isset($child->hide_fields)) {
                    $type['hide_fields'] = array_keys($child->hide_fields->asArray());
                }
                if (isset($child->disabled_types)) {
                    $type['disabled_types'] = array_keys($child->disabled_types->asArray());
                }
                $inputTypes[$key] = $type;
            }
            $events = ["adminhtml_{$entityTypeCode}_attribute_types"];
            if ($entityTypeCode === 'catalog_product') {
                // Dispatch legacy event for backwards compatibility
                array_unshift($events, 'adminhtml_product_attribute_types');
            }
            foreach ($events as $event) {
                $response = new Varien_Object(['types' => $inputTypes]);
                Mage::dispatchEvent($event, ['response' => $response]);
                $inputTypes = $response->getTypes();
            }
            foreach ($inputTypes as $type) {
                $this->cacheInputTypes[$entityTypeCode][$type['value']] = $type;
            }
        }
        return $this->cacheInputTypes[$entityTypeCode];
    }

    /**
     * Return default attribute backend type by frontend input type
     */
    public function getAttributeBackendType(string $entityTypeCode, string $inputType): ?string
    {
        return $this->getInputTypes($entityTypeCode)[$inputType]['backend_type'] ?? null;
    }

    /**
     * Return default attribute backend model by frontend input type
     */
    public function getAttributeBackendModel(string $entityTypeCode, string $inputType): ?string
    {
        return $this->getInputTypes($entityTypeCode)[$inputType]['backend_model'] ?? null;
    }

    /**
     * Return default attribute frontend model by frontend input type
     */
    public function getAttributeFrontendModel(string $entityTypeCode, string $inputType): ?string
    {
        return $this->getInputTypes($entityTypeCode)[$inputType]['frontend_model'] ?? null;
    }

    /**
     * Return default attribute source model by frontend input type
     */
    public function getAttributeSourceModel(string $entityTypeCode, string $inputType): ?string
    {
        return $this->getInputTypes($entityTypeCode)[$inputType]['source_model'] ?? null;
    }

    /**
     * Return default value field by frontend input type
     */
    public function getAttributeDefaultValueField(string $entityTypeCode, string $inputType): ?string
    {
        return $this->getInputTypes($entityTypeCode)[$inputType]['default_value_field'] ?? null;
    }

    /**
     * Return hidden fields per input type when editing attribute for entity type
     */
    public function getInputTypeHiddenFields(string $entityTypeCode): array
    {
        $hiddenFields = [];
        foreach ($this->getInputTypes($entityTypeCode) as $key => $type) {
            if (isset($type['hide_fields'])) {
                $hiddenFields[$key] = $type['hide_fields'];
            }
        }
        return $hiddenFields;
    }

    /**
     * Return disable fields per input type when editing attribute for entity type
     */
    public function getInputTypeDisabledApplyToOptions(string $entityTypeCode): array
    {
        $disabledTypes = [];
        foreach ($this->getInputTypes($entityTypeCode) as $key => $type) {
            if (isset($type['disabled_types'])) {
                $disabledTypes[$key] = $type['disabled_types'];
            }
        }
        return $disabledTypes;
    }

    /**
     * Return options panel info per input type when editing attribute for entity type
     */
    public function getInputTypeOptionsPanelInfo(string $entityTypeCode): array
    {
        $optionsPanel = [];
        foreach ($this->getInputTypes($entityTypeCode) as $key => $type) {
            if (isset($type['options_panel'])) {
                $optionsPanel[$key] = $type['options_panel'];
            }
        }
        return $optionsPanel;
    }

    /**
     * Return forms defined per entity type as defined in config.xml or set by observers
     */
    public function getForms(string $entityTypeCode): array
    {
        if (empty($entityTypeCode)) {
            return [];
        }
        if (!isset($this->cacheForms[$entityTypeCode])) {
            $forms = [];
            if ($config = Mage::app()->getConfig()->getNode(self::XML_PATH_FORMS . "/$entityTypeCode")) {
                foreach ($config->children() as $key => $child) {
                    $form = $child->asCanonicalArray();
                    if (empty($form['value']) || empty($form['label'])) {
                        continue;
                    }
                    $module = $child->getAttribute('module') ?? 'eav';
                    $form['label'] = Mage::helper($module)->__($form['label']);
                    $forms[$key] = $form;
                }
            }
            $response = new Varien_Object(['forms' => $forms]);
            Mage::dispatchEvent("adminhtml_{$entityTypeCode}_attribute_forms", ['response' => $response]);
            $this->cacheForms[$entityTypeCode] = $response->getForms();
        }
        return $this->cacheForms[$entityTypeCode];
    }

    /**
     * Return entity code formatted for humans
     */
    public function formatTypeCode(string $entityTypeCode): string
    {
        switch ($entityTypeCode) {
        case Mage_Catalog_Model_Product::ENTITY:
            return 'Product';
        case Mage_Catalog_Model_Category::ENTITY:
            return 'Category';
        default:
            return ucwords(str_replace('_', ' ', $entityTypeCode));
        }
    }
}
