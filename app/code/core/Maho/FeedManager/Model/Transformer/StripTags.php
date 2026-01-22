<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Model_Transformer_StripTags extends Maho_FeedManager_Model_Transformer_AbstractTransformer
{
    protected string $_code = 'strip_tags';
    protected string $_name = 'Strip HTML Tags';
    protected string $_description = 'Remove HTML tags from text, optionally preserving certain tags';

    protected array $_optionDefinitions = [
        'allowed_tags' => [
            'label' => 'Allowed Tags',
            'type' => 'text',
            'required' => false,
            'note' => 'Tags to preserve (e.g., "<p><br><b>"). Leave empty to strip all.',
        ],
        'decode_entities' => [
            'label' => 'Decode HTML Entities',
            'type' => 'select',
            'required' => false,
            'options' => ['1' => 'Yes', '0' => 'No'],
            'note' => 'Convert &amp; to & etc.',
        ],
    ];

    #[\Override]
    public function transform(mixed $value, array $options = [], array $productData = []): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $allowedTags = $this->_getOption($options, 'allowed_tags', '');
        $decodeEntities = (bool) $this->_getOption($options, 'decode_entities', true);

        $result = strip_tags($value, $allowedTags ?: null);

        if ($decodeEntities) {
            $result = html_entity_decode($result, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Normalize whitespace
        $result = preg_replace('/\s+/', ' ', $result);

        return trim($result);
    }
}
