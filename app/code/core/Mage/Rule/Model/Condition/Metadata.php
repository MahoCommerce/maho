<?php

/**
 * Describe the condition types of a rule, so that a client can build and read condition trees.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Rule
 */

declare(strict_types=1);

class Mage_Rule_Model_Condition_Metadata
{
    /**
     * A list with more options than this gives only its count, and the client loads it page by page.
     */
    public const DEFAULT_INLINE_OPTION_LIMIT = 100;

    /**
     * The chooser that a client opens to select the value, by product attribute code.
     */
    protected const CHOOSERS = ['sku' => 'product', 'category_ids' => 'category'];

    /**
     * @param array<string, string> $roots The root condition type of each tree, by tree key
     * @param string $eventPrefix The event "<prefix>_condition_metadata_prepare_after" lets modules change the document
     */
    public function __construct(
        protected Mage_Rule_Model_Abstract $rule,
        protected array $roots,
        protected string $eventPrefix,
        protected int $inlineOptionLimit = self::DEFAULT_INLINE_OPTION_LIMIT,
    ) {}

    /**
     * Run $callback in the admin store, with the translations of $locale and with translated condition labels.
     * Restore the previous locale and label flag after the call.
     *
     * @template T
     * @param \Closure(): T $callback
     * @return T
     */
    public static function runInLocale(string $locale, \Closure $callback): mixed
    {
        $app = Mage::app();
        $appLocale = $app->getLocale();
        $translator = $app->getTranslator();
        $previousLocale = $appLocale->getLocaleCode();
        $previousArea = $translator->getConfig(Mage_Core_Model_Translate::CONFIG_KEY_AREA);
        $previousTranslatorLocale = $translator->getLocale();
        $previousFlag = Mage_Rule_Model_Condition_Abstract::getTranslateLabels();
        $switch = $previousArea !== Mage_Core_Model_App_Area::AREA_ADMINHTML || $previousTranslatorLocale !== $locale;

        $appLocale->setLocaleCode($locale);
        if ($switch) {
            // The translator keeps its first cache key, so a translator that loaded data before must reload
            $translator->setLocale($locale)->init(Mage_Core_Model_App_Area::AREA_ADMINHTML, $previousArea !== null);
        }
        Mage_Rule_Model_Condition_Abstract::setTranslateLabels(true);

        try {
            return $app->withStore(Mage_Core_Model_App::ADMIN_STORE_ID, $callback);
        } finally {
            Mage_Rule_Model_Condition_Abstract::setTranslateLabels($previousFlag);
            $appLocale->setLocaleCode($previousLocale);
            if ($switch) {
                $translator->setLocale($previousTranslatorLocale);
                if (is_string($previousArea)) {
                    $translator->init($previousArea, true);
                }
            }
        }
    }

    /**
     * @return array<string, string>
     */
    public function getRoots(): array
    {
        return $this->roots;
    }

    /**
     * Build the document of all condition types that the root types can reach.
     * Call it inside runInLocale() to get the labels of a locale.
     *
     * @return array{roots: array<string, string>, types: array<string, array<string, mixed>>}
     */
    public function build(): array
    {
        $types = [];
        $labels = [];
        $references = [];
        $leafTypes = [];
        $queue = array_values($this->roots);
        $visited = [];

        while ($queue !== []) {
            $type = array_shift($queue);
            if (isset($visited[$type])) {
                continue;
            }
            $visited[$type] = true;

            $condition = $this->createCondition($type);
            if ($condition === null) {
                continue;
            }
            if (!$condition instanceof Mage_Rule_Model_Condition_Combine) {
                $leafTypes[$type] = true;
                continue;
            }

            $children = $this->readChildOptions($condition);
            foreach ($this->childEntries($children) as $entry) {
                $childType = $entry['type'];
                $queue[] = $childType;
                $references[$childType] ??= ['all' => false, 'attributes' => []];
                if (isset($entry['attribute'])) {
                    $references[$childType]['attributes'][$entry['attribute']] = $entry['label'];
                } else {
                    $references[$childType]['all'] = true;
                    $labels[$childType] ??= $entry['label'];
                }
            }
            $types[$type] = $this->describeCombine($type, $condition, $children);
        }

        foreach (array_keys($leafTypes) as $type) {
            $types[$type] = $this->describeLeaf($type, $references[$type] ?? ['all' => true, 'attributes' => []]);
        }

        foreach ($types as $type => &$description) {
            if (isset($labels[$type])) {
                $description = ['kind' => $description['kind'], 'label' => $labels[$type]] + $description;
            }
            if (isset($description['children'])) {
                $description['children'] = $this->removeUnknownChildren($description['children'], $types);
            }
        }
        unset($description);

        $transport = new \Maho\DataObject(['roots' => $this->roots, 'types' => $types]);
        Mage::dispatchEvent($this->eventPrefix . '_condition_metadata_prepare_after', [
            'transport' => $transport,
            'rule' => $this->rule,
        ]);

        $types = [];
        foreach ((array) $transport->getData('types') as $type => $description) {
            // An observer can add types, so check the class of each one again
            if (is_array($description) && $this->createCondition((string) $type) !== null) {
                $types[(string) $type] = $description;
            }
        }

        return ['roots' => $this->roots, 'types' => $types];
    }

