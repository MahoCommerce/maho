<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Rule
 */

declare(strict_types=1);

/**
 * @method $this unsActions()
 * @method bool hasActionsSerialized()
 * @method $this unsActionsSerialized()
 * @method $this unsConditions()
 * @method bool hasConditionsSerialized()
 * @method $this unsConditionsSerialized()
 * @method bool hasCustomerGroupIds()
 * @method bool hasDiscountAmount()
 * @method bool hasWebsiteIds()
 */
abstract class Mage_Rule_Model_Abstract extends Mage_Core_Model_Abstract
{
    /**
     * Store rule combine conditions model
     *
     * @var Mage_Rule_Model_Condition_Combine|null
     */
    protected $_conditions;

    /**
     * Store rule actions model
     *
     * @var Mage_Rule_Model_Action_Collection|Mage_Rule_Model_Condition_Combine
     */
    protected $_actions;

    /**
     * Store rule form instance
     *
     * @var \Maho\Data\Form
     */
    protected $_form;

    /**
     * Is model can be deleted flag
     *
     * @var bool
     */
    protected $_isDeleteable = true;

    /**
     * Is model readonly
     *
     * @var bool
     */
    protected $_isReadonly = false;

    /**
     * Getter for rule combine conditions instance
     *
     * @return Mage_Rule_Model_Condition_Combine
     */
    abstract public function getConditionsInstance();

    /**
     * Getter for rule actions collection instance
     *
     * @return Mage_Rule_Model_Action_Collection|Mage_Rule_Model_Condition_Combine
     */
    abstract public function getActionsInstance();

    /**
     * Decode a serialized column value, supporting both JSON and legacy PHP serialize format.
     * If legacy serialized data is detected, it is converted to JSON and persisted back to the database.
     */
    protected function _decodeRuleData(string $value, string $column): ?array
    {
        if (json_validate($value)) {
            return Mage::helper('core')->jsonDecode($value);
        }

        $data = @unserialize($value, ['allowed_classes' => false]);
        if (!is_array($data)) {
            throw new RuntimeException(
                "Could not decode {$column} in {$this->getResource()->getMainTable()} #{$this->getId()}",
            );
        }

        $json = Mage::helper('core')->jsonEncode($data);
        Mage::getSingleton('core/resource')->getConnection('core_write')->update(
            $this->getResource()->getMainTable(),
            [$column => $json],
            [$this->getResource()->getIdFieldName() . ' = ?' => $this->getId()],
        );

        return $data;
    }

    /**
     * Prepare data before saving
     *
     * @return Mage_Rule_Model_Abstract
     */
    #[\Override]
    protected function _beforeSave()
    {
        // Check if discount amount not negative
        if ($this->hasDiscountAmount()) {
            if ((int) $this->getDiscountAmount() < 0) {
                Mage::throwException(Mage::helper('rule')->__('Invalid discount amount.'));
            }
        }

        // Encode conditions as JSON
        if ($this->getConditions()) {
            try {
                $this->setConditionsSerialized(Mage::helper('core')->jsonEncode($this->getConditions()->asArray()));
            } catch (\JsonException $e) {
                Mage::logException($e);
                throw $e;
            }
            $this->unsConditions();
        }

        // Encode actions as JSON
        if ($this->getActions()) {
            try {
                $this->setActionsSerialized(Mage::helper('core')->jsonEncode($this->getActions()->asArray()));
            } catch (\JsonException $e) {
                Mage::logException($e);
                throw $e;
            }
            $this->unsActions();
        }

        /**
         * Prepare website Ids if applicable and if they were set as string in comma separated format.
         * Backwards compatibility.
         */
        if ($this->hasWebsiteIds()) {
            $websiteIds = $this->getWebsiteIds();
            if (is_string($websiteIds) && !empty($websiteIds)) {
                $this->setWebsiteIds(explode(',', $websiteIds));
            }
        }

        /**
         * Prepare customer group Ids if applicable and if they were set as string in comma separated format.
         * Backwards compatibility.
         */
        if ($this->hasCustomerGroupIds()) {
            $groupIds = $this->getData('customer_group_ids');
            if (is_string($groupIds) && !empty($groupIds)) {
                $this->setCustomerGroupIds(explode(',', $groupIds));
            }
        }

        parent::_beforeSave();
        return $this;
    }

