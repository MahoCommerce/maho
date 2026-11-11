<?php

/**
 * Load, check, save and delete the EAV attributes of every entity type, with their options, for the admin pages, the
 * API and the importer.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

declare(strict_types=1);

use Mage_Eav_Model_Entity_Attribute as Attribute;

class Mage_Eav_Service_Attribute
{
    public const OPTION_INPUT_TYPES = ['select', 'multiselect'];

    private const OPTION_SOURCE_MODEL = 'eav/entity_attribute_source_table';

    /**
     * Return a new attribute of the entity type $entityTypeCode, with the attribute model of the type.
     */
    public function newAttribute(string $entityTypeCode): Attribute
    {
        $type = Mage::getSingleton('eav/config')->getEntityType($entityTypeCode);
        /** @var Attribute $attribute */
        $attribute = Mage::getModel($type->getAttributeModel());
        return $attribute->setEntityTypeId((int) $type->getId());
    }

    /**
     * @throws Mage_Core_Exception_NoSuchEntity when $id is not an attribute of the entity type $entityTypeCode
     */
    public function getById(int $id, string $entityTypeCode): Attribute
    {
        $attribute = $this->newAttribute($entityTypeCode);
        $entityTypeId = (int) $attribute->getEntityTypeId();
        $attribute->load($id);
        if (!$attribute->getId() || (int) $attribute->getEntityTypeId() !== $entityTypeId) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('eav')->__('This attribute no longer exists.'));
        }
        return $attribute;
    }

    /**
     * @throws Mage_Core_Exception_NoSuchEntity when the entity type $entityTypeCode has no attribute $code
     */
    public function getByCode(string $code, string $entityTypeCode): Attribute
    {
        $attribute = $this->newAttribute($entityTypeCode);
        $attribute->loadByCode((int) $attribute->getEntityTypeId(), $code);
        if (!$attribute->getId()) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('eav')->__('This attribute no longer exists.'));
        }
        return $attribute;
    }

    /**
     * Check $attribute as a save does, and save nothing. A new attribute gets the models of its input type and
     * becomes user-defined. The labels lose their HTML tags, except the tags that an option label of an attribute
     * with HTML on the storefront can keep.
     *
     * @throws Mage_Core_Exception_Input with one error for each invalid field
     * @throws Mage_Core_Exception_Conflict when another attribute of the entity type has the code
     */
    public function validate(Attribute $attribute): void
    {
        $this->sanitize($attribute);
        $errors = new Mage_Core_Exception_Input();
        if (!$attribute->getId()) {
            $this->applyInputType($attribute, $errors);
        }
        foreach ($attribute->getValidationErrors()->getErrors() as $error) {
            $errors->addError($error['field'], $error['message']);
        }
        $this->addOptionErrors($attribute, $errors);
        $errors->throwIfErrors();

        $code = (string) $attribute->getAttributeCode();
        if (!$attribute->getId()
            && Mage::getResourceSingleton('eav/entity_attribute')->getIdByCode($attribute->getEntityType()->getEntityTypeCode(), $code)
        ) {
            throw new Mage_Core_Exception_Conflict(Mage::helper('eav')->__('Attribute with the same code already exists'));
        }
    }

    /**
     * Check and save $attribute. The transport puts its input into the attribute first.
     *
     * @throws Mage_Core_Exception_Input with one error for each invalid field
     * @throws Mage_Core_Exception_Conflict when another attribute has the code, or the state of the attribute
     *                                      does not allow the change
     */
    public function save(Attribute $attribute): Attribute
    {
        $this->validate($attribute);
        $attribute->save();
        // The translation cache holds the attribute labels
        Mage::app()->cleanCache([Mage_Core_Model_Translate::CACHE_TAG]);
        return $attribute;
    }

    /**
     * Delete $attribute and the values that the records hold for it.
     *
     * @throws Mage_Core_Exception_Conflict for a system attribute and for an attribute of configurable products
     */
    public function delete(Attribute $attribute): void
    {
        $attribute->delete();
    }

    /**
     * Tell whether $attribute keeps its options in its own list, which saveOptions() changes.
     */
    public function hasOwnOptions(Attribute $attribute): bool
    {
        $sourceModel = (string) $attribute->getData('source_model');
        return in_array($attribute->getFrontendInput(), self::OPTION_INPUT_TYPES, true)
            && ($sourceModel === '' || $sourceModel === self::OPTION_SOURCE_MODEL);
    }

    /**
     * @return array<int, array{order: int, labels: array<int, string>}> the options by ID, in position order
     */
    public function getOptions(Attribute $attribute): array
    {
        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');

        $options = [];
        $select = $adapter->select()
            ->from($resource->getTableName('eav/attribute_option'), ['option_id', 'sort_order'])
            ->where('attribute_id = ?', (int) $attribute->getId())
            ->order(['sort_order ASC', 'option_id ASC']);
        foreach ($adapter->fetchAll($select) as $row) {
            $options[(int) $row['option_id']] = ['order' => (int) $row['sort_order'], 'labels' => []];
        }
        if ($options === []) {
            return [];
        }

        $select = $adapter->select()
            ->from($resource->getTableName('eav/attribute_option_value'), ['option_id', 'store_id', 'value'])
            ->where('option_id IN (?)', array_keys($options));
        foreach ($adapter->fetchAll($select) as $row) {
            $options[(int) $row['option_id']]['labels'][(int) $row['store_id']] = (string) $row['value'];
        }
        return $options;
    }

    /**
     * Save the options of $changes. Each other option keeps its labels, its position and its default flag. A key
     * is an option ID, or a text that is not a number, such as option_0, for a new option. An entry can give:
     * - labels: the label of each store view ID. 0 is the admin label, which a new option must have. An empty
     *   label of a store view removes it.
     * - order: the position. A new option without a position goes after the options before it.
     * - default: true makes the option a default value. A select attribute has one default value.
     * - swatch: a hex color, or an empty text that removes the swatch.
     * - delete: true deletes the option.
     *
     * @param array<int|string, array{labels?: array<int, string>, order?: int, default?: bool, swatch?: string, delete?: bool}> $changes
     * @throws Mage_Core_Exception when the attribute has no option list of its own
     * @throws Mage_Core_Exception_NoSuchEntity when an option ID is not an option of the attribute
     * @throws Mage_Core_Exception_Input
     */
    public function saveOptions(Attribute $attribute, array $changes): Attribute
    {
        if (!$this->hasOwnOptions($attribute)) {
            throw new Mage_Core_Exception(Mage::helper('eav')->__('Only a select or multiselect attribute with its own option list has options.'));
        }
        $options = $this->getOptions($attribute);
        $defaults = $this->getDefaultOptionIds($attribute);

        // The resource writes the default value from the options that it saves, so the payload lists them all
        $payload = ['value' => [], 'order' => [], 'delete' => [], 'swatch' => []];
        foreach ($options as $id => $option) {
            $payload['value'][$id] = $option['labels'];
            $payload['order'][$id] = $option['order'];
        }
        $lastOrder = max([0, ...$payload['order']]);

        foreach ($changes as $key => $change) {
            $isNew = !isset($options[$key]);
            if ($isNew && is_int($key)) {
                throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('eav')->__('Attribute option not found'));
            }
            if (!empty($change['delete'])) {
                if (!$isNew) {
                    $payload['delete'][$key] = 1;
                }
                $defaults = array_values(array_diff($defaults, [$key]));
                continue;
            }

            $labels = $payload['value'][$key] ?? [];
            foreach ($change['labels'] ?? [] as $storeId => $label) {
                if ($label === '' && (int) $storeId !== 0) {
                    unset($labels[$storeId]);
                } else {
                    $labels[$storeId] = $label;
                }
            }
            $payload['value'][$key] = $labels;

            if (isset($change['order'])) {
                $payload['order'][$key] = $change['order'];
            } elseif ($isNew) {
                $payload['order'][$key] = $lastOrder + 1;
            }
            $lastOrder = max($lastOrder, (int) $payload['order'][$key]);

            if (isset($change['default'])) {
                $others = array_values(array_diff($defaults, [$key]));
                $defaults = match (true) {
                    !$change['default'] => $others,
                    $attribute->getFrontendInput() === 'multiselect' => [...$others, $key],
                    default => [$key],
                };
            }
            if (isset($change['swatch'])) {
                $payload['swatch'][$key] = $change['swatch'];
            }
        }

        $attribute->setData('option', $payload);
        $attribute->setData('default', $defaults);
        return $this->save($attribute);
    }

    /**
     * @return list<int>
     */
    private function getDefaultOptionIds(Attribute $attribute): array
    {
        $value = (string) $attribute->getData('default_value');
        if ($value === '') {
            return [];
        }
        return array_values(array_map(intval(...), array_filter(explode(',', $value), is_numeric(...))));
    }

    /**
     * Give a new attribute the backend, source and frontend models of its input type, the storage of the input
     * type when it has none, and the user-defined flag.
     */
    private function applyInputType(Attribute $attribute, Mage_Core_Exception_Input $errors): void
    {
        $input = (string) $attribute->getFrontendInput();
        $type = $attribute->getInputTypes()[$input] ?? null;
        if ($type === null) {
            $errors->addError('frontend_input', Mage::helper('eav')->__('Input type "%s" not found in the input types list.', $input));
            return;
        }
        $attribute->setBackendModel($type['backend_model'] ?? null)
            ->setSourceModel($type['source_model'] ?? null)
            ->setFrontendModel($type['frontend_model'] ?? null);
        if (!$attribute->getBackendType()) {
            $attribute->setBackendType($attribute->getBackendTypeByInput($input));
        }
        $attribute->setData('is_user_defined', 1);
    }

    /**
     * Remove the HTML tags from the labels and the note. An option label of an attribute with HTML on the storefront
     * keeps the allowed tags of the attribute, without the attributes that run a script.
     */
    private function sanitize(Attribute $attribute): void
    {
        $helper = Mage::helper('core');
        $strip = static fn(mixed $text): mixed => is_string($text) ? trim($helper->stripTags($text)) : $text;
        foreach (['frontend_label', 'store_labels'] as $field) {
            $value = $attribute->getData($field);
            if (is_array($value)) {
                $attribute->setData($field, array_map($strip, $value));
            } elseif (is_string($value)) {
                $attribute->setData($field, $strip($value));
            }
        }
        if (is_string($attribute->getData('note'))) {
            $attribute->setData('note', $helper->stripTags($attribute->getData('note')));
        }

        $option = $attribute->getData('option');
        if (!is_array($option) || !is_array($option['value'] ?? null)) {
            return;
        }
        $tags = $attribute->getData('is_html_allowed_on_front') ? $attribute->getAllowedOptionTags() : [];
        $allowed = $tags !== [] ? '<' . implode('><', $tags) . '>' : null;
        $filter = Mage::getSingleton('core/input_filter_maliciousCode');
        foreach ($option['value'] as $key => $labels) {
            foreach (is_array($labels) ? $labels : [] as $storeId => $label) {
                if (is_string($label)) {
                    $label = trim($helper->stripTags($label, $allowed));
                    // An allowed tag keeps its attributes, such as onclick
                    $option['value'][$key][$storeId] = $allowed !== null ? $filter->filter($label) : $label;
                }
            }
        }
        $attribute->setData('option', $option);
    }

    /**
     * Check the options of the option data of the admin form: an admin label for each option that stays, labels
     * of known store views with at most 255 characters, and a position of 0 or more.
     */
    private function addOptionErrors(Attribute $attribute, Mage_Core_Exception_Input $errors): void
    {
        $option = $attribute->getData('option');
        if (!is_array($option) || !is_array($option['value'] ?? null)) {
            return;
        }
        $helper = Mage::helper('eav');
        $storeIds = array_map(intval(...), array_keys(Mage::app()->getStores(true)));
        $found = [];
        foreach ($option['value'] as $key => $labels) {
            if (!empty($option['delete'][$key])) {
                continue;
            }
            $labels = is_array($labels) ? $labels : [];
            if (!is_string($labels[0] ?? null) || $labels[0] === '') {
                $found['label'] = $helper->__('An option must have an admin label.');
            }
            foreach ($labels as $storeId => $label) {
                $field = (int) $storeId === 0 ? 'label' : 'store_labels';
                if (!in_array((int) $storeId, $storeIds, true)) {
                    $found['store_labels'] = $helper->__('Store view %s does not exist.', $storeId);
                } elseif (is_string($label) && mb_strlen($label) > Attribute::LABEL_MAX_LENGTH) {
                    $found[$field] = $helper->__('A label can have at most %d characters.', Attribute::LABEL_MAX_LENGTH);
                }
            }
            $order = $option['order'][$key] ?? null;
            if ($order !== null && $order !== '' && (!is_scalar($order) || filter_var($order, FILTER_VALIDATE_INT) === false || (int) $order < 0)) {
                $found['sort_order'] = $helper->__('The position of an option must be an integer of 0 or more.');
            }
        }
        foreach ($found as $field => $message) {
            $errors->addError($field, $message);
        }
    }
}
