<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Model_Transformer_CombineFields extends Maho_FeedManager_Model_Transformer_AbstractTransformer
{
    protected string $_code = 'combine_fields';
    protected string $_name = 'Combine Fields';
    protected string $_description = 'Combine multiple product fields into one value using a template';

    protected array $_optionDefinitions = [
        'template' => [
            'label' => 'Template',
            'type' => 'textarea',
            'required' => true,
            'note' => 'Use {{field_name}} placeholders (e.g., "{{brand}} - {{name}} ({{sku}})")',
        ],
        'skip_if_any_empty' => [
            'label' => 'Skip if Any Field Empty',
            'type' => 'select',
            'required' => false,
            'options' => ['0' => 'No', '1' => 'Yes'],
            'note' => 'Return empty if any referenced field is empty',
        ],
    ];

    #[\Override]
    public function transform(mixed $value, array $options = [], array $productData = []): mixed
    {
        $template = (string) $this->_getOption($options, 'template', '');
        $skipIfEmpty = (bool) $this->_getOption($options, 'skip_if_any_empty', false);

        if ($template === '') {
            return $value;
        }

        // Find all placeholders
        preg_match_all('/\{\{([^}]+)\}\}/', $template, $matches);

        if (empty($matches[1])) {
            return $template;
        }

        $result = $template;
        foreach ($matches[1] as $fieldName) {
            $fieldName = trim($fieldName);
            $fieldValue = $productData[$fieldName] ?? null;

            if ($skipIfEmpty && ($fieldValue === '' || $fieldValue === null)) {
                return '';
            }

            $result = str_replace('{{' . $fieldName . '}}', (string) $fieldValue, $result);
        }

        return $result;
    }
}
