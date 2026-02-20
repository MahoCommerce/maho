<?php

/**
 * Maho
 *
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method Mage_Eav_Model_Resource_Entity_Attribute _getResource()
 * @method Mage_Eav_Model_Resource_Entity_Attribute getResource()
 * @method Mage_Eav_Model_Resource_Entity_Attribute_Collection getCollection()
 * @method Mage_Eav_Model_Resource_Entity_Attribute_Collection getResourceCollection()
 *
 * @method int getAttributeGroupId()
 * @method $this setDefaultValue(int $value)
 * @method int getEntityAttributeId()
 * @method $this setEntityAttributeId(int $value)
 * @method $this setIsFilterable(int $value)
 * @method array getFilterOptions()
 * @method $this setFrontendLabel(string $value)
 * @method $this unsIsVisible()
 */
class Mage_Eav_Model_Entity_Attribute extends Mage_Eav_Model_Entity_Attribute_Abstract
{
    /**
     * Prefix of model events names
     *
     * @var string
     */
    protected $_eventPrefix                         = 'eav_entity_attribute';

    public const ATTRIBUTE_CODE_MAX_LENGTH                 = 30;

    /**
     * Parameter name in event
     *
     * In observe method you can use $observer->getEvent()->getAttribute() in this case
     *
     * @var string
     */
    protected $_eventObject = 'attribute';

    public const CACHE_TAG         = 'EAV_ATTRIBUTE';
    protected $_cacheTag    = 'EAV_ATTRIBUTE';

    /**
     * Retrieve default attribute backend model by attribute code
     *
     * @return string
     */
    #[\Override]
    protected function _getDefaultBackendModel()
    {
        return match ($this->getAttributeCode()) {
            'created_at' => 'eav/entity_attribute_backend_time_created',
            'updated_at' => 'eav/entity_attribute_backend_time_updated',
            'store_id' => 'eav/entity_attribute_backend_store',
            'increment_id' => 'eav/entity_attribute_backend_increment',
            default => parent::_getDefaultBackendModel(),
        };
    }

    /**
     * Retrieve default attribute frontend model
     *
     * @return string
     */
    #[\Override]
    protected function _getDefaultFrontendModel()
    {
        return parent::_getDefaultFrontendModel();
    }

    /**
     * Get default source model
     */
    #[\Override]
    public function getDefaultSourceModel(): string
    {
        if ($this->getAttributeCode() == 'store_id') {
            return 'eav/entity_attribute_source_store';
        }
        return parent::getDefaultSourceModel();
    }

    /**
     * Retrieve default attribute source model
     *
     * @return string
     * @deprecated since 26.1 use getDefaultSourceModel() instead
     */
    #[\Override]
    protected function _getDefaultSourceModel()
    {
        return $this->getDefaultSourceModel();
    }

    /**
     * Delete entity
     *
     * @return Mage_Eav_Model_Resource_Entity_Attribute
     */
    public function deleteEntity()
    {
        return $this->_getResource()->deleteEntity($this);
    }

    /**
     * Load entity_attribute_id into $this by $this->attribute_set_id
     *
     * @return $this
     */
    public function loadEntityAttributeIdBySet()
    {
        // load attributes collection filtered by attribute_id and attribute_set_id
        $filteredAttributes = $this->getResourceCollection()
            ->setAttributeSetFilter($this->getAttributeSetId())
            ->addFieldToFilter('entity_attribute.attribute_id', $this->getId())
            ->load();
        if (count($filteredAttributes) > 0) {
            // getFirstItem() can be used as we can have one or zero records in the collection
            $this->setEntityAttributeId($filteredAttributes->getFirstItem()->getEntityAttributeId());
        }
        return $this;
    }

