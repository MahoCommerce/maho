<?php

/**
 * Check a condition tree from an untrusted source against a metadata document.
 * Only the clean tree that validate() returns may go to loadArray(), because loadArray() creates a model for each type.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Rule
 */

declare(strict_types=1);

class Mage_Rule_Model_Condition_TreeValidator
{
    public const MAX_DEPTH = 10;
    public const MAX_NODES = 250;
    public const MAX_LIST_ITEMS = 1000;
    public const MAX_TEXT_LENGTH = 255;

    /**
     * "label" is read-only. A client can send back a tree that it read, so the validator ignores it.
     */
    protected const NODE_KEYS = ['type', 'attribute', 'operator', 'value', 'aggregator', 'conditions', 'label'];

    /**
     * Input types whose list values Maho stores as arrays. Maho stores other lists as text with commas.
     */
    protected const ARRAY_INPUT_TYPES = ['multiselect', 'grid'];

    /** @var list<array{field: string, message: string}> */
    protected array $errors = [];

    protected int $nodeCount = 0;

    /** @var array<string, true> Fingerprints of the conditions in the stored tree */
    protected array $storedConditions = [];

    /** @var array<string, int> Category IDs to look up, by field path */
    protected array $categoryIds = [];

    /** @var array<string, array<string, true>> Option values, by type and attribute */
    protected array $optionValues = [];

    /**
     * @param array<string, mixed> $document A metadata document: Mage_Rule_Model_Condition_Metadata::build() gives its keys roots and types
     */
    public function __construct(
        protected Mage_Rule_Model_Condition_Metadata $metadata,
        protected array $document,
    ) {}

    /**
     * Check $tree and convert it to the array that loadArray() reads.
     *
     * A condition that is equal to a condition of $storedTree skips the check of its value content,
     * so that a tree with old values can still change in other places.
     *
     * @param string $treeKey The key of the tree in the metadata roots, and the start of each error field
     * @param array<string, mixed>|null $storedTree The stored tree, as TreeWriter::toWire() returns it
     * @return array{tree: array<string, mixed>|null, errors: list<array{field: string, message: string}>}
     */
    public function validate(string $treeKey, mixed $tree, ?array $storedTree = null): array
    {
        $this->errors = [];
        $this->nodeCount = 0;
        $this->categoryIds = [];
        $this->storedConditions = [];
        if ($storedTree !== null) {
            $this->collectStoredConditions($storedTree);
        }

        $rootType = $this->document['roots'][$treeKey] ?? null;
        if ($rootType === null) {
            $this->addError($treeKey, 'This rule has no tree with this name');
            return ['tree' => null, 'errors' => $this->errors];
        }

        $clean = $this->validateNode($tree, $treeKey, 1, [$rootType => null]);
        $this->checkCategories();

        return $this->errors === []
            ? ['tree' => $clean, 'errors' => []]
            : ['tree' => null, 'errors' => $this->errors];
    }