    /**
     * Set rule combine conditions model
     *
     * @param Mage_Rule_Model_Condition_Combine $conditions
     *
     * @return Mage_Rule_Model_Abstract
     */
    public function setConditions($conditions)
    {
        $this->_conditions = $conditions;
        return $this;
    }

    /**
     * Retrieve rule combine conditions model
     *
     * @return Mage_Rule_Model_Condition_Combine
     */
    public function getConditions()
    {
        if (empty($this->_conditions)) {
            $this->_resetConditions();
        }

        // Load rule conditions if it is applicable
        if ($this->hasConditionsSerialized()) {
            $conditions = $this->getConditionsSerialized();
            if (!empty($conditions)) {
                $conditions = $this->_decodeRuleData($conditions, 'conditions_serialized');
                if (is_array($conditions) && !empty($conditions)) {
                    $this->_conditions->loadArray($conditions);
                }
            }
            $this->unsConditionsSerialized();
        }

        return $this->_conditions;
    }

    /**
     * Set rule actions model
     *
     * @param Mage_Rule_Model_Action_Collection|Mage_Rule_Model_Condition_Combine $actions
     *
     * @return Mage_Rule_Model_Abstract
     */
    public function setActions($actions)
    {
        $this->_actions = $actions;
        return $this;
    }

    /**
     * Retrieve rule actions model
     *
     * @return Mage_Rule_Model_Action_Collection|Mage_Rule_Model_Condition_Combine
     */
    public function getActions()
    {
        if (!$this->_actions) {
            $this->_resetActions();
        }

        // Load rule actions if it is applicable
        if ($this->hasActionsSerialized()) {
            $actions = $this->getActionsSerialized();
            if (!empty($actions)) {
                $actions = $this->_decodeRuleData($actions, 'actions_serialized');
                if (is_array($actions) && !empty($actions)) {
                    $this->_actions->loadArray($actions);
                }
            }
            $this->unsActionsSerialized();
        }

        return $this->_actions;
    }

    /**
     * Reset rule combine conditions
     *
     * @param null|Mage_Rule_Model_Condition_Combine $conditions
     *
     * @return Mage_Rule_Model_Abstract
     */
    protected function _resetConditions($conditions = null)
    {
        $conditions ??= $this->getConditionsInstance();
        $conditions->setRule($this)->setId('1')->setPrefix('conditions');
        $this->setConditions($conditions);

        return $this;
    }

    /**
     * Reset rule actions
     *
     * @param null|Mage_Rule_Model_Action_Collection|Mage_Rule_Model_Condition_Combine $actions
     *
     * @return Mage_Rule_Model_Abstract
     */
    protected function _resetActions($actions = null)
    {
        $actions ??= $this->getActionsInstance();
        $actions->setRule($this)->setId('1')->setPrefix('actions');
        $this->setActions($actions);

        return $this;
    }

    /**
     * Rule form getter
     *
     * @return \Maho\Data\Form
     */
    public function getForm()
    {
        if (!$this->_form) {
            $this->_form = new \Maho\Data\Form();
        }
        return $this->_form;
    }

    /**
     * Initialize rule model data from array
     *
     * @return Mage_Rule_Model_Abstract
     */
    public function loadPost(array $data)
    {
        $arr = $this->_convertFlatToRecursive($data);
        if (isset($arr['conditions'])) {
            $this->getConditions()->setConditions([])->loadArray($arr['conditions'][1]);
        }
        if (isset($arr['actions'])) {
            $this->getActions()->setActions([])->loadArray($arr['actions'][1], 'actions');
        }

        return $this;
    }

    /**
     * Set specified data to current rule.
     * Set conditions and actions recursively.
     * Convert dates into Y-m-d strings.
     *
     * @return array
     */
    protected function _convertFlatToRecursive(array $data)
    {
        $arr = [];
        foreach ($data as $key => $value) {
            if (($key === 'conditions' || $key === 'actions') && is_array($value)) {
                foreach ($value as $id => $data) {
                    $path = explode('--', (string) $id);
                    $node = & $arr;
                    for ($i = 0, $l = count($path); $i < $l; $i++) {
                        $node = & $node[$key][$path[$i]] ?? [];
                    }
                    foreach ($data as $k => $v) {
                        $node[$k] = $v;
                    }
                }
            } else {
                if (in_array($key, ['from_date', 'to_date']) && $value) {
                    $parsed = DateTime::createFromFormat(Mage_Core_Model_Locale::DATE_FORMAT, $value)
                        ?: new DateTime($value);
                    $value = $parsed->format(Mage_Core_Model_Locale::DATE_FORMAT);
                }
                $this->setData($key, $value);
            }
        }

        return $arr;
    }

