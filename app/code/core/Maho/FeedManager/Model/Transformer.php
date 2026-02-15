<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Transformer Factory/Registry
 *
 * Manages transformer instances and provides a pipeline for chaining transformations
 */
class Maho_FeedManager_Model_Transformer
{
    /**
     * Registered transformers
     *
     * @var array<string, string>
     */
    protected static array $_transformers = [
        'strip_tags' => Maho_FeedManager_Model_Transformer_StripTags::class,
        'truncate' => Maho_FeedManager_Model_Transformer_Truncate::class,
        'default_value' => Maho_FeedManager_Model_Transformer_DefaultValue::class,
        'prepend_append' => Maho_FeedManager_Model_Transformer_PrependAppend::class,
        'replace' => Maho_FeedManager_Model_Transformer_Replace::class,
        'map_values' => Maho_FeedManager_Model_Transformer_MapValues::class,
        'format_price' => Maho_FeedManager_Model_Transformer_FormatPrice::class,
        'format_date' => Maho_FeedManager_Model_Transformer_FormatDate::class,
        'url_encode' => Maho_FeedManager_Model_Transformer_UrlEncode::class,
        'combine_fields' => Maho_FeedManager_Model_Transformer_CombineFields::class, // Internal only - used by Mapper
        'conditional' => Maho_FeedManager_Model_Transformer_Conditional::class,
        'round' => Maho_FeedManager_Model_Transformer_Round::class,
        'uppercase' => Maho_FeedManager_Model_Transformer_Uppercase::class,
        'lowercase' => Maho_FeedManager_Model_Transformer_Lowercase::class,
        'capitalise' => Maho_FeedManager_Model_Transformer_Capitalise::class,
    ];

    /**
     * Cached transformer instances
     *
     * @var array<string, Maho_FeedManager_Model_Transformer_TransformerInterface>
     */
    protected static array $_instances = [];

    /**
     * Internal-only transformers (not shown in UI)
     *
     * @var array<string>
     */
    protected static array $_internalOnly = ['combine_fields'];

    /**
     * Transformer categories for UI grouping
     *
     * @var array<string, array<string>>
     */
    protected static array $_categories = [
        'text_formatting' => ['uppercase', 'lowercase', 'capitalise', 'strip_tags', 'truncate', 'replace'],
        'values_defaults' => ['default_value', 'map_values', 'conditional'],
        'numbers_prices' => ['format_price', 'round'],
        'dates_urls' => ['format_date', 'url_encode'],
        'advanced' => ['prepend_append'],
    ];

    /**
     * Category labels
     *
     * @var array<string, string>
     */
    protected static array $_categoryLabels = [
        'text_formatting' => 'Text Formatting',
        'values_defaults' => 'Values & Defaults',
        'numbers_prices' => 'Numbers & Prices',
        'dates_urls' => 'Dates & URLs',
        'advanced' => 'Advanced',
    ];

    /**
     * Get transformer instance by code
     */
    public static function getTransformer(string $code): ?Maho_FeedManager_Model_Transformer_TransformerInterface
    {
        if (!isset(self::$_transformers[$code])) {
            return null;
        }

        if (!isset(self::$_instances[$code])) {
            $class = self::$_transformers[$code];
            self::$_instances[$code] = new $class();
        }

        return self::$_instances[$code];
    }

    /**
     * Get all registered transformer codes
     *
     * @return string[]
     */
    public static function getAvailableTransformers(): array
    {
        return array_keys(self::$_transformers);
    }

    /**
     * Get transformer options for dropdown
     *
     * @return array<string, string>
     */
    public static function getTransformerOptions(): array
    {
        $options = ['' => '-- Select Transformer --'];

        foreach (array_keys(self::$_transformers) as $code) {
            // Skip internal-only transformers
            if (in_array($code, self::$_internalOnly, true)) {
                continue;
            }
            $transformer = self::getTransformer($code);
            if ($transformer) {
                $options[$code] = $transformer->getName();
            }
        }

        return $options;
    }

    /**
     * Register a custom transformer
     */
    public static function registerTransformer(string $code, string $class): void
    {
        if (!is_subclass_of($class, Maho_FeedManager_Model_Transformer_TransformerInterface::class)) {
            throw new InvalidArgumentException(
                'Transformer class must implement Maho_FeedManager_Model_Transformer_TransformerInterface',
            );
        }

        self::$_transformers[$code] = $class;
        unset(self::$_instances[$code]);
    }

    /**
     * Check if transformer is registered
     */
    public static function hasTransformer(string $code): bool
    {
        return isset(self::$_transformers[$code]);
    }

