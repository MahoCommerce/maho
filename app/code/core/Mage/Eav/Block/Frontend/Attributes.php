<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

declare(strict_types=1);

/**
 * The attributes of an EAV record that have "Visible on Front" set: a table for each group of the
 * attribute set, and a titled text under the table for each value of a Text Area attribute.
 *
 * The layout names the record with the action setRegistryKey, for example current_category.
 */
class Mage_Eav_Block_Frontend_Attributes extends Mage_Core_Block_Template
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('eav/frontend/attributes.phtml');
    }

    public function getEntity(): ?Mage_Core_Model_Abstract
    {
        $entity = $this->getData('entity') ?? Mage::registry((string) $this->getData('registry_key'));
        return $entity instanceof Mage_Core_Model_Abstract ? $entity : null;
    }

    /**
     * Return the label, the value and the code of each visible attribute that has a value.
     * A Text Area value is a text: it shows under the table, because a long value does not fit in a cell.
     *
     * @return list<array{label: string, value: string, code: string, text: bool}>
     */
    public function getAttributeValues(): array
    {
        $entity = $this->getEntity();
        if (!$entity || !method_exists($entity, 'getAttributes')) {
            return [];
        }

        $visible = [];
        foreach ($entity->getAttributes() as $code => $attribute) {
            /** @var Mage_Eav_Model_Entity_Attribute $attribute */
            // An image value is a file name, not text to show
            if ($attribute->getIsVisibleOnFront() && $attribute->getFrontendInput() !== 'image') {
                $visible[$code] = $attribute;
            }
        }
        $this->loadMissingValues($entity, array_keys($visible));

        $rows = [];
        foreach ($visible as $code => $attribute) {
            // A Yes/No frontend shows "No" for null, so an empty raw value skips the row
            $raw = $entity->getData($code);
            if ($raw === null || $raw === '' || $raw === false) {
                continue;
            }
            $value = $attribute->getFrontend()->getValue($entity);
            if (!is_string($value) || $value === '') {
                continue;
            }
            $rows[] = [
                'label' => (string) $attribute->getStoreLabel(),
                'value' => $value,
                'code' => (string) $code,
                'text' => $attribute->getFrontendInput() === 'textarea',
            ];
        }
        return $rows;
    }

    /**
     * Return the values of getAttributeValues() by group of the attribute set of the record. A group
     * has the rows of its table and its texts. The first group takes the title of the block, or
     * "Additional Information". Each other group takes the label of its group in the current store view.
     *
     * @return list<array{title: string, named: bool, rows: list<array>, texts: list<array>}>
     */
    public function getAttributeGroups(): array
    {
        $rows = $this->getAttributeValues();
        if (!$rows) {
            return [];
        }
        $entity = $this->getEntity();
        $attributes = $entity->getAttributes();
        $rowOf = [];
        $list = [];
        foreach ($rows as $row) {
            $attribute = $attributes[$row['code']] ?? null;
            if ($attribute instanceof Mage_Eav_Model_Entity_Attribute_Abstract) {
                $rowOf[$row['code']] = $row;
                $list[] = $attribute;
            }
        }

        $setId = method_exists($entity, 'getAttributeSetId') ? (int) $entity->getAttributeSetId() : 0;
        $tables = [];
        foreach (Mage::helper('eav')->groupAttributesBySet($list, $setId ?: null) as $group) {
            $values = array_map(fn($attribute) => $rowOf[$attribute->getAttributeCode()], $group['attributes']);
            $tables[] = [
                'title' => $group['label'] ?? (string) ($this->getTitle() ?: $this->__('Additional Information')),
                'named' => isset($group['label']),
                'rows' => array_values(array_filter($values, fn(array $row): bool => !$row['text'])),
                'texts' => array_values(array_filter($values, fn(array $row): bool => $row['text'])),
            ];
        }
        return $tables;
    }

    /**
     * Return the HTML of a value. HTML stays only when the attribute allows HTML or uses the WYSIWYG editor.
     */
    public function getValueHtml(array $row): string
    {
        $attribute = $this->getHtmlAttribute($row['code']);
        if ($attribute === null) {
            return $this->escapeHtml($row['value']);
        }
        return $this->renderHtmlValue($attribute, $row['value']);
    }

    /**
     * Return the HTML of a text of getAttributeGroups(). A value without HTML keeps its line breaks.
     */
    public function getTextHtml(array $row): string
    {
        $html = $this->getValueHtml($row);
        return $this->getHtmlAttribute($row['code']) === null ? nl2br($html) : $html;
    }

    /**
     * Return the attribute $code when it allows HTML or uses the WYSIWYG editor, or null.
     */
    protected function getHtmlAttribute(string $code): ?Mage_Eav_Model_Entity_Attribute
    {
        $resource = $this->getEntity()?->getResource();
        $attribute = $resource instanceof Mage_Eav_Model_Entity_Abstract ? $resource->getAttribute($code) : null;
        if (!$attribute instanceof Mage_Eav_Model_Entity_Attribute
            || (!$attribute->getData('is_html_allowed_on_front') && !$attribute->getData('is_wysiwyg_enabled'))
        ) {
            return null;
        }
        return $attribute;
    }

    /**
     * Return the HTML of a value whose attribute allows HTML.
     *
     * This block does not know the template processor of the entity type, so it removes the
     * directives and sanitizes the rest. A block for one entity type overrides this method: the
     * save of its records sanitizes the value, and the block resolves the directives with the
     * processor that the save used.
     */
    protected function renderHtmlValue(Mage_Eav_Model_Entity_Attribute $attribute, string $html): string
    {
        return (string) Mage::getSingleton('core/input_filter_maliciousCode')
            ->filter(Mage_Core_Model_Input_Filter_MaliciousCode::stripDirectives($html));
    }

    /**
     * Read the values that the record did not load, for example a category from a collection.
     *
     * @param list<string> $codes
     */
    protected function loadMissingValues(Mage_Core_Model_Abstract $entity, array $codes): void
    {
        $missing = array_values(array_filter($codes, fn(string $code): bool => !$entity->hasData($code)));
        $resource = $entity->getResource();
        if (!$missing || !$entity->getId() || !method_exists($resource, 'getAttributeRawValue')) {
            return;
        }
        $values = $resource->getAttributeRawValue((int) $entity->getId(), $missing, Mage::app()->getStore()->getId());
        $values = is_array($values) ? $values : [$missing[0] => $values];
        foreach ($missing as $code) {
            if (isset($values[$code]) && $values[$code] !== false) {
                $entity->setData($code, $values[$code]);
            }
        }
    }
}