    /**
     * Validate rule conditions to determine if rule can run
     *
     * @return bool
     */
    public function validate(\Maho\DataObject $object)
    {
        return $this->getConditions()->validate($object);
    }

    /**
     * Validate rule data
     *
     * @return bool|array - return true if validation passed successfully. Array with errors description otherwise
     */
    public function validateData(\Maho\DataObject $object)
    {
        $result   = [];
        $fromDate = $toDate = null;

        if ($object->hasFromDate() && $object->hasToDate()) {
            $fromDate = $object->getFromDate();
            $toDate = $object->getToDate();
        }

        if ($fromDate && $toDate) {
            $fromDate = DateTime::createFromFormat(Mage_Core_Model_Locale::DATE_FORMAT, $fromDate);
            $toDate = DateTime::createFromFormat(Mage_Core_Model_Locale::DATE_FORMAT, $toDate);

            if ($fromDate > $toDate) {
                $result[] = Mage::helper('rule')->__('End Date must be greater than Start Date.');
            }
        }

        if ($object->hasWebsiteIds()) {
            $websiteIds = $object->getWebsiteIds();
            if (empty($websiteIds)) {
                $result[] = Mage::helper('rule')->__('Websites must be specified.');
            }
        }
        if ($object->hasCustomerGroupIds()) {
            $customerGroupIds = $object->getCustomerGroupIds();
            if (empty($customerGroupIds)) {
                $result[] = Mage::helper('rule')->__('Customer Groups must be specified.');
            }
        }

        return empty($result) ? true : $result;
    }

    /**
     * Add an error to $errors for each problem of the name, the websites, the customer groups, the dates and the
     * trees of conditions.
     * A new rule must have all of them. A website or a group that the rule had before passes, because an admin
     * can delete it after the save.
     */
    protected function _validateScope(Mage_Core_Exception_Input $errors): void
    {
        $helper = Mage::helper('rule');
        $this->_validateTrees($errors);
        if (!$this->getId() || $this->hasData('name')) {
            $name = trim((string) $this->getData('name'));
            if ($name === '' || mb_strlen($name) > 255) {
                $this->_addRecordError($errors, 'name', $helper->__('The rule name must have 1 to 255 characters.'));
            }
        }

        $scopes = [
            'website_ids' => [array_keys(Mage::app()->getWebsites()), 'Websites must be specified.', 'Unknown website ID: %s.'],
            'customer_group_ids' => [Mage::getResourceModel('customer/group_collection')->getAllIds(), 'Customer Groups must be specified.', 'Unknown customer group ID: %s.'],
        ];
        foreach ($scopes as $field => [$knownIds, $requiredMessage, $unknownMessage]) {
            if ($this->getId() && !$this->hasData($field)) {
                continue;
            }
            $ids = $this->getData($field);
            $ids = is_string($ids) ? explode(',', $ids) : (array) $ids;
            if ($ids === []) {
                $this->_addRecordError($errors, $field, $helper->__($requiredMessage));
            }
            $storedIds = $this->getOrigData($field);
            $storedIds = is_string($storedIds) ? explode(',', $storedIds) : (array) $storedIds;
            $knownIds = array_map(strval(...), [...$knownIds, ...$storedIds]);
            foreach ($ids as $id) {
                if (!in_array((string) $id, $knownIds, true)) {
                    $this->_addRecordError($errors, $field, $helper->__($unknownMessage, $id));
                }
            }
        }

        $dates = [];
        foreach (['from_date', 'to_date'] as $field) {
            $date = trim((string) $this->getData($field));
            $dates[$field] = $date === '' ? null : substr($date, 0, 10);
            if ($dates[$field] !== null && !Mage::helper('core')->isValidDate($dates[$field])) {
                $this->_addRecordError($errors, $field, $helper->__('The date "%s" is not valid. Use the format Y-m-d.', $date));
                $dates[$field] = null;
            }
            $this->setData($field, $dates[$field]);
        }
        if ($dates['from_date'] !== null && $dates['to_date'] !== null && $dates['from_date'] > $dates['to_date']) {
            $this->_addRecordError($errors, 'to_date', $helper->__('End Date must be greater than Start Date.'), ['from_date']);
        }
    }