    /**
     * Create a condition of $type for this rule. Give $type only from a metadata document or from the code of a condition.
     * Return null and log the problem when $type does not give a condition.
     */
    public function createCondition(string $type, ?string $attribute = null): ?Mage_Rule_Model_Condition_Abstract
    {
        try {
            $condition = Mage::getModel($type);
        } catch (\Throwable $e) {
            Mage::logException($e);
            return null;
        }
        if (!$condition instanceof Mage_Rule_Model_Condition_Abstract) {
            Mage::log("Rule condition metadata skips {$type}: it is not a rule condition", Mage::LOG_WARNING);
            return null;
        }

        $condition->setRule($this->rule)->setPrefix('conditions')->setId('1');
        if ($attribute !== null) {
            $condition->setAttribute($attribute);
        }
        return $condition;
    }

    /**
     * Return the value options of $attribute of $type, as lists of {value, label} and groups {label, options}.
     *
     * @return list<array<string, mixed>>
     */
    public function getValueOptions(string $type, string $attribute): array
    {
        $condition = $this->createCondition($type, $attribute);
        return $condition === null ? [] : $this->normalizeOptions($condition->getValueSelectOptions());
    }

    /**
     * Put the options of all groups into one list. Each option of a group gets the key "group" with the group label.
     *
     * @param list<array<string, mixed>> $options
     * @return list<array{value: string, label: string, group?: string}>
     */
    public static function flattenOptions(array $options): array
    {
        $flat = [];
        foreach ($options as $option) {
            if (isset($option['options'])) {
                foreach ($option['options'] as $groupOption) {
                    $flat[] = ['value' => $groupOption['value'], 'label' => $groupOption['label'], 'group' => $option['label']];
                }
            } else {
                $flat[] = ['value' => $option['value'], 'label' => $option['label']];
            }
        }
        return $flat;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function readChildOptions(Mage_Rule_Model_Condition_Combine $combine): array
    {
        $children = [];
        foreach ((array) $combine->getNewChildSelectOptions() as $option) {
            if (!is_array($option)) {
                continue;
            }
            $label = (string) ($option['label'] ?? '');
            $value = $option['value'] ?? '';
            if (is_array($value)) {
                $group = [];
                foreach ($value as $groupOption) {
                    $entry = is_array($groupOption) ? $this->parseChildOption($groupOption['value'] ?? '', (string) ($groupOption['label'] ?? '')) : null;
                    if ($entry !== null) {
                        $group[] = $entry;
                    }
                }
                if ($group !== []) {
                    $children[] = ['label' => $label, 'options' => $group];
                }
                continue;
            }
            $entry = $this->parseChildOption($value, $label);
            if ($entry !== null) {
                $children[] = $entry;
            }
        }
        return $children;
    }

    /**
     * A child option value is a condition type, or "<type>|<attribute>" for one attribute of the type.
     *
     * @return array{type: string, attribute?: string, label: string}|null
     */
    protected function parseChildOption(mixed $value, string $label): ?array
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        $parts = explode('|', $value, 2);
        $entry = ['type' => $parts[0]];
        if (isset($parts[1]) && $parts[1] !== '') {
            $entry['attribute'] = $parts[1];
        }
        $entry['label'] = $label;
        return $entry;
    }

