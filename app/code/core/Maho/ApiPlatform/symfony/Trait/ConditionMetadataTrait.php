<?php

/**
 * Give an API class the condition metadata document of a rule type, with labels in the admin locale of the token.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Trait;

trait ConditionMetadataTrait
{
    use AdminLocaleTrait;
    use CacheTrait;

    /**
     * Some value options come from the database without a cache tag, so the document expires after this time.
     */
    private int $conditionMetadataLifetime = 900;

    abstract protected function conditionMetadataModel(): \Mage_Rule_Model_Condition_Metadata;

    /**
     * Return the cache key of the document. The locale is added to it.
     */
    abstract protected function conditionMetadataCacheKey(): string;

    /**
     * Return the parts that the rule type adds to the document, such as the options of the rule fields.
     *
     * @return array<string, mixed>
     */
    protected function extraConditionMetadata(): array
    {
        return [];
    }

    /**
     * Return the metadata document of $locale without the scope part: version, locale, roots, types and the extra parts.
     *
     * @return array<string, mixed>
     */
    protected function conditionMetadata(string $locale): array
    {
        return $this->remember(
            $this->conditionMetadataCacheKey() . '_' . $locale,
            [\Mage_Core_Model_Config::CACHE_TAG, \Mage_Eav_Model_Entity_Attribute::CACHE_TAG],
            $this->conditionMetadataLifetime,
            fn(): array => $this->buildConditionMetadata($locale),
            fn(array $document): array => $document,
            fn(array $document): array => $document,
        );
    }

    /**
     * Check the trees $keys of $body and put them into $rule. The stored tree of an update lets old values pass.
     * A null tree resets it to an empty tree. The trees change only when $errors is empty.
     *
     * @param array<string, mixed> $body
     * @param list<string> $keys
     */
    protected function readRuleTrees(\Mage_Rule_Model_Abstract $rule, array $body, array $keys, string $locale, \Mage_Core_Exception_Input $errors): void
    {
        $keys = array_values(array_filter($keys, fn(string $key) => array_key_exists($key, $body)));
        if ($keys === []) {
            return;
        }

        $document = $this->conditionMetadata($locale);
        $validator = new \Mage_Rule_Model_Condition_TreeValidator($this->conditionMetadataModel(), $document);
        $writer = new \Mage_Rule_Model_Condition_TreeWriter($document);

        $cleanTrees = [];
        foreach ($keys as $key) {
            $tree = $body[$key] ?? ['type' => $document['roots'][$key], 'aggregator' => 'all', 'value' => true, 'conditions' => []];
            $result = $validator->validate($key, $tree, $rule->getId() ? $writer->readTree($rule, $key) : null);
            foreach ($result['errors'] as $error) {
                $errors->addError($error['field'], $error['message']);
            }
            if ($result['tree'] !== null) {
                $cleanTrees[$key] = $result['tree'];
            }
        }

        if ($errors->getErrors() === []) {
            foreach ($cleanTrees as $key => $cleanTree) {
                $writer->replaceTree($rule, $key, $cleanTree);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildConditionMetadata(string $locale): array
    {
        $document = \Mage_Rule_Model_Condition_Metadata::runInLocale(
            $locale,
            fn(): array => $this->conditionMetadataModel()->build() + $this->extraConditionMetadata(),
        );

        return ['version' => hash('sha256', (string) json_encode($document)), 'locale' => $locale] + $document;
    }
}