    /**
     * Apply a single transformation
     *
     * @param mixed $value The value to transform
     * @param string $transformerCode The transformer to use
     * @param array<string, mixed> $options Transformer options
     * @param array<string, mixed> $productData Full product data for context
     * @return mixed Transformed value
     */
    public static function apply(mixed $value, string $transformerCode, array $options = [], array $productData = []): mixed
    {
        $transformer = self::getTransformer($transformerCode);
        if (!$transformer) {
            return $value;
        }

        return $transformer->transform($value, $options, $productData);
    }

    /**
     * Apply a chain of transformations (pipeline)
     *
     * @param mixed $value The initial value
     * @param array<int, array{transformer: string, options?: array}> $transformations List of transformations
     * @param array<string, mixed> $productData Full product data for context
     * @return mixed Final transformed value
     */
    public static function pipeline(mixed $value, array $transformations, array $productData = []): mixed
    {
        foreach ($transformations as $transformation) {
            $code = $transformation['transformer'] ?? '';
            $options = $transformation['options'] ?? [];

            if ($code !== '') {
                $value = self::apply($value, $code, $options, $productData);
            }
        }

        return $value;
    }

    /**
     * Get option definitions for all transformers
     *
     * @return array<string, array>
     */
    public static function getAllOptionDefinitions(): array
    {
        $definitions = [];

        foreach (array_keys(self::$_transformers) as $code) {
            $transformer = self::getTransformer($code);
            if ($transformer) {
                $definitions[$code] = [
                    'name' => $transformer->getName(),
                    'description' => $transformer->getDescription(),
                    'options' => $transformer->getOptionDefinitions(),
                ];
            }
        }

        return $definitions;
    }

    /**
     * Get transformers grouped by category
     *
     * @return array<string, array<string, array{code: string, name: string, description: string}>>
     */
    public static function getTransformersByCategory(): array
    {
        $grouped = [];

        foreach (self::$_categories as $category => $codes) {
            $label = self::$_categoryLabels[$category] ?? $category;
            $grouped[$label] = [];

            foreach ($codes as $code) {
                $transformer = self::getTransformer($code);
                if ($transformer) {
                    $grouped[$label][$code] = [
                        'code' => $code,
                        'name' => $transformer->getName(),
                        'description' => $transformer->getDescription(),
                    ];
                }
            }
        }

        return $grouped;
    }

    /**
     * Get all transformer data for JavaScript (includes categories and options)
     *
     * @return array<string, mixed>
     */
    public static function getTransformerDataForJs(): array
    {
        return [
            'categories' => self::getTransformersByCategory(),
            'definitions' => self::getAllOptionDefinitions(),
        ];
    }

    /**
     * Parse a transformer chain string into array format
     *
     * Format: "transformer_code:opt1=val1,opt2=val2|next_transformer:opts..."
     *
     * @param string $chainString The transformer chain string
     * @return array<int, array{transformer: string, options: array<string, string>}>
     */
    public static function parseChainString(string $chainString): array
    {
        if (empty($chainString)) {
            return [];
        }

        $chain = [];
        $transformers = explode('|', $chainString);

        foreach ($transformers as $transformerStr) {
            $parts = explode(':', $transformerStr, 2);
            $code = trim($parts[0]);

            if (empty($code) || !self::hasTransformer($code)) {
                continue;
            }

            $options = [];
            if (isset($parts[1]) && $parts[1] !== '') {
                $optPairs = explode(',', $parts[1]);
                foreach ($optPairs as $pair) {
                    $kv = explode('=', $pair, 2);
                    if (count($kv) === 2) {
                        $options[trim($kv[0])] = trim($kv[1]);
                    }
                }
            }

            $chain[] = [
                'transformer' => $code,
                'options' => $options,
            ];
        }

        return $chain;
    }

    /**
     * Build a transformer chain string from array format
     *
     * @param array<int, array{transformer: string, options?: array<string, string>}> $chain
     */
    public static function buildChainString(array $chain): string
    {
        $parts = [];

        foreach ($chain as $item) {
            $code = $item['transformer'] ?? '';
            if (empty($code)) {
                continue;
            }

            $optStr = '';
            if (!empty($item['options'])) {
                $optParts = [];
                foreach ($item['options'] as $key => $value) {
                    $optParts[] = "{$key}={$value}";
                }
                $optStr = ':' . implode(',', $optParts);
            }

            $parts[] = $code . $optStr;
        }

        return implode('|', $parts);
    }
}