    /**
     * @param list<array<string, mixed>> $children
     * @return list<array{type: string, attribute?: string, label: string}>
     */
    protected function childEntries(array $children): array
    {
        $entries = [];
        foreach ($children as $child) {
            if (isset($child['options'])) {
                array_push($entries, ...$child['options']);
            } else {
                $entries[] = $child;
            }
        }
        return $entries;
    }

    /**
     * @param list<array<string, mixed>> $children
     * @param array<string, array<string, mixed>> $types
     * @return list<array<string, mixed>>
     */
    protected function removeUnknownChildren(array $children, array $types): array
    {
        $result = [];
        foreach ($children as $child) {
            if (isset($child['options'])) {
                $child['options'] = array_values(array_filter($child['options'], fn(array $entry) => isset($types[$entry['type']])));
                if ($child['options'] !== []) {
                    $result[] = $child;
                }
            } elseif (isset($types[$child['type']])) {
                $result[] = $child;
            }
        }
        return $result;
    }

    /**
     * @param list<array<string, mixed>> $children
     * @return array<string, mixed>
     */
    protected function describeCombine(string $type, Mage_Rule_Model_Condition_Combine $combine, array $children): array
    {
        $description = [
            'kind' => 'combine',
            'sentence' => $this->getSentence($combine),
            'aggregator' => [
                'default' => (string) $combine->getAggregator(),
                'options' => $this->normalizeOptions($combine->getAggregatorSelectOptions()),
            ],
            'value' => $this->describeCombineValue($combine),
        ];

        // A combine with its own attributes compares a total of the matching items, as the products subselection does
        $attributeCodes = array_map(strval(...), array_keys($combine->getAttributeOption() ?? []));
        if ($attributeCodes !== []) {
            $description['attributes'] = [];
            foreach ($attributeCodes as $code) {
                $description['attributes'][] = $this->describeAttribute($type, $code, false, 'numeric');
            }
        }

        $description['children'] = $children;
        return $description;
    }

    /**
     * @return array{default: bool|string|null, options: list<array<string, mixed>>}
     */
    protected function describeCombineValue(Mage_Rule_Model_Condition_Combine $combine): array
    {
        $options = [];
        $boolean = true;
        foreach ((array) $combine->getValueSelectOptions() as $option) {
            if (!is_array($option) || !isset($option['value']) || is_array($option['value'])) {
                continue;
            }
            $options[] = ['value' => $option['value'], 'label' => (string) ($option['label'] ?? '')];
            $boolean = $boolean && in_array((string) $option['value'], ['0', '1'], true);
        }
        if ($options === []) {
            return ['default' => null, 'options' => []];
        }

        $default = $combine->getData('value');
        foreach ($options as &$option) {
            $option['value'] = $boolean ? (bool) $option['value'] : (string) $option['value'];
        }
        unset($option);

        return ['default' => $boolean ? (bool) $default : (string) $default, 'options' => $options];
    }

    /**
     * @param array{all: bool, attributes: array<string, string>} $references
     * @return array<string, mixed>
     */
    protected function describeLeaf(string $type, array $references): array
    {
        $probe = $this->createCondition($type);
        $codes = array_keys($references['attributes']);
        if ($references['all'] && $probe !== null) {
            $codes = array_unique([...$codes, ...array_map(strval(...), array_keys($probe->getAttributeOption() ?? []))]);
        }

        $valueless = $probe !== null && $this->isValueless($probe, $codes);
        $description = ['kind' => 'leaf'];
        if ($valueless) {
            $description['valueless'] = true;
        }
        $description['attributes'] = [];
        foreach ($codes as $code) {
            $description['attributes'][] = $this->describeAttribute($type, (string) $code, $valueless, null, $references['attributes'][$code] ?? null);
        }
        return $description;
    }