    /**
     * @throws Mage_Eav_Exception
     */
    #[\Override]
    protected function _beforeSave()
    {
        /**
         * Check for maximum attribute_code length
         */
        if (isset($this->_data['attribute_code'])) {
            if (!Mage::helper('core')->isValidLength($this->_data['attribute_code'], null, self::ATTRIBUTE_CODE_MAX_LENGTH)) {
                throw Mage::exception('Mage_Eav', Mage::helper('eav')->__('Maximum length of attribute code must be less then %s symbols', self::ATTRIBUTE_CODE_MAX_LENGTH));
            }
        }

        $defaultValue   = $this->getDefaultValue();
        $hasDefaultValue = ((string) $defaultValue != '');

        if ($this->getBackendType() == 'decimal' && $hasDefaultValue) {
            $locale = Mage::app()->getLocale()->getLocaleCode();

            // Use NumberFormatter to validate and normalize the number
            $parsedValue = Mage::app()->getLocale()->normalizeNumber($defaultValue);

            if ($parsedValue === false) {
                throw Mage::exception('Mage_Eav', Mage::helper('eav')->__('Invalid default decimal value'));
            }

            try {
                // Set the normalized value (NumberFormatter::parse returns a float)
                $this->setDefaultValue((int) $parsedValue);
            } catch (Exception $e) {
                throw Mage::exception('Mage_Eav', Mage::helper('eav')->__('Invalid default decimal value'));
            }
        }

        if ($this->getBackendType() == 'datetime') {
            if (!$this->getBackendModel()) {
                $this->setBackendModel('eav/entity_attribute_backend_datetime');
            }

            if (!$this->getFrontendModel()) {
                $this->setFrontendModel('eav/entity_attribute_frontend_datetime');
            }

            // save default date value as timestamp
            if ($hasDefaultValue) {
                $format = Mage::app()->getLocale()->getDateFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT);
                try {
                    $defaultValue = Mage::app()->getLocale()->dateMutable($defaultValue, $format, null, false)->getTimestamp();
                    $this->setDefaultValue($defaultValue);
                } catch (Exception $e) {
                    throw Mage::exception('Mage_Eav', Mage::helper('eav')->__('Invalid default date'));
                }
            }
        }

        if ($this->getBackendType() == 'gallery') {
            if (!$this->getBackendModel()) {
                $this->setBackendModel('eav/entity_attribute_backend_media');
            }
        }

        return parent::_beforeSave();
    }

    /**
     * Save additional data
     */
    #[\Override]
    protected function _afterSave()
    {
        $this->_getResource()->saveInSetIncluding($this);
        return parent::_afterSave();
    }

    /**
     * Detect backend storage type using frontend input type
     *
     * @return string backend_type field value
     * @param string $type frontend_input field value
     */
    public function getBackendTypeByInput($type)
    {
        $field = null;
        $field = match ($type) {
            'text', 'gallery', 'media_image', 'file' => 'varchar',
            'image', 'textarea', 'multiselect' => 'text',
            'date' => 'datetime',
            'select', 'boolean' => 'int',
            'price' => 'decimal',
            default => $field,
        };

        return $field;
    }

    /**
     * Detect default value using frontend input type
     *
     * @return string default_value field value
     * @param string $type frontend_input field name
     */
    public function getDefaultValueByInput($type)
    {
        $field = '';
        switch ($type) {
            case 'select':
            case 'gallery':
            case 'media_image':
                break;
            case 'multiselect':
                $field = null;
                break;

            case 'text':
            case 'price':
            case 'image':
            case 'weight':
                $field = 'default_value_text';
                break;

            case 'textarea':
                $field = 'default_value_textarea';
                break;

            case 'date':
                $field = 'default_value_date';
                break;

            case 'boolean':
                $field = 'default_value_yesno';
                break;
        }

        return $field;
    }

    /**
     * Retrieve attribute codes by frontend type
     *
     * @param string $type
     * @return array
     */
    public function getAttributeCodesByFrontendType($type)
    {
        return $this->getResource()->getAttributeCodesByFrontendType($type);
    }

    /**
     * Return array of labels of stores
     *
     * @return array
     */
    public function getStoreLabels()
    {
        if (!$this->getData('store_labels')) {
            $storeLabel = $this->getResource()->getStoreLabelsByAttributeId($this->getId());
            $this->setData('store_labels', $storeLabel);
        }
        return $this->getData('store_labels');
    }

    /**
     * Return store label of attribute
     *
     * @param int $storeId
     * @return string
     * @throws Mage_Core_Model_Store_Exception
     */
    public function getStoreLabel($storeId = null)
    {
        if ($this->hasData('store_label')) {
            return $this->getData('store_label');
        }
        $store = Mage::app()->getStore($storeId);
        $label = false;
        if (!$store->isAdmin()) {
            $labels = $this->getStoreLabels();
            if (isset($labels[$store->getId()])) {
                return $labels[$store->getId()];
            }
        }
        return $this->getFrontendLabel();
    }
}
