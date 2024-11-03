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
 * Eav data helper
 *
 * @category   Mage
 * @package    Mage_Eav
 */
class Mage_Eav_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * XML path to input types validator data in config
     */
    public const XML_PATH_VALIDATOR_DATA_INPUT_TYPES = 'general/validator_data/input_types';

    protected $_moduleName = 'Mage_Eav';

    protected $_attributesHidden = [];

    protected $_attributesLockedFields = [];

    protected $_entityTypeFrontendClasses = [];

    /**
     * Return default frontend classes value labal array
     *
     * @return array
     */
    protected function _getDefaultFrontendClasses()
    {
        return [
            [
                'value' => '',
                'label' => Mage::helper('eav')->__('None')
            ],
            [
                'value' => 'validate-number',
                'label' => Mage::helper('eav')->__('Decimal Number')
            ],
            [
                'value' => 'validate-digits',
                'label' => Mage::helper('eav')->__('Integer Number')
            ],
            [
                'value' => 'validate-email',
                'label' => Mage::helper('eav')->__('Email')
            ],
            [
                'value' => 'validate-url',
                'label' => Mage::helper('eav')->__('URL')
            ],
            [
                'value' => 'validate-alpha',
                'label' => Mage::helper('eav')->__('Letters')
            ],
            [
                'value' => 'validate-alphanum',
                'label' => Mage::helper('eav')->__('Letters (a-z, A-Z) or Numbers (0-9)')
            ]
        ];
    }

    /**
     * Return merged default and entity type frontend classes value label array
     *
     * @param string $entityTypeCode
     * @return array
     */
    public function getFrontendClasses($entityTypeCode)
    {
        $_defaultClasses = $this->_getDefaultFrontendClasses();
        if (isset($this->_entityTypeFrontendClasses[$entityTypeCode])) {
            return array_merge(
                $_defaultClasses,
                $this->_entityTypeFrontendClasses[$entityTypeCode]
            );
        }
        $_entityTypeClasses = Mage::app()->getConfig()
            ->getNode('global/eav_frontendclasses/' . $entityTypeCode);
        if ($_entityTypeClasses) {
            foreach ($_entityTypeClasses->children() as $item) {
                $this->_entityTypeFrontendClasses[$entityTypeCode][] = [
                    'value' => (string)$item->value,
                    'label' => (string)$item->label
                ];
            }
            return array_merge(
                $_defaultClasses,
                $this->_entityTypeFrontendClasses[$entityTypeCode]
            );
        }
        return $_defaultClasses;
    }

    /**
     * Retrieve hidden attributes for entity type
     *
     * @param string $entityTypeCode
     * @return array
     */
    public function getHiddenAttributes($entityTypeCode)
    {
        if (!$entityTypeCode) {
            return [];
        }
        if (isset($this->_attributesHidden[$entityTypeCode])) {
            return $this->_attributesHidden[$entityTypeCode];
        }
        $_data = Mage::app()->getConfig()->getNode('global/eav_attributes/' . $entityTypeCode);
        if ($_data) {
            $this->_attributesHidden[$entityTypeCode] = [];
            foreach ($_data->children() as $attribute) {
                if ($attribute->is('hidden')) {
                    $this->_attributesHidden[$entityTypeCode][] = $attribute->code;
                }
            }
            return $this->_attributesHidden[$entityTypeCode];
        }
        return [];
    }

    /**
     * Retrieve attributes locked fields to edit
     *
     * @param string $entityTypeCode
     * @return array
     */
    public function getAttributeLockedFields($entityTypeCode)
    {
        if (!$entityTypeCode) {
            return [];
        }
        if (isset($this->_attributesLockedFields[$entityTypeCode])) {
            return $this->_attributesLockedFields[$entityTypeCode];
        }
        $_data = Mage::app()->getConfig()->getNode('global/eav_attributes/' . $entityTypeCode);
        if ($_data) {
            $this->_attributesLockedFields[$entityTypeCode] = [];
            foreach ($_data->children() as $attribute) {
                if (isset($attribute->locked_fields)) {
                    $this->_attributesLockedFields[$entityTypeCode][(string)$attribute->code] =
                        array_keys($attribute->locked_fields->asArray());
                }
            }
            return $this->_attributesLockedFields[$entityTypeCode];
        }
        return [];
    }

    /**
     * Get input types validator data
     *
     * @return array
     */
    public function getInputTypesValidatorData()
    {
        return Mage::getStoreConfig(self::XML_PATH_VALIDATOR_DATA_INPUT_TYPES);
    }

    /**
     * Return information array of attribute input types
     * Only a small number of settings returned, so we won't break anything in current dataflow
     * As soon as development process goes on we need to add there all possible settings
     *
     * @param string $inputType
     * @return array
     */
    public function getAttributeInputTypes($inputType = null)
    {
        /**
        * @todo specify there all relations for properties depending on input type
        */
        $inputTypes = [
            'text' => [
                'backend_type'  => 'varchar',
            ],
            'textarea' => [
                'backend_type'  => 'text',
            ],
            'select' => [
                'backend_type'  => 'int',
            ],
            'multiselect' => [
                'backend_model' => 'eav/entity_attribute_backend_array',
                'backend_type'  => 'text',
            ],
            'customselect' => [
                'backend_type'  => 'varchar',
                'source_model'  => 'eav/entity_attribute_source_table',
            ],
            'boolean' => [
                'backend_type'  => 'int',
                'source_model'  => 'eav/entity_attribute_source_boolean',
            ],
            'date' => [
                'backend_type'  => 'datetime',
            ],
            'price' => [
                'backend_type'  => 'decimal',
            ],
            'image' => [
                'backend_type'  => 'text',
            ],
            'gallery' => [
                'backend_type'  => 'varchar',
            ],
            'media_image' => [
                'backend_type'  => 'varchar',
            ],
        ];

        if (is_null($inputType)) {
            return $inputTypes;
        } elseif (isset($inputTypes[$inputType])) {
            return $inputTypes[$inputType];
        }
        return [];
    }

    /**
     * Return default attribute backend type by frontend input type
     *
     * @param string $inputType
     * @return string|null
     */
    public function getAttributeBackendTypeByInputType($inputType)
    {
        return $this->getAttributeInputTypes($inputType)['backend_type'] ?? null;
    }

    /**
     * Return default attribute backend model by frontend input type
     *
     * @param string $inputType
     * @return string|null
     */
    public function getAttributeBackendModelByInputType($inputType)
    {
        return $this->getAttributeInputTypes($inputType)['backend_model'] ?? null;
    }

    /**
     * Return default attribute source model by frontend input type
     *
     * @param string $inputType
     * @return string|null
     */
    public function getAttributeSourceModelByInputType($inputType)
    {
        return $this->getAttributeInputTypes($inputType)['source_model'] ?? null;
    }

    /**
     * Return default value field by frontend input type
     *
     * @param string $inputType
     * @return string|null
     */
    public function getDefaultValueFieldByInputType($inputType)
    {
        switch ($inputType) {
            case 'text':
            case 'price':
            case 'image':
            case 'weight':
                return 'default_value_text';
            case 'textarea':
                return 'default_value_textarea';
            case 'date':
                return 'default_value_date';
            case 'boolean':
                return 'default_value_yesno';
            default:
                return null;
        }
    }

    /**
     * Return entity code formatted for humans
     *
     * @param Mage_Eav_Model_Entity_Type|string $entityTypeCode
     * @return string
     */
    public function formatTypeCode($entityTypeCode)
    {
        if ($entityTypeCode instanceof Mage_Eav_Model_Entity_Type) {
            $entityTypeCode = $entityTypeCode->getEntityTypeCode();
        }
        if (!is_string($entityTypeCode)) {
            $entityTypeCode = '';
        }
        return ucwords(str_replace('_', ' ', $entityTypeCode));
    }
}
