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
            'multiselect'   => [
                'backend_model'     => 'eav/entity_attribute_backend_array'
            ],
            'boolean'       => [
                'source_model'      => 'eav/entity_attribute_source_boolean'
            ],
            'customselect'  => [
                'source_model'      => 'eav/entity_attribute_source_table'
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
     * Return default attribute backend model by input type
     *
     * @param string $inputType
     * @return string|null
     */
    public function getAttributeBackendModelByInputType($inputType)
    {
        $inputTypes = $this->getAttributeInputTypes();
        if (!empty($inputTypes[$inputType]['backend_model'])) {
            return $inputTypes[$inputType]['backend_model'];
        }
        return null;
    }

    /**
     * Return default attribute source model by input type
     *
     * @param string $inputType
     * @return string|null
     */
    public function getAttributeSourceModelByInputType($inputType)
    {
        $inputTypes = $this->getAttributeInputTypes();
        if (!empty($inputTypes[$inputType]['source_model'])) {
            return $inputTypes[$inputType]['source_model'];
        }
        return null;
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
