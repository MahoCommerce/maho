<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Model_Transformer_PrependAppend extends Maho_FeedManager_Model_Transformer_AbstractTransformer
{
    protected string $_code = 'prepend_append';
    protected string $_name = 'Prepend/Append';
    protected string $_description = 'Add text before and/or after the value';

    protected array $_optionDefinitions = [
        'prepend' => [
            'label' => 'Prepend Text',
            'type' => 'text',
            'required' => false,
            'note' => 'Text to add before the value',
        ],
        'append' => [
            'label' => 'Append Text',
            'type' => 'text',
            'required' => false,
            'note' => 'Text to add after the value',
        ],
        'skip_if_empty' => [
            'label' => 'Skip if Empty',
            'type' => 'select',
            'required' => false,
            'options' => ['1' => 'Yes', '0' => 'No'],
            'note' => 'Do not prepend/append if original value is empty',
        ],
    ];

    #[\Override]
    public function transform(mixed $value, array $options = [], array $productData = []): mixed
    {
        $prepend = (string) $this->_getOption($options, 'prepend', '');
        $append = (string) $this->_getOption($options, 'append', '');
        $skipIfEmpty = (bool) $this->_getOption($options, 'skip_if_empty', true);

        $stringValue = (string) $value;

        if ($skipIfEmpty && trim($stringValue) === '') {
            return $value;
        }

        return $prepend . $stringValue . $append;
    }
}
