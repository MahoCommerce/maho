<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

declare(strict_types=1);

/**
 * @method Mage_Eav_Model_Resource_Entity_Attribute _getResource()
 * @method Mage_Eav_Model_Resource_Entity_Attribute getResource()
 * @method Mage_Eav_Model_Resource_Entity_Attribute_Collection getCollection()
 * @method Mage_Eav_Model_Resource_Entity_Attribute_Collection getResourceCollection()
 *
 * @method $this unsIsVisible()
 */
class Mage_Eav_Model_Entity_Attribute extends Mage_Eav_Model_Entity_Attribute_Abstract
{
    /** The input types whose fields a condition reads by option. */
    public const OPTION_INPUTS = ['select', 'multiselect', 'boolean'];

    /**
     * The input types whose fields a condition reads only for a value or no value. A file, an image and a multiline
     * field are not here: their form inputs do not hold the stored value in one input.
     */
    public const FREE_VALUE_INPUTS = ['text', 'textarea', 'date', 'datetime'];

    /**
     * Prefix of model events names
     *
     * @var string
     */
    #[\Override]
    protected $_eventPrefix                         = 'eav_entity_attribute';

    public const ATTRIBUTE_CODE_MAX_LENGTH                 = 64;

    /** The code of a new attribute: a letter a-z, then letters a-z, digits and underscores. "event" is not a valid code. */
    public const ATTRIBUTE_CODE_PATTERN = '/^(?!event$)[a-z][a-z_0-9]{1,254}$/';

    public const LABEL_MAX_LENGTH = 255;

    /**
     * Parameter name in event
     *
     * In observe method you can use $observer->getEvent()->getAttribute() in this case
     *
     * @var string
     */
    #[\Override]
    protected $_eventObject = 'attribute';