    /**
     * Add an error to $errors for each tree of conditions that holds an attribute with an HTML tag. The admin
     * shows the attribute of a condition as text.
     */
    protected function _validateTrees(Mage_Core_Exception_Input $errors): void
    {
        foreach (['conditions' => $this->getConditions(), 'actions' => $this->getActions()] as $field => $root) {
            if ($root && self::_hasAttributeWithTags($root->asArray())) {
                $errors->addError($field, Mage::helper('rule')->__('Wrong rule specified'));
            }
        }
    }

    /**
     * @param array<array-key, mixed> $tree a condition and its children, in the format of asArray()
     */
    private static function _hasAttributeWithTags(array $tree): bool
    {
        foreach ($tree as $key => $value) {
            if (is_array($value)) {
                if (self::_hasAttributeWithTags($value)) {
                    return true;
                }
            } elseif ($key === 'attribute' && is_string($value) && $value !== Mage::helper('core')->removeTags($value)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Add $message to $errors, unless $errors already has an error of $field: a transport that could not read a
     * value reports it first. A loaded rule reports only a field that changed since the load, or whose rule
     * depends on a field of $relatedFields that changed, so a save of a counter passes with an old stored value.
     *
     * @param list<string> $relatedFields
     */
    protected function _addRecordError(Mage_Core_Exception_Input $errors, string $field, string $message, array $relatedFields = []): void
    {
        if ($this->getId() && $this->getOrigData() !== null
            && array_filter([$field, ...$relatedFields], $this->dataHasChangedFor(...)) === []
        ) {
            return;
        }
        if (!in_array($field, array_column($errors->getErrors(), 'field'), true)) {
            $errors->addError($field, $message);
        }
    }

    /**
     * Check availability to delete rule
     *
     * @return bool
     */
    public function isDeleteable()
    {
        return $this->_isDeleteable;
    }

    /**
     * Set is rule can be deleted flag
     *
     * @param bool $value
     *
     * @return Mage_Rule_Model_Abstract
     */
    public function setIsDeleteable($value)
    {
        $this->_isDeleteable = (bool) $value;
        return $this;
    }

    /**
     * Check if rule is readonly
     *
     * @return bool
     */
    public function isReadonly()
    {
        return $this->_isReadonly;
    }

    /**
     * Set is readonly flag to rule
     *
     * @param bool $value
     *
     * @return Mage_Rule_Model_Abstract
     */
    public function setIsReadonly($value)
    {
        $this->_isReadonly = (bool) $value;
        return $this;
    }

    /**
     * Get rule associated website Ids
     *
     * @return array
     */
    public function getWebsiteIds()
    {
        if (!$this->hasWebsiteIds()) {
            $websiteIds = $this->_getResource()->getWebsiteIds($this->getId());
            $this->setData('website_ids', (array) $websiteIds);
        }
        return $this->_getData('website_ids');
    }

    /**
     * Tell whether the rule is in one or more of the websites $websiteIds.
     *
     * @param list<int|string> $websiteIds
     */
    public function isInWebsites(array $websiteIds): bool
    {
        return array_intersect(array_map(intval(...), (array) $this->getWebsiteIds()), array_map(intval(...), $websiteIds)) !== [];
    }

    /**
     * @param list<int|string> $value
     */
    public function setWebsiteIds(array $value): static
    {
        return $this->setData('website_ids', $value);
    }

    public function getActionsSerialized(): ?string
    {
        $value = $this->getData('actions_serialized');
        return $value === null ? null : (string) $value;
    }

    public function setActionsSerialized(?string $value): static
    {
        return $this->setData('actions_serialized', $value);
    }

    public function getConditionsSerialized(): ?string
    {
        $value = $this->getData('conditions_serialized');
        return $value === null ? null : (string) $value;
    }

    public function setConditionsSerialized(?string $value): static
    {
        return $this->setData('conditions_serialized', $value);
    }

    /**
     * Older code sets the groups as a comma-separated string, so a string becomes a list.
     */
    public function getCustomerGroupIds(): ?array
    {
        $value = $this->getData('customer_group_ids');
        return $value === null || is_array($value) ? $value : explode(',', (string) $value);
    }

    /**
     * @param list<int|string> $value
     */
    public function setCustomerGroupIds(array $value): static
    {
        return $this->setData('customer_group_ids', $value);
    }

    public function getDiscountAmount(): ?float
    {
        $value = $this->getData('discount_amount');
        return $value === null ? null : (float) $value;
    }
}
