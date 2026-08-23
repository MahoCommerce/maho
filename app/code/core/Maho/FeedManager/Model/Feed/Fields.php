<?php

/**
 * The fields a feed exports, read from the definition the feed holds.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

class Maho_FeedManager_Model_Feed_Fields
{
    /** The value of one template parameter. A quote closes it, always. */
    private const VALUE = '"([^"]*)"';

    /** One field of an item template: {type="..." name="value" ...} */
    public const TEMPLATE_FIELD_PATTERN = '/\{(type=' . self::VALUE . '(?:\s+\w+=' . self::VALUE . ')*)\}/';

    /** One placeholder of the older item template syntax: {{name}} */
    public const TEMPLATE_PLACEHOLDER_PATTERN = '/\{\{([^}]+)\}\}/';

    /**
     * The parameters of one item template field, as name to value.
     *
     * Reads the body TEMPLATE_FIELD_PATTERN captures, with the same value shape, so the
     * two cannot read the same field differently.
     *
     * @return array<string, string>
     */
    public static function parseField(string $body): array
    {
        preg_match_all('/(\w+)=' . self::VALUE . '/', $body, $matches, PREG_SET_ORDER);

        $params = [];
        foreach ($matches as $match) {
            $params[$match[1]] = $match[2];
        }

        return $params;
    }

    /**
     * The fields this feed exports: an element tag, a property name, or a column name.
     *
     * @return array<int, string>
     */
    public static function exported(
        Maho_FeedManager_Model_Feed $feed,
        ?Maho_FeedManager_Model_Mapper $mapper = null,
    ): array {
        $format = (string) $feed->getFileFormat();

        if ($format === 'xml' && $feed->getXmlStructure()) {
            return self::structureTags(self::decode((string) $feed->getXmlStructure()));
        }

        if ($format === 'xml' && $feed->getXmlItemTemplate()) {
            return self::templateTags((string) $feed->getXmlItemTemplate());
        }

        if ($format === 'json' && $feed->getJsonStructure()) {
            $structure = self::decode((string) $feed->getJsonStructure());
            if ($structure !== []) {
                return self::structureProperties($structure);
            }
        }

        $mapper ??= (new Maho_FeedManager_Model_Mapper($feed))->applyBuilderDefinitions();

        return $mapper->getMappedFieldNames();
    }

    /**
     * The weight fields this feed exports, as platform field name to platform label.
     *
     * @return array<string, string>
     */
    public static function weightFields(
        Maho_FeedManager_Model_Feed $feed,
        ?Maho_FeedManager_Model_Platform_AdapterInterface $platform,
        ?Maho_FeedManager_Model_Mapper $mapper = null,
    ): array {
        if ($platform === null) {
            return [];
        }

        $attributes = $platform->getAllAttributes();
        $labels = [];
        foreach (self::exported($feed, $mapper) as $name) {
            $field = Maho_FeedManager_Model_Mapper::toPlatformField($name);
            if (Maho_FeedManager_Model_Mapper::unitTypeOf($platform, $field) !== Maho_FeedManager_Model_Mapper::UNIT_TYPE_WEIGHT) {
                continue;
            }
            $labels[$field] = (string) ($attributes[$field]['label'] ?? $field);
        }

        return $labels;
    }

    /**
     * The element a placeholder is the whole content of, or '' when there is none.
     *
     * A placeholder that shares its element with other text is not the value of that
     * field on its own, so this method reports no element for it.
     */
    public static function enclosingTag(string $template, int $offset, int $length): string
    {
        $before = substr($template, 0, $offset);
        if (!preg_match('/<([a-zA-Z_][\w.-]*(?::[\w.-]+)?)(?:\s[^>]*)?>\s*(?:<!\[CDATA\[\s*)?$/', $before, $open)) {
            return '';
        }

        $after = substr($template, $offset + $length);
        if (!preg_match('#^\s*(?:\]\]>\s*)?</' . preg_quote($open[1], '#') . '>#', $after)) {
            return '';
        }

        return $open[1];
    }

    /**
     * The elements an item template fills with one product value.
     *
     * @return array<int, string>
     */
    protected static function templateTags(string $template): array
    {
        $tags = [];

        foreach ([self::TEMPLATE_FIELD_PATTERN, self::TEMPLATE_PLACEHOLDER_PATTERN] as $pattern) {
            preg_match_all($pattern, $template, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
            foreach ($matches as $match) {
                $tag = self::enclosingTag($template, $match[0][1], strlen($match[0][0]));
                if ($tag !== '') {
                    $tags[] = $tag;
                }
            }
        }

        return $tags;
    }

    /**
     * The element tags an XML structure fills with one product value.
     *
     * @param array<int|string, mixed> $structure
     * @return array<int, string>
     */
    protected static function structureTags(array $structure): array
    {
        $tags = [];

        foreach ($structure as $config) {
            if (!is_array($config)) {
                continue;
            }

            if (!empty($config['children']) && is_array($config['children'])) {
                $tags = array_merge($tags, self::structureTags($config['children']));
                continue;
            }

            $tag = (string) ($config['tag'] ?? '');
            if ($tag !== '' && Maho_FeedManager_Model_Mapper::writesValue($config)) {
                $tags[] = $tag;
            }
        }

        return $tags;
    }

    /**
     * The property names a JSON structure fills with one product value.
     *
     * An array property takes another path, which applies no measure, so it stays out.
     *
     * @param array<string, mixed> $structure
     * @return array<int, string>
     */
    protected static function structureProperties(array $structure): array
    {
        $names = [];

        foreach ($structure as $key => $config) {
            if (!is_array($config)) {
                continue;
            }

            $type = (string) ($config['type'] ?? 'string');

            if ($type === 'object' && is_array($config['properties'] ?? null)) {
                $names = array_merge($names, self::structureProperties($config['properties']));
                continue;
            }

            if ($type === 'array' || !Maho_FeedManager_Model_Mapper::writesValue($config)) {
                continue;
            }

            $names[] = (string) $key;
        }

        return $names;
    }

    /**
     * A builder definition, or an empty array when it is not readable.
     *
     * A definition the admin form cannot read must not stop the form. The generator
     * rejects the same definition on its own.
     *
     * @return array<int|string, mixed>
     */
    protected static function decode(string $json): array
    {
        try {
            $decoded = Mage::helper('core')->jsonDecode($json);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }
}
