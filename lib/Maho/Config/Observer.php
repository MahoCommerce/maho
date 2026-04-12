<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Config;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
readonly class Observer
{
    public function __construct(
        public string $event,
        public string $area = 'global',
        public string $type = 'singleton',
        public ?string $name = null,
        public ?string $replaces = null,
        public array $args = [],
    ) {}
}
