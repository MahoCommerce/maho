<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * EAV Entity attribute model
 *
 * @category   Mage
 * @package    Mage_Eav
 *
 * @method Mage_Eav_Model_Resource_Entity_Attribute _getResource()
 * @method Mage_Eav_Model_Resource_Entity_Attribute getResource()
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
    public const SCOPE_STORE   = 0;
    public const SCOPE_GLOBAL  = 1;
    public const SCOPE_WEBSITE = 2;

    /**
     * Prefix of model events names
     *
     * @var string
     */
    protected $_eventPrefix = 'eav_entity_attribute';

    public const ATTRIBUTE_CODE_MIN_LENGTH = 1;
    public const ATTRIBUTE_CODE_MAX_LENGTH = 30;

    /**
     * Parameter name in event
     *
     * In observe method you can use $observer->getEvent()->getAttribute() in this case
     *
     * @var string
     */
    protected $_eventObject = 'attribute';

    public const CACHE_TAG = 'EAV_ATTRIBUTE';
    protected $_cacheTag = 'EAV_ATTRIBUTE';

    /**
     * Retrieve default attribute backend model by attribute code
     *
     * @return string
     */
    #[\Override]
    protected function _getDefaultBackendModel()
    {
        switch ($this->getAttributeCode()) {
            case 'created_at':
                return 'eav/entity_attribute_backend_time_created';

            case 'updated_at':
                return 'eav/entity_attribute_backend_time_updated';

            case 'store_id':
                return 'eav/entity_attribute_backend_store';

            case 'increment_id':
                return 'eav/entity_attribute_backend_increment';
        }

        return parent::_getDefaultBackendModel();
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
     * Retrieve default attribute source model
     *
     * @return string
     */
    #[\Override]
    public function _getDefaultSourceModel()
    {
        if ($this->getAttributeCode() == 'store_id') {
            return 'eav/entity_attribute_source_store';
        }
        return parent::_getDefaultSourceModel();
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
     * Prepare data for save
     *
     * @inheritDoc
     * @throws Mage_Eav_Exception
     */
    #[\Override]
    protected function _beforeSave()
    {
        /** @var Mage_Eav_Helper_Data $helper */
        $helper = Mage::helper('eav');

        /**
         * Validate attribute_code
         */
        $code = $this->getAttributeCode();
        if (empty($code)) {
            throw Mage::exception('Mage_Eav', $helper->__('Attribute code cannot be empty'));
        }
        if (!preg_match('/^[a-z][a-z_0-9]*$/', $code)) {
            throw Mage::exception('Mage_Eav', $helper->__('Attribute code must contain only letters (a-z), numbers (0-9) or underscore(_), first character should be a letter'));
        }
        if (strlen($code) < self::ATTRIBUTE_CODE_MIN_LENGTH || strlen($code) > self::ATTRIBUTE_CODE_MAX_LENGTH) {
            throw Mage::exception('Mage_Eav', $helper->__('Attribute code must be between %d and %d characters', self::ATTRIBUTE_CODE_MIN_LENGTH, self::ATTRIBUTE_CODE_MAX_LENGTH));
        }

        /**
         * Set default values from input type
         */
        $entityTypeCode = $this->getEntityType()->getEntityTypeCode();
        $inputType = $this->getFrontendInput();
        if (!$this->getBackendType()) {
            $this->setBackendType($helper->getAttributeBackendType($entityTypeCode, $inputType));
        }
        if (!$this->getBackendModel()) {
            $this->setBackendModel($helper->getAttributeBackendModel($entityTypeCode, $inputType));
        }
        if (!$this->getFrontendModel()) {
            $this->setFrontendModel($helper->getAttributeFrontendModel($entityTypeCode, $inputType));
        }
        if (!$this->getSourceModel()) {
            $this->setSourceModel($helper->getAttributeSourceModel($entityTypeCode, $inputType));
        }

        /**
         * Validate default_value
         */
        $defaultValue = $this->getDefaultValue();
        if (!empty($defaultValue)) {
            if ($this->getBackendType() === 'decimal') {
                $locale = Mage::app()->getLocale()->getLocaleCode();
                if (!Zend_Locale_Format::isNumber($defaultValue, ['locale' => $locale])) {
                    throw Mage::exception('Mage_Eav', Mage::helper('eav')->__('Invalid default decimal value'));
                }
                try {
                    $filter = new Zend_Filter_LocalizedToNormalized(
                        ['locale' => Mage::app()->getLocale()->getLocaleCode()]
                    );
                    $this->setDefaultValue($filter->filter($defaultValue));
                } catch (Exception $e) {
                    throw Mage::exception('Mage_Eav', Mage::helper('eav')->__('Invalid default decimal value'));
                }
            }
            if ($this->getBackendType() == 'datetime') {
                $format = Mage::app()->getLocale()->getDateFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT);
                try {
                    $defaultValue = Mage::app()->getLocale()->date($defaultValue, $format, null, false)->toValue();
                    $this->setDefaultValue($defaultValue);
                } catch (Exception $e) {
                    throw Mage::exception('Mage_Eav', Mage::helper('eav')->__('Invalid default date'));
                }
            }
        }

        return parent::_beforeSave();
    }

    /**
     * Save additional data
     *
     * @inheritDoc
     */
    #[\Override]
    protected function _afterSave()
    {
        $this->_getResource()->saveInSetIncluding($this);
        return parent::_afterSave();
    }

    /**
     * Return backend storage type by frontend input type
     *
     * @deprecated Instead use Mage::helper('eav')->getAttributeBackendTypeByInputType()
     * @see Mage_Eav_Helper_Data::getAttributeBackendTypeByInputType()
     * @param string $inputType
     * @return string|null
     */
    public function getBackendTypeByInput($inputType)
    {
        $entityTypeCode = $this->getEntityType()->getEntityTypeCode();
        return Mage::helper('eav')->getAttributeBackendType($entityTypeCode, $inputType);
    }

    /**
     * Return default value field by frontend input type
     *
     * @deprecated Instead use Mage::helper('eav')->getDefaultValueFieldByInputType()
     * @see Mage_Eav_Helper_Data::getDefaultValueFieldByInputType()
     * @param string $inputType
     * @return string|null
     */
    public function getDefaultValueByInput($inputType)
    {
        $entityTypeCode = $this->getEntityType()->getEntityTypeCode();
        return Mage::helper('eav')->getAttributeDefaultValueField($entityTypeCode, $inputType);
    }

    /**
     * Return attribute codes by frontend input type
     *
     * @param string $inputType
     * @return array
     */
    public function getAttributeCodesByFrontendType($inputType)
    {
        return $this->getResource()->getAttributeCodesByFrontendType($inputType);
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