    public const CACHE_TAG         = 'EAV_ATTRIBUTE';
    #[\Override]
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
     */
    #[\Override]
    #[\Deprecated(message: 'since 26.1 use getDefaultSourceModel() instead')]
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
     * The column limits the length of the code. Mage_Eav_Service_Attribute checks the rules of the admin form
     * and of the API with getValidationErrors(), so a setup script can save a code that the form refuses.
     *
     * @throws Mage_Eav_Exception
     */
    #[\Override]
    protected function _beforeSave()
    {
        if (isset($this->_data['attribute_code'])
            && !Mage::helper('core')->isValidLength($this->_data['attribute_code'], null, self::ATTRIBUTE_CODE_MAX_LENGTH)
        ) {
            throw Mage::exception('Mage_Eav', Mage::helper('eav')->__('Maximum length of attribute code must be less then %s symbols', self::ATTRIBUTE_CODE_MAX_LENGTH));
        }

        $defaultValue   = $this->getDefaultValue();
        $hasDefaultValue = ((string) $defaultValue != '');

        if ($this->getBackendType() == 'decimal' && $hasDefaultValue) {
            // Use NumberFormatter to validate and normalize the number
            $parsedValue = Mage::app()->getLocale()->normalizeNumber($defaultValue);

            if ($parsedValue === false) {
                throw Mage::exception('Mage_Eav', Mage::helper('eav')->__('Invalid default decimal value'));
            }

            $this->setDefaultValue((string) $parsedValue);
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
                    $parsed = DateTime::createFromFormat($format, $defaultValue);
                    $defaultValue = ($parsed !== false ? $parsed : new DateTime($defaultValue))->getTimestamp();
                    $this->setDefaultValue($defaultValue);
                } catch (Exception) {
                    throw Mage::exception('Mage_Eav', Mage::helper('eav')->__('Invalid default date'));
                }
            }
        }

        if ($this->getBackendType() == 'gallery') {
            if (!$this->getBackendModel()) {
                $this->setBackendModel('eav/entity_attribute_backend_media');
            }
        }

        if (!$this->getId() && !$this->getAttributeSetId() && $this->getEntityTypeId()
            && $this->getAdminPageSettings()?->addToDefaultSet
        ) {
            $this->assignFirstGroupOfDefaultSet();
        }

        if ($this->hasData('depends_on')) {
            $this->setDependsOn($this->getDependsOn());
        }
        if ($this->hasData('required_when')) {
            $this->setRequiredWhen($this->getRequiredWhen());
        }

        return parent::_beforeSave();
    }

    /**
     * Return one error for each value that the admin form and the API refuse. A subclass adds the rules of its
     * entity type. A saved attribute keeps its code, its input type and its locked fields. The model does not
     * call this method when it saves: Mage_Eav_Service_Attribute does.
     */
    public function getValidationErrors(): Mage_Core_Exception_Input
    {
        $helper = Mage::helper('eav');
        $errors = new Mage_Core_Exception_Input();
        $isNew = !$this->getId();

        $code = $this->getData('attribute_code');
        if ($isNew && (!is_string($code) || !preg_match(self::ATTRIBUTE_CODE_PATTERN, $code))) {
            $errors->addError('attribute_code', $helper->__('Attribute code is invalid. Please use only letters (a-z), numbers (0-9) or underscore(_) in this field, first character should be a letter. Do not use "event" for an attribute code.'));
        } elseif (is_string($code) && !Mage::helper('core')->isValidLength($code, null, self::ATTRIBUTE_CODE_MAX_LENGTH)) {
            $errors->addError('attribute_code', $helper->__('Maximum length of attribute code must be less then %s symbols', self::ATTRIBUTE_CODE_MAX_LENGTH));
        }

        if (!$isNew) {
            $fixed = [
                'attribute_code' => $helper->__('The attribute code cannot change after the attribute is created.'),
                'frontend_input' => $helper->__('The input type cannot change after the attribute is created.'),
            ];
            foreach ($fixed as $field => $message) {
                if ($this->hasChangedStoredValue($field)) {
                    $errors->addError($field, $message);
                }
            }
            foreach ($this->getLockedFields() as $field) {
                if ($this->hasChangedStoredValue($field)) {
                    $errors->addError($field, $helper->__('The field %s cannot change for this attribute.', $field));
                }
            }
        }

        $label = $this->getData('frontend_label');
        if ($isNew || $this->isValueChanged('frontend_label')) {
            $labels = is_array($label) ? $label : [0 => $label];
            if (!is_string($labels[0] ?? null) || trim($labels[0]) === '') {
                $errors->addError('frontend_label', $helper->__('Frontend label is not defined'));
            }
            foreach ($labels as $text) {
                if (is_string($text) && mb_strlen($text) > self::LABEL_MAX_LENGTH) {
                    $errors->addError('frontend_label', $helper->__('A label can have at most %d characters.', self::LABEL_MAX_LENGTH));
                    break;
                }
            }
        }

        $class = $this->getData('frontend_class');
        if ($class !== null && $class !== '' && $this->isValueChanged('frontend_class') && $this->getEntityTypeId()) {
            $classes = array_values(array_filter(array_column(
                $helper->getFrontendClasses($this->getEntityType()->getEntityTypeCode()),
                'value',
            )));
            if (!in_array($class, $classes, true)) {
                $errors->addError('frontend_class', $helper->__('The input validation must be one of: %s', implode(', ', $classes)));
            }
        }

        foreach (['depends_on', 'required_when'] as $column) {
            if ($this->isValueChanged($column)) {
                $this->addConditionErrors($errors, $column);
            }
        }

        return $errors;
    }

    /**
     * Return the fields that the condition of this attribute can read, keyed by code: each attribute of
     * the entity type with a list of options, and each visible attribute with a free value, such as a
     * text, except this attribute. A field with a free value has no options: a condition can only ask
     * whether it holds a value (Maho\Data\Form\Condition::ANY_VALUE). A subclass adds the fields of its
     * edit form that are not attributes.
     *
     * @return array<string, array{label: string, options: list<array{value: string, label: string}>}>
     */
    public function getDependencySources(): array
    {
        if (!$this->getEntityTypeId()) {
            return [];
        }
        $entityType = $this->getEntityType();
        /** @var Mage_Eav_Model_Resource_Entity_Attribute_Collection $collection */
        $collection = Mage::getResourceModel($entityType->getEntityAttributeCollection());
        $collection->setEntityTypeFilter((int) $entityType->getId())
            ->addFieldToFilter('main_table.frontend_input', ['in' => [...self::OPTION_INPUTS, ...self::FREE_VALUE_INPUTS]])
            ->addFieldToFilter('main_table.frontend_label', ['notnull' => true])
            ->setOrder('main_table.frontend_label', 'ASC');

        $sources = [];
        foreach ($collection as $attribute) {
            /** @var Mage_Eav_Model_Entity_Attribute_Abstract $attribute */
            $code = (string) $attribute->getAttributeCode();
            if ($code === $this->getAttributeCode() || (string) $attribute->getFrontendLabel() === '') {
                continue;
            }
            $hasOptions = in_array($attribute->getFrontendInput(), self::OPTION_INPUTS, true);
            // A field with a free value that no form shows, such as a token of the customer, is not a choice
            if (!$hasOptions && $attribute->hasData('is_visible') && !$attribute->getData('is_visible')) {
                continue;
            }
            try {
                // A Yes/No attribute stores no source model, so usesSource() is false, but its default source has the options
                $options = $hasOptions && ($attribute->usesSource() || $attribute->getFrontendInput() === 'boolean')
                    ? $attribute->getSource()->getAllOptions()
                    : [];
            } catch (Mage_Core_Exception) {
                continue;
            }
            $sources[$code] = [
                'label' => Mage::helper('eav')->__((string) $attribute->getFrontendLabel()),
                'options' => self::flattenOptions($options),
            ];
        }
        return $sources;
    }

    /**
     * Return the codes of the attributes whose condition in one of $columns reads this attribute: the attributes of
     * the same entity type, and with $otherTypes the attributes of the other types of getDependentScopes().
     *
     * @param list<string> $columns depends_on, required_when or both
     * @return list<string>
     */
    public function getDependentAttributeCodes(array $columns = ['depends_on'], bool $otherTypes = false): array
    {
        $code = (string) ($this->getOrigData('attribute_code') ?? $this->getAttributeCode());
        if ($code === '' || !$this->getEntityTypeId()) {
            return [];
        }
        $adapter = $this->_getResource()->getReadConnection();
        $codes = [];
        foreach ($this->getDependentScopes($code) as [$entityTypeId, $field]) {
            if (!$otherTypes && $entityTypeId !== (int) $this->getEntityTypeId()) {
                continue;
            }
            $select = $adapter->select()
                ->from($this->_getResource()->getMainTable(), ['attribute_code', ...$columns])
                ->where('entity_type_id = ?', $entityTypeId)
                ->where(implode(' OR ', array_map(fn(string $column): string => $adapter->quoteIdentifier($column) . ' IS NOT NULL', $columns)));
            foreach ($adapter->fetchAll($select) as $row) {
                $reader = Mage::getModel('eav/entity_attribute')->setData($row);
                $isSelf = $entityTypeId === (int) $this->getEntityTypeId() && $row['attribute_code'] === $code;
                foreach ($columns as $column) {
                    $condition = $reader->decodeConditionOf($column);
                    if (!$isSelf && $condition && in_array($field, \Maho\Data\Form\Condition::fields($condition), true)) {
                        $codes[] = (string) $row['attribute_code'];
                        break;
                    }
                }
            }
        }
        return array_values(array_unique($codes));
    }

    /**
     * Return the condition that the column $column holds, for a caller outside the attribute.
     */
    public function decodeConditionOf(string $column): ?array
    {
        return $this->decodeCondition($column);
    }

    /**
     * Return the places where a condition can read the attribute $code: pairs of an entity type ID and the field name
     * that a condition of that type uses. A subclass adds another entity type, as an address reads a customer field.
     *
     * @return list<array{int, string}>
     */
    protected function getDependentScopes(string $code): array
    {
        return [[(int) $this->getEntityTypeId(), $code]];
    }

    /**
     * Add an error to $errors for each part of the condition in $column that a form cannot apply. The condition
     * of depends_on also cannot read a field with a condition of its own, nor be on a field that another condition
     * reads. The condition of required_when only reads values, so it has no such rule.
     */
    protected function addConditionErrors(Mage_Core_Exception_Input $errors, string $column): void
    {
        $raw = $this->getData($column);
        if ($raw === null || $raw === '' || $raw === '[]') {
            return;
        }
        $helper = Mage::helper('eav');
        // The configuration locks depends_on on a field that the models always require, for both conditions
        if (in_array('depends_on', $this->getLockedFields(), true)) {
            $errors->addError($column, $helper->__('The store always requires this field, so it cannot have a condition.'));
            return;
        }
        $condition = $this->decodeCondition($column);
        if ($condition === null) {
            $errors->addError($column, $helper->__('The condition is not valid JSON.'));
            return;
        }
        foreach (\Maho\Data\Form\Condition::structureErrors($condition) as $message) {
            $errors->addError($column, $message);
        }

        $isShowCondition = $column === 'depends_on';
        $dependents = $isShowCondition && $this->getId() ? $this->getDependentAttributeCodes() : [];
        if ($dependents) {
            $errors->addError($column, $helper->__('This attribute cannot have a condition, because the condition of %s reads it.', implode(', ', $dependents)));
        }

        $sources = $this->getDependencySources();
        $config = Mage::getSingleton('eav/config');
        foreach (\Maho\Data\Form\Condition::wantedValues($condition) as $field => $values) {
            if ($field === $this->getAttributeCode()) {
                $errors->addError($column, $helper->__('The condition cannot read the attribute itself.'));
                continue;
            }
            if (!isset($sources[$field])) {
                $errors->addError($column, $helper->__('The condition reads the field %s, which this form does not have.', $field));
                continue;
            }
            $source = $config->getAttribute($this->getEntityType(), $field);
            if ($isShowCondition && $source instanceof Mage_Eav_Model_Entity_Attribute_Abstract && $source->getDependsOn() !== null) {
                $errors->addError($column, $helper->__('The condition cannot read %s, because %s has a condition of its own.', $field, $field));
            }
            $unknown = array_diff($values, [...array_column($sources[$field]['options'], 'value'), \Maho\Data\Form\Condition::ANY_VALUE]);
            if ($unknown) {
                $errors->addError($column, $helper->__('The field %s has no option with the value %s.', $field, implode(', ', $unknown)));
            }
        }
    }

    /**
     * @param array<mixed> $options the result of getAllOptions(), which can hold groups of options
     * @return list<array{value: string, label: string}>
     */
    protected static function flattenOptions(array $options): array
    {
        $result = [];
        foreach ($options as $option) {
            if (is_array($option['value'] ?? null)) {
                $result = [...$result, ...self::flattenOptions($option['value'])];
            } elseif (isset($option['value']) && $option['value'] !== '') {
                $result[] = ['value' => (string) $option['value'], 'label' => (string) ($option['label'] ?? $option['value'])];
            }
        }
        return $result;
    }

    /**
     * Return the fields that a save cannot change on this saved attribute: the locked fields that the
     * configuration gives for its code, and the unique flag of a system attribute.
     *
     * @return list<string>
     */
    public function getLockedFields(): array
    {
        $fields = [];
        if ($this->getEntityTypeId()) {
            $locked = Mage::helper('eav')->getAttributeLockedFields($this->getEntityType()->getEntityTypeCode());
            $fields = array_values($locked[(string) $this->getOrigData('attribute_code')] ?? []);
        }
        if ($this->getOrigData('is_user_defined') !== null && !$this->getOrigData('is_user_defined')) {
            $fields[] = 'is_unique';
        }
        return array_values(array_unique($fields));
    }

    /**
     * Return the HTML tags that an option label keeps when the attribute allows HTML on the storefront.
     *
     * @return list<string>
     */
    public function getAllowedOptionTags(): array
    {
        return $this->getAdminPageSettings()->allowedTags ?? [];
    }

    /**
     * Tell whether $field holds a value that differs from the value that the attribute had when it was loaded.
     */
    protected function hasChangedStoredValue(string $field): bool
    {
        $stored = $this->getOrigData($field);
        return $stored !== null && $this->hasData($field) && (string) $this->getData($field) !== (string) $stored;
    }

    /**
     * Tell whether a save writes a new value of $field: the attribute is new and has a value, or the attribute was
     * loaded and the value changed since. An attribute from Mage_Eav_Model_Config has no loaded values, so a save of
     * it does not check the values that it already had.
     */
    protected function isValueChanged(string $field): bool
    {
        if (!$this->getId()) {
            return $this->getData($field) !== null;
        }
        return $this->getOrigData('attribute_id') !== null && $this->dataHasChangedFor($field);
    }

    /**
     * Put this attribute in the first group of the default set of its entity type, so that the
     * records show it at once. The save writes the set and the group.
     */
    protected function assignFirstGroupOfDefaultSet(): void
    {
        $setId = (int) $this->getEntityType()->getDefaultAttributeSetId();
        $groupId = (int) Mage::getResourceModel('eav/entity_attribute_group_collection')
            ->setAttributeSetFilter($setId)
            ->setSortOrder()
            ->getFirstItem()
            ->getId();
        if ($setId && $groupId) {
            $this->setAttributeSetId($setId);
            $this->setAttributeGroupId($groupId);
        }
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
     * Return the settings of the admin pages that manage the attributes of this entity type,
     * or null when System > Attributes does not manage the type.
     *
     * The module of the entity type gives them, and declares the menu entry and the ACL resource
     * in its own adminhtml.xml.
     */
    public function getAdminPageSettings(): ?Mage_Eav_Model_Entity_Attribute_AdminPageSettings
    {
        return null;
    }

    /**
     * Return the summary that the edit page of this attribute shows above the properties, or null for no summary.
     *
     * Each row names one setting and lists its values. A value that does not show the attribute has
     * shown false. A row note explains the row.
     *
     * @return array{comment: string, rows: list<array{label: string, items: list<array{label: string, shown: bool}>, note: ?string}>}|null
     */
    public function getAttributeSummary(): ?array
    {
        return null;
    }

    /**
     * Return a text that the edit page of $set shows above the groups, or null for no text.
     * Each line of the text is a paragraph.
     */
    public function getSetNote(Mage_Eav_Model_Entity_Attribute_Set $set): ?string
    {
        return null;
    }

    /**
     * Return the question that the edit page of $set asks before it deletes the set.
     *
     * The records of the set move to the default set. The nightly eav_clean_orphaned_records job
     * then deletes their values of the attributes that are not in the default set, so the
     * question names those attributes.
     */
    public function getSetDeleteQuestion(Mage_Eav_Model_Entity_Attribute_Set $set): string
    {
        $helper = Mage::helper('eav');
        $labels = Mage::getResourceSingleton('eav/entity_attribute_set')->getMissingAttributeLabels(
            (int) $set->getId(),
            (int) $this->getEntityType()->getDefaultAttributeSetId(),
        );

        $text = $helper->__('The records of this set will move to the default set.');
        if ($labels) {
            $text .= ' ' . $helper->__('They will lose their values of the attributes that are not in the default set: %s.', implode(', ', $labels));
        }
        return $text . ' ' . $helper->__('Delete the set?');
    }

    /**
     * Return the question that the edit page of $set asks before a save takes $attributes out of
     * the set, or null for no question.
     *
     * The nightly eav_clean_orphaned_records job deletes the values of an attribute that is not in
     * the set of the record, so the question names each attribute and its number of records with a value.
     *
     * @param list<Mage_Eav_Model_Entity_Attribute_Abstract> $attributes
     */
    public function getSetRemoveQuestion(Mage_Eav_Model_Entity_Attribute_Set $set, array $attributes): ?string
    {
        if (!$attributes) {
            return null;
        }
        $helper = Mage::helper('eav');
        $resource = Mage::getResourceSingleton('eav/entity_attribute_set');
        $names = [];
        foreach ($attributes as $attribute) {
            $label = self::getQuestionLabel($attribute);
            $count = $resource->countRecordsWithValue((int) $set->getId(), $attribute);
            $names[] = $count ? $helper->__('%s (records with a value: %d)', $label, $count) : $label;
        }
        return $helper->__('These attributes leave the set: %s.', implode(', ', $names))
            . ' ' . $helper->__('Tonight, the store deletes their values in the records of this set. Save the set?');
    }

    /**
     * Return the label of $attribute for a question, or its code when it has no label.
     */
    protected static function getQuestionLabel(Mage_Eav_Model_Entity_Attribute_Abstract $attribute): string
    {
        return (string) ($attribute->getFrontendLabel() ?: $attribute->getAttributeCode());
    }

    /**
     * Return the labels of $attributes for a question, separated by commas.
     *
     * @param list<Mage_Eav_Model_Entity_Attribute_Abstract> $attributes
     */
    protected static function getQuestionLabels(array $attributes): string
    {
        $labels = [];
        foreach ($attributes as $attribute) {
            $labels[] = self::getQuestionLabel($attribute);
        }
        return implode(', ', $labels);
    }

    /**
     * Turn the posted values of the admin attribute form into the data of this attribute.
     * The attribute is loaded when it exists.
     */
    public function prepareAdminData(array $data): array
    {
        return $data;
    }

    /**
     * Save the posted values of the admin attribute form that the attribute does not save itself.
     */
    public function saveAdminData(array $data): void {}

    /**
     * Return the input types that the admin offers for the entity type of this attribute.
     *
     * The key is the input code. Each type has a label, and can name a backend_model, a
     * source_model and a frontend_model. The event eav_attribute_input_types_prepare lets a
     * module add or remove a type.
     *
     * @return array<string, array{label: string, backend_model?: string, source_model?: string, frontend_model?: string}>
     */
    public function getInputTypes(): array
    {
        $types = new \Maho\DataObject($this->_getInputTypes());
        Mage::dispatchEvent('eav_attribute_input_types_prepare', [
            'attribute' => $this,
            'entity_type_code' => $this->getEntityType()->getEntityTypeCode(),
            'types' => $types,
        ]);
        return $types->getData();
    }

    /**
     * @return array<string, array{label: string, backend_model?: string, source_model?: string, frontend_model?: string}>
     */
    protected function _getInputTypes(): array
    {
        $types = [];
        foreach (Mage::getModel('eav/adminhtml_system_config_source_inputtype')->toOptionArray() as $option) {
            $types[$option['value']] = ['label' => $option['label']];
        }
        $types['boolean']['source_model'] = 'eav/entity_attribute_source_boolean';
        $types['multiselect']['backend_model'] = 'eav/entity_attribute_backend_array';
        return $types;
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
            'image', 'textarea', 'multiselect', 'multiline' => 'text',
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
     * @param int|string|Mage_Core_Model_Store|null $storeId
     * @throws Mage_Core_Model_Store_Exception
     */
    #[\Override]
    public function getStoreLabel($storeId = null): ?string
    {
        if ($this->hasData('store_label')) {
            $value = $this->getData('store_label');
            return $value === null ? null : (string) $value;
        }
        $store = Mage::app()->getStore($storeId);
        if (!$store->isAdmin()) {
            $labels = $this->getStoreLabels();
            if (isset($labels[$store->getId()])) {
                return (string) $labels[$store->getId()];
            }
        }
        $label = $this->getFrontendLabel();
        return is_string($label) ? $label : null;
    }

    public function getAttributeGroupId(): ?int
    {
        $value = $this->getData('attribute_group_id');
        return $value === null ? null : (int) $value;
    }

    public function setDefaultValue(int|string|null $value): static
    {
        return $this->setData('default_value', $value);
    }

    public function getEntityAttributeId(): ?int
    {
        $value = $this->getData('entity_attribute_id');
        return $value === null ? null : (int) $value;
    }

    public function setEntityAttributeId(?int $value): static
    {
        return $this->setData('entity_attribute_id', $value);
    }

    public function getFilterOptions(): ?array
    {
        return $this->getData('filter_options');
    }

    public function setFrontendLabel(?string $value): static
    {
        return $this->setData('frontend_label', $value);
    }

    public function setIsFilterable(?int $value): static
    {
        return $this->setData('is_filterable', $value);
    }

}
