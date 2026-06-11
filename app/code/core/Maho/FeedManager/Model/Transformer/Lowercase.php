<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

class Maho_FeedManager_Model_Transformer_Lowercase extends Maho_FeedManager_Model_Transformer_AbstractTransformer
{
    protected string $_code = 'lowercase';
    protected string $_name = 'Lowercase';
    protected string $_description = 'Convert text to lowercase';

    protected array $_optionDefinitions = [];

    #[\Override]
    public function transform(mixed $value, array $options = [], array $productData = []): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        return mb_strtolower($value, 'UTF-8');
    }
}
