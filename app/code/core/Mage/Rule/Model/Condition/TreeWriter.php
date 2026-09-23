<?php

/**
 * Convert condition trees between the rule models and the wire format of a metadata document.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Rule
 */

declare(strict_types=1);

class Mage_Rule_Model_Condition_TreeWriter
{
    /**
     * @param array{roots: array<string, string>, types: array<string, array<string, mixed>>} $document
     */
    public function __construct(protected array $document) {}

    /**
     * Return the tree $treeKey of $rule in the wire format.
     *
     * @return array<string, mixed>
     */
    public function readTree(Mage_Rule_Model_Abstract $rule, string $treeKey): array
    {
        $root = $treeKey === 'actions' ? $rule->getActions() : $rule->getConditions();
        if (!$root instanceof Mage_Rule_Model_Condition_Abstract) {
            throw new InvalidArgumentException("The {$treeKey} of this rule are not a condition tree");
        }
        return $this->toWire($root);
    }

    /**
     * Convert a condition and its children to the wire format. The read-only key "label" describes each condition.
     *
     * @return array<string, mixed>
     */
    public function toWire(Mage_Rule_Model_Condition_Abstract $condition): array
    {
        $type = (string) $condition->getType();
        $description = $this->document['types'][$type] ?? [];
        $wire = ['type' => $type];

        if ($condition instanceof Mage_Rule_Model_Condition_Combine) {
            $wire['aggregator'] = (string) $condition->getAggregator();
            if (!empty($description['attributes'])) {
                $wire['attribute'] = (string) $condition->getAttribute();
                $wire['operator'] = (string) $condition->getOperator();
                $wire['value'] = $this->toWireValue($condition->getData('value'), $condition->isArrayOperatorType());
            } else {
                $wire['value'] = $this->toWireCombineValue($condition->getData('value'), $description);
            }
            $wire['conditions'] = [];
            foreach ($condition->getConditions() as $child) {
                if ($child instanceof Mage_Rule_Model_Condition_Abstract) {
                    $wire['conditions'][] = $this->toWire($child);
                }
            }
            $wire['label'] = $this->combineLabel($condition, $description);
            return $wire;
        }

        $wire['attribute'] = (string) $condition->getAttribute();
        $wire['operator'] = (string) $condition->getOperator();
        $wire['value'] = empty($description['valueless'])
            ? $this->toWireValue($condition->getData('value'), $condition->isArrayOperatorType())
            : null;
        $wire['label'] = $this->leafLabel($condition);
        return $wire;
    }

    /**
     * Replace the tree $treeKey of $rule with $cleanTree. Give only a tree that TreeValidator::validate() returned.
     *
     * @param array<string, mixed> $cleanTree
     */
    public function replaceTree(Mage_Rule_Model_Abstract $rule, string $treeKey, array $cleanTree): void
    {
        $root = $treeKey === 'actions' ? $rule->getActionsInstance() : $rule->getConditionsInstance();
        if (!$root instanceof Mage_Rule_Model_Condition_Combine) {
            throw new InvalidArgumentException("The {$treeKey} of this rule are not a condition tree");
        }
        $root->setRule($rule)->setId('1')->setPrefix($treeKey);
        $root->loadArray($cleanTree);

        // loadArray() logs and skips a condition that it cannot load, so compare the counts
        $expected = self::countConditions($cleanTree);
        $loaded = self::countConditions($root->asArray());
        if ($expected !== $loaded) {
            $message = "The {$treeKey} tree has {$expected} conditions, but only {$loaded} conditions loaded";
            Mage::log($message, Mage::LOG_ERROR);
            throw new RuntimeException($message);
        }

        // Without the unset, getConditions() and getActions() load the stored tree into the new root
        if ($treeKey === 'actions') {
            $rule->unsActionsSerialized();
            $rule->setActions($root);
        } else {
            $rule->unsConditionsSerialized();
            $rule->setConditions($root);
        }
    }

    /**
     * Count the conditions of a tree in the wire format or in the format of asArray(), including the root.
     *
     * @param array<string, mixed> $tree
     */
    public static function countConditions(array $tree): int
    {
        $count = 1;
        foreach ($tree['conditions'] ?? [] as $child) {
            if (is_array($child)) {
                $count += self::countConditions($child);
            }
        }
        return $count;
    }

    /**
     * @return string|list<string>|null
     */
    protected function toWireValue(mixed $value, bool $isList): string|array|null
    {
        if ($value === null || $value === false) {
            return $isList ? [] : null;
        }
        if ($isList) {
            $items = is_array($value)
                ? $value
                : preg_split('/\s*[,;]\s*/', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY);
            return array_values(array_map($this->toText(...), (array) $items));
        }
        return is_array($value) ? implode(',', array_map($this->toText(...), $value)) : $this->toText($value);
    }

    protected function toText(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * @param array<string, mixed> $description
     */
    protected function toWireCombineValue(mixed $value, array $description): bool|string|null
    {
        $options = array_column($description['value']['options'] ?? [], 'value');
        if ($options !== [] && array_filter($options, is_string(...)) === $options) {
            return is_scalar($value) ? (string) $value : null;
        }
        return (bool) $value;
    }

    /**
     * @param array<string, mixed> $description
     */
    protected function combineLabel(Mage_Rule_Model_Condition_Combine $combine, array $description): ?string
    {
        try {
            $sentence = $description['sentence'] ?? null;
            if (!is_string($sentence)) {
                return $combine->asString();
            }
            return strtr($sentence, [
                '{aggregator}' => (string) $combine->getAggregatorName(),
                '{value}' => $combine->getValueName(),
                '{attribute}' => $combine->getAttributeName(),
                '{operator}' => $combine->getOperatorName(),
            ]);
        } catch (\Throwable $e) {
            Mage::logException($e);
            return null;
        }
    }

    protected function leafLabel(Mage_Rule_Model_Condition_Abstract $condition): ?string
    {
        try {
            return trim($condition->asString());
        } catch (\Throwable $e) {
            Mage::logException($e);
            return null;
        }
    }
}
