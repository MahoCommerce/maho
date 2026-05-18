<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_Ai
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Ai_Model_Source_OpenaiEmbed
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'text-embedding-3-small', 'label' => 'text-embedding-3-small (512–1536d, recommended)'],
            ['value' => 'text-embedding-3-large', 'label' => 'text-embedding-3-large (512–3072d, highest quality)'],
            ['value' => 'text-embedding-ada-002', 'label' => 'text-embedding-ada-002 (1536d, legacy)'],
        ];
    }
}
