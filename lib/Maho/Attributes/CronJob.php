<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
readonly class CronJob
{
    public function __construct(
        public ?string $schedule = null,
        public ?string $configPath = null,
        public ?string $name = null,
    ) {}
}
