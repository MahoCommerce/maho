<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

declare(strict_types=1);

/**
 * A table of the attributes of an EAV record that have "Visible on Front" set.
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
     *
     * @return list<array{label: string, value: string, code: string}>
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
        uasort($visible, fn($a, $b): int => (int) $a->getData('position') <=> (int) $b->getData('position'));
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
            ];
        }
        return $rows;
    }

    /**
     * Return the HTML of a value. HTML stays only when the attribute allows it on the storefront.
     */
    public function getValueHtml(array $row): string
    {
        $resource = $this->getEntity()?->getResource();
        $attribute = $resource instanceof Mage_Eav_Model_Entity_Abstract ? $resource->getAttribute($row['code']) : null;
        if ($attribute instanceof Mage_Eav_Model_Entity_Attribute && $attribute->getData('is_html_allowed_on_front')) {
            $html = Mage::helper('cms')->getPageTemplateProcessor()->filter($row['value']);
            return Mage::getSingleton('core/input_filter_maliciousCode')->filter($html);
        }
        return $this->escapeHtml($row['value']);
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
