<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_Ai
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Ai_Model_Source_OpenaiImage
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'dall-e-3', 'label' => 'DALL-E 3 (1024×1024, 1024×1792, 1792×1024)'],
            ['value' => 'dall-e-2', 'label' => 'DALL-E 2 (256×256 – 1024×1024)'],
        ];
    }
}