    /**
     * A condition takes no value when none of its operators compares a value, as "is assigned" does.
     *
     * @param list<string|int> $codes
     */
    protected function isValueless(Mage_Rule_Model_Condition_Abstract $probe, array $codes): bool
    {
        $comparisons = array_map(strval(...), array_keys($probe->getDefaultOperatorOptions()));
        if ($codes !== []) {
            $probe->setAttribute((string) reset($codes));
        }
        $operators = array_map(fn(array $option) => (string) $option['value'], $probe->getOperatorSelectOptions());
        return $operators !== [] && array_intersect($operators, $comparisons) === [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function describeAttribute(string $type, string $code, bool $valueless, ?string $inputType = null, ?string $label = null): array
    {
        $condition = $this->createCondition($type, $code);
        if ($condition === null) {
            return ['code' => $code, 'label' => $label ?? $code, 'inputType' => 'string', 'operators' => [], 'options' => null];
        }

        $attributeLabels = $condition->getAttributeOption() ?? [];
        $inputType ??= (string) $condition->getInputType();
        $operators = [];
        foreach ($condition->getOperatorSelectOptions() as $option) {
            $condition->setOperator((string) $option['value']);
            $operators[] = [
                'value' => (string) $option['value'],
                'label' => (string) $option['label'],
                'valueIsList' => !$valueless && $condition->isArrayOperatorType(),
            ];
        }

        $description = [
            'code' => $code,
            'label' => (string) ($attributeLabels[$code] ?? $label ?? $code),
            'inputType' => $inputType,
            'valueElement' => (string) $condition->getValueElementType(),
            'operators' => $operators,
            'options' => $valueless || $condition instanceof Mage_Rule_Model_Condition_Combine
                ? null
                : $this->describeOptions($condition),
        ];
        if (!$valueless && $condition->getExplicitApply()) {
            $description['explicitApply'] = true;
        }
        if (!$valueless && $condition instanceof Mage_Rule_Model_Condition_Product_Abstract && isset(self::CHOOSERS[$code])) {
            $description['chooser'] = self::CHOOSERS[$code];
        }
        if (!$valueless && $inputType === 'string' && $this->hasNumericValue($condition, $code)) {
            $description['numericHint'] = true;
        }
        return $description;
    }

    /**
     * Tell a client that a text value holds a number, when the attribute stores a number.
     */
    protected function hasNumericValue(Mage_Rule_Model_Condition_Abstract $condition, string $code): bool
    {
        if (!$condition instanceof Mage_Rule_Model_Condition_Product_Abstract) {
            return false;
        }
        $attribute = $condition->getAttributeObject();
        return $attribute instanceof Mage_Eav_Model_Entity_Attribute_Abstract
            && in_array($attribute->getBackendType(), ['decimal', 'int'], true)
            && !$attribute->usesSource();
    }

    /**
     * @return array{inline: list<array<string, mixed>>|null, count: int, paged: bool}|null
     */
    protected function describeOptions(Mage_Rule_Model_Condition_Abstract $condition): ?array
    {
        $options = $this->normalizeOptions($condition->getValueSelectOptions());
        if ($options === []) {
            return null;
        }
        $count = count(self::flattenOptions($options));
        if ($count > $this->inlineOptionLimit) {
            return ['inline' => null, 'count' => $count, 'paged' => true];
        }
        return ['inline' => $options, 'count' => $count, 'paged' => false];
    }

    /**
     * Convert Maho select options to {value, label} and groups {label, options}. Remove options without a value.
     *
     * @return list<array<string, mixed>>
     */
    protected function normalizeOptions(mixed $options): array
    {
        $result = [];
        foreach ((array) $options as $option) {
            if (!is_array($option) || !array_key_exists('value', $option)) {
                continue;
            }
            $label = is_scalar($option['label'] ?? null) ? trim((string) $option['label']) : '';
            if (is_array($option['value'])) {
                $group = $this->normalizeOptions($option['value']);
                if ($group !== []) {
                    $result[] = ['label' => $label, 'options' => $group];
                }
                continue;
            }
            if (!is_scalar($option['value']) || (string) $option['value'] === '') {
                continue;
            }
            $result[] = ['value' => (string) $option['value'], 'label' => $label];
        }
        return $result;
    }

    /**
     * Return the sentence of a combine, with the placeholders {aggregator}, {value}, {attribute} and {operator}.
     */
    protected function getSentence(Mage_Rule_Model_Condition_Combine $combine): string
    {
        return Mage::helper('rule')->__('If %s of these conditions are %s:', '{aggregator}', '{value}');
    }
}
