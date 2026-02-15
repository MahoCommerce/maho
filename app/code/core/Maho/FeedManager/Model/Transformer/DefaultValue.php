<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Model_Transformer_DefaultValue extends Maho_FeedManager_Model_Transformer_AbstractTransformer
{
    protected string $_code = 'default_value';
    protected string $_name = 'Default Value';
    protected string $_description = 'Provide a fallback value when the field is empty';

    protected array $_optionDefinitions = [
        'default' => [
            'label' => 'Default Value',
            'type' => 'text',
            'required' => true,
            'note' => 'Value to use when the original is empty',
        ],
        'empty_includes_zero' => [
            'label' => 'Treat Zero as Empty',
            'type' => 'select',
            'required' => false,
            'options' => ['0' => 'No', '1' => 'Yes'],
            'note' => 'If yes, 0 and "0" will be replaced with default',
        ],
    ];

    #[\Override]
    public function transform(mixed $value, array $options = [], array $productData = []): mixed
    {
        $default = $this->_getOption($options, 'default', '');
        $emptyIncludesZero = (bool) $this->_getOption($options, 'empty_includes_zero', false);

        $isEmpty = $value === null || $value === '' || (is_array($value) && empty($value));

        if ($emptyIncludesZero && ($value === 0 || $value === '0' || $value === 0.0)) {
            $isEmpty = true;
        }

        return $isEmpty ? $default : $value;
    }
}
