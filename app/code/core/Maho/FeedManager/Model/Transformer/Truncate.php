<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Model_Transformer_Truncate extends Maho_FeedManager_Model_Transformer_AbstractTransformer
{
    protected string $_code = 'truncate';
    protected string $_name = 'Truncate';
    protected string $_description = 'Limit text to a maximum length';

    protected array $_optionDefinitions = [
        'max_length' => [
            'label' => 'Maximum Length',
            'type' => 'text',
            'required' => true,
            'note' => 'Maximum number of characters',
        ],
        'suffix' => [
            'label' => 'Suffix',
            'type' => 'text',
            'required' => false,
            'note' => 'Text to append when truncated (e.g., "...")',
        ],
        'word_boundary' => [
            'label' => 'Respect Word Boundary',
            'type' => 'select',
            'required' => false,
            'options' => ['1' => 'Yes', '0' => 'No'],
            'note' => 'Truncate at word boundary to avoid cutting words',
        ],
    ];

    #[\Override]
    public function transform(mixed $value, array $options = [], array $productData = []): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $maxLength = (int) $this->_getOption($options, 'max_length', 255);
        $suffix = (string) $this->_getOption($options, 'suffix', '');
        $wordBoundary = (bool) $this->_getOption($options, 'word_boundary', false);

        if (mb_strlen($value) <= $maxLength) {
            return $value;
        }

        $effectiveLength = $maxLength - mb_strlen($suffix);

        if ($wordBoundary) {
            $truncated = mb_substr($value, 0, $effectiveLength);
            $lastSpace = mb_strrpos($truncated, ' ');
            if ($lastSpace !== false && $lastSpace > $effectiveLength * 0.5) {
                $truncated = mb_substr($truncated, 0, $lastSpace);
            }
        } else {
            $truncated = mb_substr($value, 0, $effectiveLength);
        }

        return trim($truncated) . $suffix;
    }
}
