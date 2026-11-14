<?php

/**
 * The editor of the condition that shows an attribute in a form, on the attribute edit page.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

declare(strict_types=1);

use Maho\Data\Form\Condition;

class Mage_Eav_Block_Adminhtml_Attribute_Edit_Dependence extends Mage_Adminhtml_Block_Template
{
    #[\Override]
    protected $_template = 'eav/attribute/dependence.phtml';

    public function getAttribute(): Mage_Eav_Model_Entity_Attribute
    {
        return $this->getData('attribute');
    }

    /**
     * Return the fields that a row can name, each with Any Value as its first option. A field with a condition of its
     * own is left out.
     *
     * @return array<string, array{label: string, options: list<array{value: string, label: string}>}>
     */
    public function getSources(): array
    {
        $attribute = $this->getAttribute();
        $config = Mage::getSingleton('eav/config');
        $sources = [];
        foreach ($attribute->getDependencySources() as $code => $source) {
            $sourceAttribute = $config->getAttribute($attribute->getEntityType(), $code);
            if ($sourceAttribute instanceof Mage_Eav_Model_Entity_Attribute_Abstract && $sourceAttribute->getDependsOn() !== null) {
                continue;
            }
            $source['options'] = [['value' => Condition::ANY_VALUE, 'label' => Mage::helper('eav')->__('Any Value')], ...$source['options']];
            $sources[$code] = $source;
        }
        return $sources;
    }

    /**
     * Return the stored condition as one operator and a list of rows, or null when the condition has
     * a group inside a group, which this editor does not show.
     *
     * @return array{operator: string, rows: list<array{field: string, values: list<string>}>}|null
     */
    public function getEditableCondition(): ?array
    {
        $condition = $this->getAttribute()->getDependsOn();
        if ($condition === null) {
            return ['operator' => Condition::MODE_AND, 'rows' => []];
        }
        $operator = Condition::MODE_AND;
        if (count($condition) === 1 && Condition::isGroup(array_first($condition))) {
            $group = array_first($condition);
            $operator = $group['operator'];
            $condition = $group['condition'];
        }
        $rows = [];
        foreach ($condition as $field => $values) {
            if (!is_string($field) || Condition::isGroup($values)) {
                return null;
            }
            $rows[] = ['field' => $field, 'values' => array_values(array_map(strval(...), (array) $values))];
        }
        return ['operator' => $operator, 'rows' => $rows];
    }

    /**
     * @return array<string, string> the label of each operator
     */
    public function getOperators(): array
    {
        $helper = Mage::helper('eav');
        return [
            Condition::MODE_AND => $helper->__('Each row matches'),
            Condition::MODE_OR => $helper->__('At least one row matches'),
            Condition::MODE_NOT => $helper->__('No row matches'),
            Condition::MODE_XOR => $helper->__('Exactly one row matches'),
        ];
    }

    /**
     * Return the attributes whose condition reads this attribute. While they exist, it cannot get a condition.
     *
     * @return list<string>
     */
    public function getDependentCodes(): array
    {
        return $this->getAttribute()->getId() ? $this->getAttribute()->getDependentAttributeCodes() : [];
    }

    public function getConfigJson(): string
    {
        $helper = Mage::helper('eav');
        return Mage::helper('core')->jsonEncode([
            'sources' => $this->getSources(),
            'condition' => $this->getEditableCondition(),
            'labels' => [
                'field' => $helper->__('Field'),
                'values' => $helper->__('Values'),
                'delete' => $helper->__('Delete'),
                'deleteRow' => $helper->__('Delete row %s'),
            ],
        ]);
    }
}
