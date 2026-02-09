<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Model_Transformer_Capitalise extends Maho_FeedManager_Model_Transformer_AbstractTransformer
{
    protected string $_code = 'capitalise';
    protected string $_name = 'Capitalise';
    protected string $_description = 'Capitalise text (title case, first letter of each word, or first letter only)';

    protected array $_optionDefinitions = [
        'mode' => [
            'label' => 'Capitalisation Mode',
            'type' => 'select',
            'required' => false,
            'options' => [
                'title' => 'Title Case (Each Word)',
                'first' => 'First Letter Only',
                'sentence' => 'Sentence Case',
            ],
            'note' => 'How to capitalise the text',
        ],
    ];

    #[\Override]
    public function transform(mixed $value, array $options = [], array $productData = []): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        $mode = $this->_getOption($options, 'mode', 'title');

        return match ($mode) {
            'first' => $this->capitaliseFirst($value),
            'sentence' => $this->capitaliseSentence($value),
            default => $this->capitaliseTitle($value),
        };
    }

    /**
     * Capitalise first letter of each word (title case)
     */
    protected function capitaliseTitle(string $value): string
    {
        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Capitalise first letter only
     */
    protected function capitaliseFirst(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        $firstChar = mb_strtoupper(mb_substr($value, 0, 1, 'UTF-8'), 'UTF-8');
        $rest = mb_substr($value, 1, null, 'UTF-8');

        return $firstChar . $rest;
    }

    /**
     * Capitalise first letter of each sentence
     */
    protected function capitaliseSentence(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');

        // Split by sentence-ending punctuation
        $sentences = preg_split('/([.!?]+\s*)/', $value, -1, PREG_SPLIT_DELIM_CAPTURE);

        $result = '';
        foreach ($sentences as $i => $part) {
            if ($i % 2 === 0) {
                // This is a sentence, capitalise first letter
                $result .= $this->capitaliseFirst(ltrim($part));
            } else {
                // This is a delimiter (punctuation + space)
                $result .= $part;
            }
        }

        return $result;
    }
}
