<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Model_Transformer_Uppercase extends Maho_FeedManager_Model_Transformer_AbstractTransformer
{
    protected string $_code = 'uppercase';
    protected string $_name = 'Uppercase';
    protected string $_description = 'Convert text to uppercase';

    protected array $_optionDefinitions = [];

    #[\Override]
    public function transform(mixed $value, array $options = [], array $productData = []): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        return mb_strtoupper($value, 'UTF-8');
    }
}