    /**
     * @param array<string, array<string, true>|null> $allowed Allowed types, with their allowed attributes or null for all
     * @return array<string, mixed>|null
     */
    protected function validateNode(mixed $node, string $path, int $depth, array $allowed): ?array
    {
        $this->nodeCount++;
        if ($this->nodeCount === self::MAX_NODES + 1) {
            $this->addError($path, sprintf('A tree can have no more than %d conditions', self::MAX_NODES));
        }
        if ($this->nodeCount > self::MAX_NODES) {
            return null;
        }

        if (!is_array($node) || ($node !== [] && array_is_list($node))) {
            $this->addError($path, 'A condition must be an object');
            return null;
        }
        foreach (array_keys($node) as $key) {
            if (!in_array($key, self::NODE_KEYS, true)) {
                $this->addError("{$path}.{$key}", 'Unknown key');
            }
        }

        $type = $node['type'] ?? null;
        if (!is_string($type) || $type === '') {
            $this->addError("{$path}.type", 'The type is required');
            return null;
        }
        $description = $this->document['types'][$type] ?? null;
        if (!is_array($description)) {
            $this->addError("{$path}.type", 'Unknown condition type: ' . $this->quote($type));
            return null;
        }
        if (!array_key_exists($type, $allowed)) {
            $this->addError("{$path}.type", 'The condition type ' . $this->quote($type) . ' is not allowed here');
            return null;
        }

        return ($description['kind'] ?? null) === 'combine'
            ? $this->validateCombine($node, $path, $depth, $type, $description)
            : $this->validateLeaf($node, $path, $type, $description, $allowed[$type]);
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, mixed> $description
     * @param array<string, true>|null $allowedAttributes
     * @return array<string, mixed>|null
     */
    protected function validateLeaf(array $node, string $path, string $type, array $description, ?array $allowedAttributes): ?array
    {
        $this->rejectKeys($node, $path, ['aggregator', 'conditions']);

        $attribute = $this->validateAttribute($node, $path, $description, $allowedAttributes);
        if ($attribute === null) {
            return null;
        }
        $operator = $this->validateOperator($node, $path, $attribute);
        if ($operator === null) {
            return null;
        }

        $clean = ['type' => $type, 'attribute' => $attribute['code'], 'operator' => $operator['value']];
        if (!empty($description['valueless'])) {
            if (isset($node['value']) && $node['value'] !== '' && $node['value'] !== []) {
                $this->addError("{$path}.value", 'This condition takes no value');
            }
            return $clean;
        }

        $value = $this->readValue($node, "{$path}.value", (bool) $operator['valueIsList']);
        if ($value === null) {
            return null;
        }
        if (!$this->isStoredCondition($type, $attribute['code'], $operator['value'], $value)) {
            $this->checkValueContent($value, "{$path}.value", $type, $attribute);
        }

        $clean['value'] = is_array($value) && !in_array($attribute['inputType'], self::ARRAY_INPUT_TYPES, true)
            ? implode(',', $value)
            : $value;
        return $clean;
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, mixed> $description
     * @return array<string, mixed>|null
     */
    protected function validateCombine(array $node, string $path, int $depth, string $type, array $description): ?array
    {
        $clean = ['type' => $type];
        $attributes = $description['attributes'] ?? [];

        if ($attributes === []) {
            $this->rejectKeys($node, $path, ['attribute', 'operator']);
            $clean['value'] = $this->readCombineValue($node, $path, $description);
        } else {
            $attribute = $this->validateAttribute($node, $path, $description, null);
            $operator = $attribute === null ? null : $this->validateOperator($node, $path, $attribute);
            if ($attribute === null || $operator === null) {
                return null;
            }
            $value = $this->readValue($node, "{$path}.value", (bool) $operator['valueIsList']);
            if ($value === null) {
                return null;
            }
            if (!$this->isStoredCondition($type, $attribute['code'], $operator['value'], $value)) {
                $this->checkValueContent($value, "{$path}.value", $type, $attribute);
            }
            $clean['attribute'] = $attribute['code'];
            $clean['operator'] = $operator['value'];
            $clean['value'] = is_array($value) ? implode(',', $value) : $value;
        }

        $aggregator = $node['aggregator'] ?? ($description['aggregator']['default'] ?? null);
        $aggregators = array_column($description['aggregator']['options'] ?? [], 'value');
        if (!is_string($aggregator) || !in_array($aggregator, $aggregators, true)) {
            $this->addError("{$path}.aggregator", 'The aggregator must be one of: ' . implode(', ', $aggregators));
        }
        $clean['aggregator'] = $aggregator;

        $children = $node['conditions'] ?? [];
        if (!is_array($children) || !array_is_list($children)) {
            $this->addError("{$path}.conditions", 'The conditions must be a list');
            $children = [];
        }
        if ($children !== [] && $depth >= self::MAX_DEPTH) {
            $this->addError("{$path}.conditions", sprintf('A tree can have no more than %d levels', self::MAX_DEPTH));
            $children = [];
        }

        $allowed = $this->allowedChildren($description);
        $clean['conditions'] = [];
        foreach ($children as $index => $child) {
            $cleanChild = $this->validateNode($child, "{$path}.conditions[{$index}]", $depth + 1, $allowed);
            if ($cleanChild !== null) {
                $clean['conditions'][] = $cleanChild;
            }
        }
        return $clean;
    }

    /**
     * Return the value of a combine as Maho stores it: "1" or "0" for a boolean value.
     *
     * @param array<string, mixed> $node
     * @param array<string, mixed> $description
     */
    protected function readCombineValue(array $node, string $path, array $description): ?string
    {
        $default = $description['value']['default'] ?? null;
        $value = array_key_exists('value', $node) ? $node['value'] : $default;
        $options = array_column($description['value']['options'] ?? [], 'value');
        $isBoolean = $options !== [] && array_filter($options, is_bool(...)) === $options;

        if ($isBoolean) {
            if ($value === 1 || $value === 0) {
                $value = (bool) $value;
            }
            if (!is_bool($value)) {
                $this->addError("{$path}.value", 'The value must be true or false');
                return null;
            }
            return $value ? '1' : '0';
        }

        if (!is_scalar($value) || is_bool($value)) {
            $this->addError("{$path}.value", 'The value must be a string');
            return null;
        }
        $value = (string) $value;
        if ($options !== [] && !in_array($value, array_map(strval(...), $options), true)) {
            $this->addError("{$path}.value", 'The value must be one of: ' . implode(', ', $options));
        }
        return $value;
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, mixed> $description
     * @param array<string, true>|null $allowedAttributes
     * @return array<string, mixed>|null The description of the attribute
     */
    protected function validateAttribute(array $node, string $path, array $description, ?array $allowedAttributes): ?array
    {
        $code = $node['attribute'] ?? null;
        if (!is_string($code) || $code === '') {
            $this->addError("{$path}.attribute", 'The attribute is required');
            return null;
        }
        foreach ($description['attributes'] ?? [] as $attribute) {
            if ($attribute['code'] === $code && ($allowedAttributes === null || isset($allowedAttributes[$code]))) {
                return $attribute;
            }
        }
        $this->addError("{$path}.attribute", 'The attribute ' . $this->quote($code) . ' is not allowed for this condition type');
        return null;
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, mixed> $attribute
     * @return array{value: string, label: string, valueIsList: bool}|null
     */
    protected function validateOperator(array $node, string $path, array $attribute): ?array
    {
        $operator = $node['operator'] ?? null;
        foreach ($attribute['operators'] ?? [] as $option) {
            if ($option['value'] === $operator) {
                return $option;
            }
        }
        $this->addError(
            "{$path}.operator",
            'The operator must be one of: ' . implode(', ', array_column($attribute['operators'] ?? [], 'value')),
        );
        return null;
    }

    /**
     * Check the shape of the value and return it as trimmed text, or as a list of trimmed texts.
     *
     * @param array<string, mixed> $node
     * @return string|list<string>|null
     */
    protected function readValue(array $node, string $path, bool $isList): string|array|null
    {
        $value = $node['value'] ?? null;
        if ($value === null) {
            $this->addError($path, 'The value is required');
            return null;
        }

        if ($isList) {
            if (!is_array($value) || !array_is_list($value)) {
                $this->addError($path, 'The value must be a list for this operator');
                return null;
            }
            if (count($value) > self::MAX_LIST_ITEMS) {
                $this->addError($path, sprintf('A list can have no more than %d values', self::MAX_LIST_ITEMS));
                return null;
            }
            $items = [];
            foreach ($value as $index => $item) {
                $text = $this->scalarToText($item);
                if ($text === null) {
                    $this->addError("{$path}[{$index}]", 'Each value must be a string or a number');
                    return null;
                }
                $items[] = $text;
            }
            return $items;
        }

        $text = $this->scalarToText($value);
        if ($text === null) {
            $this->addError($path, 'The value must be a string or a number for this operator');
        }
        return $text;
    }

    protected function scalarToText(mixed $value): ?string
    {
        if (is_string($value)) {
            return trim($value);
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value) && is_finite($value)) {
            $text = (string) $value;
            return str_contains($text, 'E') ? rtrim(rtrim(sprintf('%.10F', $value), '0'), '.') : $text;
        }
        return null;
    }

    /**
     * @param string|list<string> $value
     * @param array<string, mixed> $attribute
     */
    protected function checkValueContent(string|array $value, string $path, string $type, array $attribute): void
    {
        if ($value === [] || $value === '') {
            $this->addError($path, 'The value must not be empty');
            return;
        }

        $inputType = (string) ($attribute['inputType'] ?? 'string');
        foreach ((array) $value as $index => $item) {
            $itemPath = is_array($value) ? "{$path}[{$index}]" : $path;
            $message = match (true) {
                $item === '' => 'The value must not be empty',
                mb_strlen($item) > self::MAX_TEXT_LENGTH => sprintf('The value can have no more than %d characters', self::MAX_TEXT_LENGTH),
                $inputType === 'numeric' => preg_match('/^-?\d+(\.\d+)?$/', $item) ? null : 'The value must be a number',
                $inputType === 'date' => $this->isDate($item, 'Y-m-d') ? null : 'The value must be a date in the format YYYY-MM-DD',
                $inputType === 'datetime' => $this->isDate($item, 'Y-m-d H:i:s') || $this->isDate($item, 'Y-m-d')
                    ? null
                    : 'The value must be a date in the format YYYY-MM-DD or YYYY-MM-DD HH:MM:SS',
                $inputType === 'category' => $this->queueCategory($item, $itemPath),
                in_array($inputType, ['select', 'multiselect', 'boolean'], true) && ($attribute['options'] ?? null) !== null
                    => $this->isOption($type, $attribute, $item) ? null : 'The value ' . $this->quote($item) . ' is not an option of this attribute',
                default => $this->checkText($item),
            };
            if ($message !== null) {
                $this->addError($itemPath, $message);
            }
        }
    }

    protected function checkText(string $text): ?string
    {
        if (!mb_check_encoding($text, 'UTF-8') || preg_match('/[\x00-\x1F\x7F]/', $text)) {
            return 'The value must not contain control characters';
        }
        if (strip_tags($text) !== $text) {
            return 'The value must not contain HTML tags';
        }
        return null;
    }

    protected function isDate(string $text, string $format): bool
    {
        $date = DateTime::createFromFormat('!' . $format, $text);
        return $date !== false && $date->format($format) === $text;
    }

    protected function queueCategory(string $item, string $path): ?string
    {
        if (!ctype_digit($item) || (int) $item <= 0) {
            return 'The value must be a category ID';
        }
        $this->categoryIds[$path] = (int) $item;
        return null;
    }

    /**
     * Look up all queued category IDs with one query.
     */
    protected function checkCategories(): void
    {
        if ($this->categoryIds === []) {
            return;
        }
        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        $select = $adapter->select()
            ->from($resource->getTableName('catalog/category'), ['entity_id'])
            ->where('entity_id IN (?)', array_values(array_unique($this->categoryIds)));
        $existing = array_map(intval(...), $adapter->fetchCol($select));
        foreach ($this->categoryIds as $path => $id) {
            if (!in_array($id, $existing, true)) {
                $this->addError($path, "Category {$id} does not exist");
            }
        }
    }

    /**
     * @param array<string, mixed> $attribute
     */
    protected function isOption(string $type, array $attribute, string $value): bool
    {
        $key = $type . '|' . $attribute['code'];
        if (!isset($this->optionValues[$key])) {
            $options = $attribute['options']['inline'] ?? null;
            $options ??= $this->metadata->getValueOptions($type, $attribute['code']);
            $values = array_map(strval(...), array_column(Mage_Rule_Model_Condition_Metadata::flattenOptions($options), 'value'));
            $this->optionValues[$key] = array_fill_keys($values, true);
        }
        return isset($this->optionValues[$key][$value]);
    }

    /**
     * @param array<string, mixed> $description
     * @return array<string, array<string, true>|null>
     */
    protected function allowedChildren(array $description): array
    {
        $allowed = [];
        foreach ($description['children'] ?? [] as $child) {
            foreach ($child['options'] ?? [$child] as $entry) {
                $type = $entry['type'];
                if (!isset($entry['attribute'])) {
                    $allowed[$type] = null;
                } elseif (!array_key_exists((string) $type, $allowed) || $allowed[$type] !== null) {
                    $allowed[$type][$entry['attribute']] = true;
                }
            }
        }
        return $allowed;
    }

    /**
     * @param array<string, mixed> $node
     * @param list<string> $keys
     */
    protected function rejectKeys(array $node, string $path, array $keys): void
    {
        foreach ($keys as $key) {
            if (isset($node[$key])) {
                $this->addError("{$path}.{$key}", 'This key does not apply to this condition type');
            }
        }
    }

    /**
     * @param array<string, mixed> $tree
     */
    protected function collectStoredConditions(array $tree): void
    {
        if (isset($tree['attribute'], $tree['operator']) && array_key_exists('value', $tree)) {
            $this->storedConditions[$this->fingerprint((string) $tree['type'], (string) $tree['attribute'], (string) $tree['operator'], $tree['value'])] = true;
        }
        foreach ($tree['conditions'] ?? [] as $child) {
            if (is_array($child)) {
                $this->collectStoredConditions($child);
            }
        }
    }

    /**
     * @param string|list<string> $value
     */
    protected function isStoredCondition(string $type, string $attribute, string $operator, string|array $value): bool
    {
        return isset($this->storedConditions[$this->fingerprint($type, $attribute, $operator, $value)]);
    }

    protected function fingerprint(string $type, string $attribute, string $operator, mixed $value): string
    {
        return (string) json_encode([$type, $attribute, $operator, $value]);
    }

    protected function quote(string $text): string
    {
        $text = mb_scrub($text, 'UTF-8');
        return '"' . (mb_strlen($text) > 64 ? mb_substr($text, 0, 64) . '...' : $text) . '"';
    }

    protected function addError(string $field, string $message): void
    {
        $this->errors[] = ['field' => $field, 'message' => $message];
    }
}
