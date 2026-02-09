<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Model_Transformer_Replace extends Maho_FeedManager_Model_Transformer_AbstractTransformer
{
    protected string $_code = 'replace';
    protected string $_name = 'Find & Replace';
    protected string $_description = 'Replace text patterns in the value';

    protected array $_optionDefinitions = [
        'search' => [
            'label' => 'Search Pattern',
            'type' => 'text',
            'required' => true,
            'note' => 'Text or regex pattern to find',
        ],
        'replace' => [
            'label' => 'Replacement',
            'type' => 'text',
            'required' => false,
            'note' => 'Text to replace with (leave empty to remove)',
        ],
        'is_regex' => [
            'label' => 'Use Regular Expression',
            'type' => 'select',
            'required' => false,
            'options' => ['0' => 'No', '1' => 'Yes'],
            'note' => 'Treat search pattern as regex',
        ],
        'case_sensitive' => [
            'label' => 'Case Sensitive',
            'type' => 'select',
            'required' => false,
            'options' => ['1' => 'Yes', '0' => 'No'],
            'note' => 'Match case exactly',
        ],
    ];

    #[\Override]
    public function transform(mixed $value, array $options = [], array $productData = []): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $search = (string) $this->_getOption($options, 'search', '');
        $replace = (string) $this->_getOption($options, 'replace', '');
        $isRegex = (bool) $this->_getOption($options, 'is_regex', false);
        $caseSensitive = (bool) $this->_getOption($options, 'case_sensitive', true);

        if ($search === '') {
            return $value;
        }

        if ($isRegex) {
            $pattern = $search;
            if (!$caseSensitive) {
                // Only add 'i' flag if not already present in the flags section
                // Extract flags by finding the last delimiter and checking what follows
                if (preg_match('/^(.)(.*)\1([imsxADSUXJu]*)$/s', $pattern, $matches)) {
                    // Valid delimited regex - check if 'i' is in the flags
                    if (!str_contains($matches[3], 'i')) {
                        $pattern .= 'i';
                    }
                } else {
                    // Non-delimited pattern - just append 'i'
                    $pattern .= 'i';
                }
            }
            return preg_replace($pattern, $replace, $value) ?? $value;
        }

        if ($caseSensitive) {
            return str_replace($search, $replace, $value);
        }

        return str_ireplace($search, $replace, $value);
    }

    #[\Override]
    public function validateOptions(array $options): array
    {
        $errors = parent::validateOptions($options);

        if (!empty($options['is_regex']) && !empty($options['search'])) {
            // Validate regex pattern
            $pattern = $options['search'];
            if (@preg_match($pattern, '') === false) {
                $errors[] = 'Invalid regular expression pattern';
            }
        }

        return $errors;
    }
}
